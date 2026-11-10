<?php
/**
 * Reschedule ability integration tests.
 *
 * @package Masthead
 */

namespace Masthead\Tests\Integration\Abilities;

use Masthead\Plan\SeriesSource;
use Masthead\Plan\Slots;
use Masthead\Tests\Integration\TestCase;

/**
 * Reschedule ability integration tests.
 */
final class RescheduleTest extends TestCase {

	private function ability() {
		$ability = wp_get_ability( 'masthead/reschedule' );
		$this->assertNotNull( $ability );
		return $ability;
	}

	private function future_post( int $author = 0 ): int {
		$args = array(
			'post_title'    => 'Moveable',
			'post_date'     => '2026-11-20 07:30:00',
			'post_date_gmt' => '2026-11-20 15:30:00',
			'edit_date'     => true,
		);
		if ( $author > 0 ) {
			$args['post_author'] = $author;
		}
		return ( new Slots( new SeriesSource() ) )->insert( $args, 'future' );
	}

	public function test_allowed(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$id = $this->future_post();
		$this->assertTrue(
			$this->ability()->check_permissions(
				array(
					'post_id' => $id,
					'date'    => '2026-12-01',
				)
			)
		);
	}

	public function test_denied(): void {
		$owner = self::factory()->user->create( array( 'role' => 'author' ) );
		$id    = $this->future_post( $owner );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$input  = array(
			'post_id' => $id,
			'date'    => '2026-12-01',
		);
		$result = $this->ability()->check_permissions( $input );
		$this->assertTrue( false === $result || is_wp_error( $result ) );
		$this->assertWPError( $this->ability()->execute( $input ) );
		$this->assertSame( '2026-11-20 07:30:00', get_post( $id )->post_date );
	}

	public function test_happy_path(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$id     = $this->future_post();
		$result = $this->ability()->execute(
			array(
				'post_id' => $id,
				'date'    => '2026-12-01',
				'time'    => '09:00',
			)
		);
		$this->assertNotWPError( $result );
		$this->assertSame(
			array(
				'post_id' => $id,
				'date'    => '2026-12-01T09:00:00-08:00',
				'status'  => 'future',
			),
			$result
		);
		$this->assertSame( 'future', get_post_status( $id ) );
	}

	public function test_published_post_is_refused(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$id     = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_date' => '2026-11-01 08:00:00',
			) 
		);
		$result = $this->ability()->execute(
			array(
				'post_id' => $id,
				'date'    => '2026-12-01',
			)
		);
		$this->assertWPError( $result );
		$this->assertSame( 'masthead_published_locked', $result->get_error_code() );
		$this->assertSame( 409, $result->get_error_data()['status'] );
	}

	public function test_repeating_the_call_is_idempotent(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$id    = $this->future_post();
		$input = array(
			'post_id' => $id,
			'date'    => '2026-12-01',
		);
		$first  = $this->ability()->execute( $input );
		$second = $this->ability()->execute( $input );
		$this->assertSame( $first, $second );
		$this->assertSame( '2026-12-01 07:30:00', get_post( $id )->post_date );
	}
}
