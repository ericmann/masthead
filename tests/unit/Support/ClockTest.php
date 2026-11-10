<?php
/**
 * Clock tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Support;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use DateTimeImmutable;
use DateTimeZone;
use Masthead\Support\Clock;
use Masthead\Tests\Unit\TestCase;

/**
 * Clock tests.
 */
final class ClockTest extends TestCase {

	public function test_now_is_in_site_timezone(): void {
		$now = ( new Clock() )->now();
		$this->assertSame( self::ZONE, $now->getTimezone()->getName() );
	}

	public function test_filter_pins_now_with_datetime(): void {
		$pin = new DateTimeImmutable( '2026-11-09 22:00:00', new DateTimeZone( 'UTC' ) );
		Filters\expectApplied( 'masthead_now' )->once()->andReturn( $pin );
		$now = ( new Clock() )->now();
		$this->assertSame( '2026-11-09 14:00:00', $now->format( 'Y-m-d H:i:s' ) );
		$this->assertSame( self::ZONE, $now->getTimezone()->getName() );
	}

	public function test_filter_pins_now_with_site_time_string(): void {
		Filters\expectApplied( 'masthead_now' )->once()->andReturn( '2026-11-09 14:00:00' );
		$now = ( new Clock() )->now();
		$this->assertSame( '2026-11-09 14:00:00', $now->format( 'Y-m-d H:i:s' ) );
		$this->assertSame( self::ZONE, $now->getTimezone()->getName() );
	}

	public function test_invalid_filter_value_is_ignored(): void {
		Filters\expectApplied( 'masthead_now' )->twice()->andReturn( 'garbage', 42 );
		$clock = new Clock();
		$this->assertNotSame( 'garbage', $clock->now()->format( 'Y' ) );
		$this->assertSame( self::ZONE, $clock->now()->getTimezone()->getName() );
	}

	public function test_today_uses_pinned_now(): void {
		Filters\expectApplied( 'masthead_now' )->once()->andReturn( '2026-11-09 14:00:00' );
		$this->assertSame( '2026-11-09', ( new Clock() )->today() );
	}

	public function test_format_delegates_to_wp_date(): void {
		$dt = new DateTimeImmutable( '2026-11-09 14:00:00', new DateTimeZone( self::ZONE ) );
		Functions\expect( 'wp_date' )->once()->andReturnUsing(
			function ( $format, $timestamp, $zone ) use ( $dt ) {
				$this->assertSame( 'l', $format );
				$this->assertSame( $dt->getTimestamp(), $timestamp );
				$this->assertSame( self::ZONE, $zone->getName() );
				return 'Monday';
			}
		);
		$this->assertSame( 'Monday', ( new Clock() )->format( $dt, 'l' ) );
	}
}
