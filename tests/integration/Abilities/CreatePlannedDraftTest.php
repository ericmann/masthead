<?php
/**
 * CreatePlannedDraft ability integration tests.
 *
 * @package Masthead
 */

namespace Masthead\Tests\Integration\Abilities;

use Masthead\Plan\SeriesSource;
use Masthead\Tests\Integration\TestCase;

/**
 * CreatePlannedDraft ability integration tests.
 */
final class CreatePlannedDraftTest extends TestCase {

	public function set_up(): void {
		parent::set_up();
		( new SeriesSource() )->register();
		self::factory()->category->create(
			array(
				'slug' => 'technology',
				'name' => 'Technology',
			) 
		);
	}

	private function ability() {
		$ability = wp_get_ability( 'masthead/create-planned-draft' );
		$this->assertNotNull( $ability );
		return $ability;
	}

	private function input( array $extra = array() ): array {
		return array_merge(
			array(
				'date'     => '2026-11-18',
				'time'     => '09:30',
				'title'    => 'Planned by ability',
				'category' => 'technology',
			),
			$extra
		);
	}

	private function count_posts(): int {
		return (int) ( new \WP_Query(
			array(
				'post_status' => 'any',
				'fields' => 'ids',
				'no_found_rows' => true,
				'posts_per_page' => 50,
			) 
		) )->post_count;
	}

	public function test_allowed(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'contributor' ) ) );
		$this->assertTrue( $this->ability()->check_permissions( $this->input() ) );
	}

	public function test_denied(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$before = $this->count_posts();
		$this->assertWPError( $this->ability()->execute( $this->input() ) );
		$this->assertSame( $before, $this->count_posts() );
	}

	public function test_happy_path(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$result = $this->ability()->execute( $this->input() );
		$this->assertNotWPError( $result );
		$this->assertSame( '2026-11-18T09:30:00-08:00', $result['date'] );
		$this->assertStringContainsString( 'post=' . $result['post_id'], $result['edit_url'] );
		$post = get_post( $result['post_id'] );
		$this->assertSame( 'draft', $post->post_status );
		$this->assertSame( '2026-11-18 09:30:00', $post->post_date );
	}

	public function test_outline_is_stored_in_meta_and_content(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$result = $this->ability()->execute( $this->input( array( 'outline' => array( 'Intro', 'Body' ) ) ) );
		$this->assertSame( '["Intro","Body"]', get_post_meta( $result['post_id'], 'masthead_outline', true ) );
		$this->assertStringContainsString( '<li>Intro</li>', get_post( $result['post_id'] )->post_content );
	}

	public function test_unknown_category_is_refused_without_creating_a_post(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$before = $this->count_posts();
		$result = $this->ability()->execute( $this->input( array( 'category' => 'nope' ) ) );
		$this->assertWPError( $result );
		$this->assertSame( 'masthead_unknown_category', $result->get_error_code() );
		$this->assertSame( $before, $this->count_posts() );
	}

	public function test_unknown_series_is_refused_without_creating_a_post(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$before = $this->count_posts();
		$result = $this->ability()->execute(
			$this->input(
				array(
					'series' => array(
						'term_id' => 999999,
						'part'    => 1,
					),
				)
			)
		);
		$this->assertWPError( $result );
		$this->assertSame( 'masthead_unknown_series', $result->get_error_code() );
		$this->assertSame( $before, $this->count_posts() );

		$term_id = self::factory()->term->create(
			array(
				'taxonomy' => 'masthead_series',
				'name' => 'S',
			) 
		);
		$ok      = $this->ability()->execute(
			$this->input(
				array(
					'series' => array(
						'term_id' => $term_id,
						'part' => 2,
					),
				) 
			) 
		);
		$this->assertNotWPError( $ok );
	}
}
