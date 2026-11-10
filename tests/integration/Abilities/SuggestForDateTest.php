<?php
/**
 * SuggestForDate ability integration tests.
 *
 * @package Masthead
 */

namespace Masthead\Tests\Integration\Abilities;

use Masthead\Cli\Seeder;
use Masthead\Plan\SeriesSource;
use Masthead\Plan\Slots;
use Masthead\Tests\Integration\TestCase;

/**
 * SuggestForDate ability integration tests.
 */
final class SuggestForDateTest extends TestCase {

	public function set_up(): void {
		parent::set_up();
		$series = new SeriesSource();
		$series->register();
		$seeder = new Seeder( new Slots( $series ), $series );
		$seeder->reset();
		$seeder->seed( json_decode( (string) file_get_contents( dirname( __DIR__, 3 ) . '/docs/fixtures/queue-2026-11-09.json' ), true ), '2026-11-09 14:00:00' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
	}

	private function ability() {
		$ability = wp_get_ability( 'masthead/suggest-for-date' );
		$this->assertNotNull( $ability );
		return $ability;
	}

	private function keys( array $result ): array {
		return array_column( $result['suggestions'], 'key' );
	}

	public function test_annotations_match_spec(): void {
		$annotations = $this->ability()->get_meta()['annotations'];
		$this->assertTrue( $annotations['readonly'] );
		$this->assertFalse( $annotations['idempotent'] );
		$this->assertFalse( $annotations['destructive'] );
	}

	public function test_allowed(): void {
		$this->assertTrue( $this->ability()->check_permissions( array( 'date' => '2026-11-18' ) ) );
	}

	public function test_denied(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$result = $this->ability()->check_permissions( array( 'date' => '2026-11-18' ) );
		$this->assertTrue( false === $result || is_wp_error( $result ) );
		$this->assertWPError( $this->ability()->execute( array( 'date' => '2026-11-18' ) ) );
	}

	public function test_happy_path(): void {
		$result = $this->ability()->execute( array( 'date' => '2026-11-18' ) );
		$this->assertNotWPError( $result );
		$this->assertSame( '2026-11-18', $result['date'] );
		$this->assertSame( 'standalone', $result['lane']['slug'] );
		$this->assertFalse( $result['ai']['used'] );
		$this->assertIsInt( $result['ai']['remaining'] );
		$this->assertNotEmpty( $result['suggestions'] );
		$this->assertSame( array( 'schedule', 'open', 'dismiss' ), $result['suggestions'][0]['actions'] );
	}

	public function test_nov_18_first_suggestion_is_draft_10178_with_the_spec_reason(): void {
		$result = $this->ability()->execute( array( 'date' => '2026-11-18' ) );
		$first  = $result['suggestions'][0];
		$this->assertSame( 'draft:10178', $first['key'] );
		$this->assertSame( 'draft', $first['kind'] );
		$this->assertSame( 10178, $first['post_id'] );
		$this->assertSame( "Wednesday is your Standalone slot. This draft isn't on the calendar; last edited Oct 29.", $first['why'] );
	}

	public function test_nov_18_lists_a_gap_and_no_reshare_when_stats_are_unavailable(): void {
		$result = $this->ability()->execute( array( 'date' => '2026-11-18' ) );
		$kinds  = array_column( $result['suggestions'], 'kind' );
		$this->assertContains( 'gap', $kinds );
		$this->assertNotContains( 'reshare', $kinds );
		$gap = array_values( array_filter( $result['suggestions'], static fn( $s ) => 'gap' === $s['kind'] ) )[0];
		$this->assertStringStartsWith( 'Nothing has been published under ', $gap['why'] );
		$this->assertStringStartsWith( 'gap:', $gap['key'] );
		$this->assertSame( array( 'create_draft', 'suggest_topics', 'dismiss' ), $gap['actions'] );
	}

	public function test_nov_11_has_a_post_and_gets_no_suggestions(): void {
		$result = $this->ability()->execute( array( 'date' => '2026-11-11' ) );
		$this->assertSame( array(), $result['suggestions'] );
	}

	public function test_invalid_date_is_refused(): void {
		$result = $this->ability()->execute( array( 'date' => '2026-02-30' ) );
		$this->assertWPError( $result );
		$this->assertSame( 'masthead_invalid_date', $result->get_error_code() );
		$this->assertSame( 400, $result->get_error_data()['status'] );
	}

	public function test_include_ideas_requires_prompt_ai(): void {
		$input = array(
			'date'          => '2026-11-18',
			'include_ideas' => true,
		);
		// A contributor has edit_posts but not publish_posts, so the prompt_ai fallback does not apply.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'contributor' ) ) );
		$this->assertTrue( $this->ability()->check_permissions( array( 'date' => '2026-11-18' ) ) );
		$denied = $this->ability()->check_permissions( $input );
		$this->assertTrue( false === $denied || is_wp_error( $denied ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertTrue( $this->ability()->check_permissions( $input ) );
	}

	public function test_include_ideas_appends_fixture_ideas_and_reports_ai_used(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		delete_option( 'masthead_ai_usage' );
		$plain  = $this->ability()->execute( array( 'date' => '2026-11-18' ) );
		$result = $this->ability()->execute(
			array(
				'date'          => '2026-11-18',
				'include_ideas' => true,
			)
		);
		$this->assertNotWPError( $result );
		$this->assertTrue( $result['ai']['used'], wp_json_encode( $result['ai'] ) );
		$this->assertSame( $plain['suggestions'], array_slice( $result['suggestions'], 0, count( $plain['suggestions'] ) ) );
		$ideas = array_slice( $result['suggestions'], count( $plain['suggestions'] ) );
		$this->assertCount( 3, $ideas );
		foreach ( $ideas as $idea ) {
			$this->assertSame( 'idea', $idea['kind'] );
			$this->assertStringStartsWith( 'idea:', $idea['key'] );
			$this->assertSame( array( 'create_draft', 'dismiss' ), $idea['actions'] );
			$this->assertNotSame( '', $idea['why'] );
		}
		$this->assertSame( array( 'opinion', 'security', 'technology' ), array_column( $ideas, 'category' ) );
		$this->assertSame( $plain['ai']['remaining'] - 1, $result['ai']['remaining'] );
	}
}
