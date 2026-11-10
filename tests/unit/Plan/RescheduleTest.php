<?php
/**
 * Reschedule tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Plan;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Masthead\Plan\Reschedule;
use Masthead\Support\Clock;
use Masthead\Tests\Unit\TestCase;

/**
 * Reschedule tests.
 */
final class RescheduleTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_option' )->alias(
			static fn( $name, $fallback = false ) => 'masthead_settings' === $name ? array() : $fallback
		);
		Functions\when( 'is_wp_error' )->alias( static fn( $v ) => $v instanceof \WP_Error );
		Filters\expectApplied( 'masthead_now' )->zeroOrMoreTimes()->andReturn( '2026-11-09 14:00:00' );
	}

	private function post( string $status, ?string $date = '2026-11-20 07:30:00' ): void {
		Functions\when( 'get_post' )->justReturn(
			(object) array(
				'ID'            => 9,
				'post_type'     => 'post',
				'post_status'   => $status,
				'post_date'     => $date ?? '2026-11-01 10:00:00',
				'post_date_gmt' => null === $date ? '0000-00-00 00:00:00' : '2026-11-20 15:30:00',
			)
		);
	}

	private function mover(): Reschedule {
		return new Reschedule( new Clock() );
	}

	public function test_refuses_published_post(): void {
		$this->post( 'publish' );
		Functions\expect( 'wp_update_post' )->never();
		$result = $this->mover()->move( 9, '2026-12-01', null );
		$this->assertSame( 'masthead_published_locked', $result->get_error_code() );
		$this->assertSame( array( 'status' => 409 ), $result->get_error_data() );
	}

	public function test_refuses_unsupported_status(): void {
		$this->post( 'pending' );
		Functions\expect( 'wp_update_post' )->never();
		$this->assertSame( 'masthead_unsupported_status', $this->mover()->move( 9, '2026-12-01', null )->get_error_code() );
		Functions\when( 'get_post' )->justReturn( null );
		$this->assertSame( 'masthead_not_found', $this->mover()->move( 9, '2026-12-01', null )->get_error_code() );
		$this->post( 'draft' );
		$this->assertSame( 'masthead_invalid_date', $this->mover()->move( 9, '2026-02-30', null )->get_error_code() );
		$this->assertSame( 'masthead_invalid_date', $this->mover()->move( 9, '2026-12-01', '99:00' )->get_error_code() );
	}

	public function test_refuses_past_datetime_for_future_post(): void {
		$this->post( 'future' );
		Functions\expect( 'wp_update_post' )->never();
		$result = $this->mover()->move( 9, '2026-11-09', '13:59' );
		$this->assertSame( 'masthead_past_date', $result->get_error_code() );
		$this->assertSame( array( 'status' => 400 ), $result->get_error_data() );
		$this->assertSame( 'masthead_past_date', $this->mover()->move( 9, '2026-11-09', '14:00' )->get_error_code() );
	}

	public function test_keeps_time_when_time_is_omitted(): void {
		$this->post( 'future' );
		Functions\expect( 'wp_update_post' )->once()->andReturnUsing(
			function ( $args ) {
				$this->assertSame( '2026-12-05 07:30:00', $args['post_date'] );
				$this->assertSame( '2026-12-05 15:30:00', $args['post_date_gmt'] );
				$this->assertTrue( $args['edit_date'] );
				return 9;
			}
		);
		Actions\expectDone( 'masthead_rescheduled' )->once()->with( 9, '2026-11-20 07:30:00', '2026-12-05 07:30:00' );
		$this->assertSame(
			array(
				'post_id' => 9,
				'date'    => '2026-12-05T07:30:00-08:00',
				'status'  => 'future',
			),
			$this->mover()->move( 9, '2026-12-05', null )
		);
	}

	public function test_undated_draft_takes_lane_time(): void {
		$this->post( 'draft', null );
		Functions\expect( 'wp_update_post' )->once()->andReturnUsing(
			function ( $args ) {
				// 2026-12-12 is a Saturday: fiction lane, 06:30.
				$this->assertSame( '2026-12-12 06:30:00', $args['post_date'] );
				return 9;
			}
		);
		Actions\expectDone( 'masthead_rescheduled' )->once()->with( 9, null, '2026-12-12 06:30:00' );
		$result = $this->mover()->move( 9, '2026-12-12', null );
		$this->assertSame( 'draft', $result['status'] );
	}

	public function test_same_date_and_time_writes_nothing(): void {
		$this->post( 'future' );
		Functions\expect( 'wp_update_post' )->never();
		Actions\expectDone( 'masthead_rescheduled' )->never();
		$result = $this->mover()->move( 9, '2026-11-20', '07:30' );
		$this->assertSame( '2026-11-20T07:30:00-08:00', $result['date'] );
	}
}
