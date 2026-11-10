<?php
/**
 * Reschedule integration tests.
 *
 * @package Masthead
 */

namespace Masthead\Tests\Integration\Plan;

use Masthead\Plan\Reschedule;
use Masthead\Plan\SeriesSource;
use Masthead\Plan\Slots;
use Masthead\Support\Clock;
use Masthead\Tests\Integration\TestCase;

/**
 * Reschedule integration tests.
 */
final class RescheduleTest extends TestCase {

	/**
	 * Slots.
	 *
	 * @var Slots
	 */
	private Slots $slots;

	/**
	 * Reschedule under test.
	 *
	 * @var Reschedule
	 */
	private Reschedule $reschedule;

	public function set_up(): void {
		parent::set_up();
		$this->slots      = new Slots( new SeriesSource() );
		$this->reschedule = new Reschedule( new Clock() );
	}

	private function post( string $status, ?string $local ): int {
		$arr = array( 'post_title' => 'Moveable' );
		if ( null !== $local ) {
			$arr['post_date']     = $local;
			$arr['post_date_gmt'] = get_gmt_from_date( $local );
			$arr['edit_date']     = true;
		} else {
			$arr['post_date_gmt'] = '0000-00-00 00:00:00';
		}
		return $this->slots->insert( $arr, $status );
	}

	public function test_future_post_moves_and_stays_future(): void {
		$id     = $this->post( 'future', '2026-11-20 07:30:00' );
		$result = $this->reschedule->move( $id, '2026-12-01', null );
		$this->assertSame( 'future', $result['status'] );
		$this->assertSame( '2026-12-01 07:30:00', get_post( $id )->post_date );
		$this->assertSame( '2026-12-01 15:30:00', get_post( $id )->post_date_gmt );
		$this->assertSame( 'future', get_post_status( $id ) );
	}

	public function test_dated_draft_moves_and_stays_draft(): void {
		$id = $this->post( 'draft', '2026-11-20 07:30:00' );
		$this->assertIsArray( $this->reschedule->move( $id, '2026-11-02', '09:00' ) );
		$post = get_post( $id );
		$this->assertSame( '2026-11-02 09:00:00', $post->post_date );
		$this->assertSame( 'draft', $post->post_status );
	}

	public function test_undated_draft_becomes_dated(): void {
		$id = $this->post( 'draft', null );
		$this->assertFalse( Slots::is_dated( get_post( $id ) ) );
		$this->assertIsArray( $this->reschedule->move( $id, '2026-12-12', null ) );
		$post = get_post( $id );
		$this->assertTrue( Slots::is_dated( $post ) );
		$this->assertSame( '2026-12-12 06:30:00', $post->post_date );
		$this->assertSame( 'draft', $post->post_status );
	}

	public function test_action_reports_old_and_new_dates(): void {
		$id    = $this->post( 'draft', '2026-11-20 07:30:00' );
		$calls = array();
		add_action(
			'masthead_rescheduled',
			static function ( ...$args ) use ( &$calls ) {
				$calls[] = $args;
			},
			10,
			3
		);
		$this->reschedule->move( $id, '2026-11-23', null );
		$this->assertSame( array( array( $id, '2026-11-20 07:30:00', '2026-11-23 07:30:00' ) ), $calls );
		$this->reschedule->move( $id, '2026-11-23', null );
		$this->assertCount( 1, $calls, 'idempotent: no second event' );
	}

	public function test_published_post_is_refused_and_unchanged(): void {
		$id     = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_date' => '2026-11-01 08:00:00',
			) 
		);
		$result = $this->reschedule->move( $id, '2026-12-01', null );
		$this->assertWPError( $result );
		$this->assertSame( 'masthead_published_locked', $result->get_error_code() );
		$this->assertSame( '2026-11-01 08:00:00', get_post( $id )->post_date );
		$this->assertSame( 'publish', get_post_status( $id ) );
	}
}
