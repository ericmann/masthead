<?php
/**
 * DismissSuggestion ability integration tests.
 *
 * @package Masthead
 */

namespace Masthead\Tests\Integration\Abilities;

use Masthead\Cli\Seeder;
use Masthead\Plan\SeriesSource;
use Masthead\Plan\Slots;
use Masthead\Tests\Integration\TestCase;

/**
 * DismissSuggestion ability integration tests.
 */
final class DismissSuggestionTest extends TestCase {

	public function set_up(): void {
		parent::set_up();
		$series = new SeriesSource();
		$series->register();
		$seeder = new Seeder( new Slots( $series ), $series );
		$seeder->reset();
		$seeder->seed( json_decode( (string) file_get_contents( dirname( __DIR__, 3 ) . '/docs/fixtures/queue-2026-11-09.json' ), true ), '2026-11-09 14:00:00' );
	}

	private function input(): array {
		return array(
			'date' => '2026-11-18',
			'key'  => 'draft:10178',
		);
	}

	private function suggestion_keys( int $user_id ): array {
		wp_set_current_user( $user_id );
		$result = wp_get_ability( 'masthead/suggest-for-date' )->execute( array( 'date' => '2026-11-18' ) );
		return array_column( $result['suggestions'], 'key' );
	}

	public function test_allowed(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'contributor' ) ) );
		$this->assertTrue( wp_get_ability( 'masthead/dismiss-suggestion' )->check_permissions( $this->input() ) );
	}

	public function test_denied(): void {
		$user = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user );
		$ability = wp_get_ability( 'masthead/dismiss-suggestion' );
		$result  = $ability->check_permissions( $this->input() );
		$this->assertTrue( false === $result || is_wp_error( $result ) );
		$this->assertWPError( $ability->execute( $this->input() ) );
		$this->assertSame( '', get_user_meta( $user, 'masthead_dismissed', true ) );
	}

	public function test_happy_path(): void {
		$user = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user );
		$this->assertSame( array( 'ok' => true ), wp_get_ability( 'masthead/dismiss-suggestion' )->execute( $this->input() ) );
		$this->assertSame( array( '2026-11-18' => array( 'draft:10178' ) ), get_user_meta( $user, 'masthead_dismissed', true ) );
		$bad = wp_get_ability( 'masthead/dismiss-suggestion' )->execute(
			array(
				'date' => 'junk',
				'key'  => 'x',
			)
		);
		$this->assertWPError( $bad );
	}

	public function test_dismissed_draft_no_longer_appears_for_that_user_and_date(): void {
		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		$other  = self::factory()->user->create( array( 'role' => 'editor' ) );
		$this->assertContains( 'draft:10178', $this->suggestion_keys( $editor ) );
		wp_set_current_user( $editor );
		wp_get_ability( 'masthead/dismiss-suggestion' )->execute( $this->input() );
		$this->assertNotContains( 'draft:10178', $this->suggestion_keys( $editor ) );
		$this->assertContains( 'draft:10178', $this->suggestion_keys( $other ), 'another user still sees it' );
	}

	public function test_dismissing_twice_is_idempotent(): void {
		$user = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user );
		$ability = wp_get_ability( 'masthead/dismiss-suggestion' );
		$ability->execute( $this->input() );
		$ability->execute( $this->input() );
		$this->assertSame( array( '2026-11-18' => array( 'draft:10178' ) ), get_user_meta( $user, 'masthead_dismissed', true ) );
	}
}
