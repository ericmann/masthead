<?php
/**
 * Cadence tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Plan;

use Brain\Monkey\Functions;
use Masthead\Plan\Cadence;
use Masthead\Tests\Unit\TestCase;

/**
 * Cadence tests.
 */
final class CadenceTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
	}

	private function mantle( ?int $weekday = null ): array {
		return array(
			'every_weeks' => 4,
			'weekday'     => $weekday,
			'time'        => '08:00',
		);
	}

	public function test_sanitize_rejects_zero_weeks_bad_weekday_and_bad_time(): void {
		$this->assertNull( Cadence::sanitize( 'x' ) );
		$this->assertNull(
			Cadence::sanitize(
				array(
					'every_weeks' => 0,
					'weekday' => null,
					'time' => '08:00',
				) 
			) 
		);
		$this->assertNull(
			Cadence::sanitize(
				array(
					'every_weeks' => 4,
					'weekday' => 7,
					'time' => '08:00',
				) 
			) 
		);
		$this->assertNull(
			Cadence::sanitize(
				array(
					'every_weeks' => 4,
					'weekday' => 'mon',
					'time' => '08:00',
				) 
			) 
		);
		$this->assertNull(
			Cadence::sanitize(
				array(
					'every_weeks' => 4,
					'weekday' => 1,
					'time' => '8am',
				) 
			) 
		);
		$this->assertNull(
			Cadence::sanitize(
				array(
					'every_weeks' => 4,
					'weekday' => 1,
				) 
			) 
		);
		$this->assertSame(
			$this->mantle( 6 ),
			Cadence::sanitize(
				array(
					'every_weeks' => '4',
					'weekday' => '6',
					'time' => '08:00',
				) 
			) 
		);
		$this->assertSame(
			$this->mantle(),
			Cadence::sanitize(
				array(
					'every_weeks' => 4,
					'time' => '08:00',
				) 
			) 
		);
	}

	public function test_json_round_trip(): void {
		$json = Cadence::to_json( $this->mantle( 6 ) );
		$this->assertSame( '{"every_weeks":4,"weekday":6,"time":"08:00"}', $json );
		$this->assertSame( $this->mantle( 6 ), Cadence::from_json( $json ) );
		$this->assertNull( Cadence::from_json( '[]' ) );
		$this->assertSame( '', Cadence::sanitize_json( '{"every_weeks":0}' ) );
		$this->assertSame( $json, Cadence::sanitize_json( $json ) );
		$this->assertSame( $json, Cadence::sanitize_json( $this->mantle( 6 ) ) );
	}

	public function test_expected_date_same_weekday_four_weeks_after_2026_11_09_is_2026_12_07(): void {
		$this->assertSame( '2026-12-07', Cadence::expected_date( '2026-11-09', $this->mantle() ) );
	}

	public function test_expected_date_moves_to_named_weekday(): void {
		// Base 2026-12-07 is a Monday; Saturday is two days before (2026-12-05), Wednesday two after.
		$this->assertSame( '2026-12-05', Cadence::expected_date( '2026-11-09', $this->mantle( 6 ) ) );
		$this->assertSame( '2026-12-09', Cadence::expected_date( '2026-11-09', $this->mantle( 3 ) ) );
		$this->assertSame( '2026-12-07', Cadence::expected_date( '2026-11-09', $this->mantle( 1 ) ) );
	}

	public function test_mantle_part_two_on_2026_12_14_is_a_break_with_window_three(): void {
		$this->assertTrue( Cadence::is_break( '2026-11-09', '2026-12-14', $this->mantle(), 3 ) );
		$this->assertFalse( Cadence::is_break( '2026-11-09', '2026-12-10', $this->mantle(), 3 ) );
		$this->assertFalse( Cadence::is_break( '2026-11-09', '2026-12-04', $this->mantle(), 3 ) );
		$this->assertTrue( Cadence::is_break( '2026-11-09', '2026-12-03', $this->mantle(), 3 ) );
	}

	public function test_mantle_part_three_on_2027_01_11_after_2026_12_14_is_not_a_break(): void {
		$this->assertFalse( Cadence::is_break( '2026-12-14', '2027-01-11', $this->mantle(), 3 ) );
	}

	public function test_schedule_returns_requested_number_of_dates(): void {
		$this->assertSame( array( '2026-11-09', '2026-12-07', '2027-01-04' ), Cadence::schedule( '2026-11-09', $this->mantle(), 3 ) );
		$this->assertSame( array(), Cadence::schedule( '2026-11-09', $this->mantle(), 0 ) );
	}
}
