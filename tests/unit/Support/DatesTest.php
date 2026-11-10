<?php
/**
 * Dates tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Support;

use Masthead\Support\Dates;
use Masthead\Tests\Unit\TestCase;

/**
 * Dates tests.
 */
final class DatesTest extends TestCase {

	public function test_parse_date_rejects_invalid_and_overflowing_dates(): void {
		$this->assertNull( Dates::parse_date( '2026-02-30' ) );
		$this->assertNull( Dates::parse_date( '2026-13-01' ) );
		$this->assertNull( Dates::parse_date( '2026-1-1' ) );
		$this->assertNull( Dates::parse_date( 'tomorrow' ) );
		$this->assertNull( Dates::parse_date( '' ) );
		$this->assertNotNull( Dates::parse_date( '2026-02-28' ) );
		$this->assertFalse( Dates::is_date( '2026-02-29' ) );
		$this->assertTrue( Dates::is_date( '2028-02-29' ) );
	}

	public function test_parse_does_not_depend_on_the_wall_clock(): void {
		$parsed = Dates::parse_date( '2026-11-09' );
		$this->assertSame( '2026-11-09 00:00:00', Dates::to_mysql( $parsed ) );
		$this->assertSame( self::ZONE, $parsed->getTimezone()->getName() );
	}

	public function test_weekday_of_2026_11_09_is_monday(): void {
		$this->assertSame( 1, Dates::weekday( '2026-11-09' ) );
		$this->assertSame( 0, Dates::weekday( '2026-11-08' ) );
		$this->assertSame( 6, Dates::weekday( '2026-11-14' ) );
		$this->assertSame( 7, Dates::DAYS_IN_WEEK );
	}

	public function test_add_days_crosses_dst_boundary_by_calendar_day(): void {
		// US DST ends 2026-11-01.
		$this->assertSame( '2026-11-02', Dates::add_days( '2026-10-31', 2 ) );
		$this->assertSame( '2026-10-31', Dates::add_days( '2026-11-02', -2 ) );
		// US DST starts 2026-03-08.
		$this->assertSame( '2026-03-09', Dates::add_days( '2026-03-07', 2 ) );
		$this->assertSame( '', Dates::add_days( 'junk', 1 ) );
	}

	public function test_diff_days_is_signed(): void {
		$this->assertSame( 8, Dates::diff_days( '2026-10-31', '2026-11-08' ) );
		$this->assertSame( -8, Dates::diff_days( '2026-11-08', '2026-10-31' ) );
		$this->assertSame( 0, Dates::diff_days( '2026-11-08', '2026-11-08' ) );
		$this->assertSame( 2, Dates::diff_days( '2026-03-07', '2026-03-09' ) );
	}

	public function test_range_is_inclusive_and_empty_when_reversed(): void {
		$this->assertSame( array( '2026-11-08', '2026-11-09', '2026-11-10' ), Dates::range( '2026-11-08', '2026-11-10' ) );
		$this->assertSame( array( '2026-11-08' ), Dates::range( '2026-11-08', '2026-11-08' ) );
		$this->assertSame( array(), Dates::range( '2026-11-10', '2026-11-08' ) );
		$this->assertSame( array(), Dates::range( 'bad', '2026-11-08' ) );
	}

	public function test_combine_and_time_of_round_trip(): void {
		$local = Dates::combine( '2026-11-09', '09:30' );
		$this->assertSame( '2026-11-09 09:30:00', $local );
		$this->assertSame( '09:30', Dates::time_of( $local ) );
		$this->assertSame( '', Dates::time_of( 'junk' ) );
	}

	public function test_local_to_gmt_uses_site_timezone(): void {
		$this->assertSame( '2026-11-09 22:00:00', Dates::local_to_gmt( '2026-11-09 14:00:00' ) );
		$this->assertSame( '2026-07-09 21:00:00', Dates::local_to_gmt( '2026-07-09 14:00:00' ) );
		$this->assertSame( '0000-00-00 00:00:00', Dates::local_to_gmt( 'junk' ) );
	}

	public function test_to_iso_carries_the_site_offset(): void {
		$this->assertSame( '2026-11-09T14:00:00-08:00', Dates::to_iso( Dates::parse_local( '2026-11-09 14:00:00' ) ) );
		$this->assertSame( '2026-07-09T14:00:00-07:00', Dates::to_iso( Dates::parse_local( '2026-07-09 14:00:00' ) ) );
		$parsed = Dates::parse_iso( '2026-11-09T22:00:00+00:00' );
		$this->assertSame( '2026-11-09 14:00:00', Dates::to_mysql( $parsed ) );
		$this->assertNull( Dates::parse_iso( 'nonsense' ) );
		$this->assertSame( '2026-11-09', Dates::to_ymd( $parsed ) );
	}

	public function test_is_time_accepts_only_24_hour_hh_mm(): void {
		$this->assertTrue( Dates::is_time( '00:00' ) );
		$this->assertTrue( Dates::is_time( '09:30' ) );
		$this->assertTrue( Dates::is_time( '23:59' ) );
		$this->assertFalse( Dates::is_time( '24:00' ) );
		$this->assertFalse( Dates::is_time( '9:30' ) );
		$this->assertFalse( Dates::is_time( '09:60' ) );
		$this->assertFalse( Dates::is_time( '09:30:00' ) );
		$this->assertFalse( Dates::is_time( '' ) );
	}
}
