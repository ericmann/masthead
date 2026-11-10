<?php
/**
 * Cache tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Stats;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Masthead\Stats\Cache;
use Masthead\Support\Clock;
use Masthead\Tests\Unit\TestCase;

/**
 * Cache tests.
 */
final class CacheTest extends TestCase {

	/**
	 * Fake transient store.
	 *
	 * @var array<string,array{0:mixed,1:int}>
	 */
	private array $store = array();

	protected function setUp(): void {
		parent::setUp();
		$this->store = array();
		Functions\when( 'get_option' )->alias(
			static fn( $name, $fallback = false ) => 'masthead_settings' === $name ? array() : $fallback
		);
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'get_transient' )->alias( fn( $name ) => $this->store[ $name ][0] ?? false );
		Functions\when( 'set_transient' )->alias(
			function ( $name, $value, $ttl ) {
				$this->store[ $name ] = array( $value, $ttl );
				return true;
			}
		);
		Filters\expectApplied( 'masthead_now' )->zeroOrMoreTimes()->andReturn( '2026-11-09 13:00:00' );
	}

	public function test_second_call_uses_the_cached_value(): void {
		$cache = new Cache( new Clock() );
		$calls = 0;
		$make  = static function () use ( &$calls ) {
			++$calls;
			return array( 'a' => 1 );
		};
		$this->assertSame( array( 'a' => 1 ), $cache->remember( 'top', array( 'days' => 30 ), $make ) );
		$this->assertSame( array( 'a' => 1 ), $cache->remember( 'top', array( 'days' => 30 ), $make ) );
		$this->assertSame( 1, $calls );
		$cache->remember( 'top', array( 'days' => 7 ), $make );
		$this->assertSame( 2, $calls, 'different arguments are a different entry' );
		foreach ( array_keys( $this->store ) as $name ) {
			$this->assertStringStartsWith( 'masthead_stats_', $name );
		}
	}

	public function test_null_is_not_cached(): void {
		$cache = new Cache( new Clock() );
		$calls = 0;
		$make  = static function () use ( &$calls ) {
			++$calls;
			return null;
		};
		$this->assertNull( $cache->remember( 'top', array(), $make ) );
		$this->assertNull( $cache->remember( 'top', array(), $make ) );
		$this->assertSame( 2, $calls );
		$this->assertSame( array(), $this->store );
	}

	public function test_ttl_comes_from_config(): void {
		$cache = new Cache( new Clock() );
		$cache->remember( 'top', array(), static fn() => 1 );
		$this->assertSame( 21600, array_values( $this->store )[0][1] );

		$this->store = array();
		Filters\expectApplied( 'masthead_config' )->zeroOrMoreTimes()->andReturnUsing(
			static function ( $flat ) {
				$flat['stats.cache_ttl'] = 60;
				return $flat;
			}
		);
		$cache->remember( 'top', array(), static fn() => 1 );
		$this->assertSame( 60, array_values( $this->store )[0][1] );
	}

	public function test_fetched_at_is_the_clock_time_of_the_fetch(): void {
		$cache = new Cache( new Clock() );
		$this->assertNull( $cache->fetched_at( 'top', array() ) );
		$cache->remember( 'top', array(), static fn() => 1 );
		$this->assertSame( '2026-11-09T13:00:00-08:00', $cache->fetched_at( 'top', array() ) );
	}
}
