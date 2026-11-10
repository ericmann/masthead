<?php
/**
 * Tuning harness for stats.cache_ttl (P3-06).
 *
 * Run: vendor/bin/phpunit -c phpunit.xml.dist --group tuning --filter StatsCacheBench
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Tuning;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Masthead\Config;
use Masthead\Stats\Cache;
use Masthead\Support\Clock;
use Masthead\Tests\Unit\TestCase;

/**
 * Simulates one day against Stats\Cache for several cache lifetimes.
 *
 * @group tuning
 */
final class StatsCacheBench extends TestCase {

	private const READ_STEP_SECONDS = 1800;
	private const FIRST_READ_HOUR   = 6;
	private const LAST_READ_HOUR    = 22;
	private const SECONDS_PER_HOUR  = 3600;

	/**
	 * Simulated seconds since midnight.
	 *
	 * @var int
	 */
	private int $now = 0;

	/**
	 * In-memory transients: name => [ value, expires_at ].
	 *
	 * @var array<string,array{0:mixed,1:int}>
	 */
	private array $store = array();

	/**
	 * Lifetimes to measure.
	 *
	 * @return array<string,array{0:int}>
	 */
	public static function lifetimes(): array {
		return array(
			'3600'  => array( 3600 ),
			'21600' => array( 21600 ),
			'86400' => array( 86400 ),
		);
	}

	protected function setUp(): void {
		parent::setUp();
		$this->now   = 0;
		$this->store = array();
		Functions\when( 'get_option' )->alias(
			static fn( $name, $fallback = false ) => 'masthead_settings' === $name ? array() : $fallback
		);
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'get_transient' )->alias(
			function ( $name ) {
				$entry = $this->store[ $name ] ?? null;
				return ( null !== $entry && $this->now < $entry[1] ) ? $entry[0] : false;
			}
		);
		Functions\when( 'set_transient' )->alias(
			function ( $name, $value, $ttl ) {
				$this->store[ $name ] = array( $value, $this->now + $ttl );
				return true;
			}
		);
		Filters\expectApplied( 'masthead_now' )->zeroOrMoreTimes()->andReturnUsing(
			fn() => sprintf( '2026-11-09 %02d:%02d:00', intdiv( $this->now, self::SECONDS_PER_HOUR ), intdiv( $this->now % self::SECONDS_PER_HOUR, 60 ) )
		);
	}

	/**
	 * Measures one lifetime.
	 *
	 * @dataProvider lifetimes
	 * @param int $ttl Cache lifetime in seconds.
	 * @return void
	 */
	public function test_measure_calls_and_staleness_per_ttl( int $ttl ): void {
		Filters\expectApplied( 'masthead_config' )->zeroOrMoreTimes()->andReturnUsing(
			static function ( $flat ) use ( $ttl ) {
				$flat['stats.cache_ttl'] = $ttl;
				return $flat;
			}
		);
		$cache   = new Cache( new Clock() );
		$methods = array( 'get_visits', 'get_top_posts', 'get_tags' );
		$calls   = 0;
		$fetched = array();
		$oldest  = 0;
		$reads   = 0;

		$read_all = function () use ( $cache, $methods, &$calls, &$fetched, &$oldest, &$reads ) {
			foreach ( $methods as $method ) {
				$cache->remember(
					$method,
					array(),
					function () use ( $method, &$calls, &$fetched ) {
						++$calls;
						$fetched[ $method ] = $this->now;
						return array( 'payload' => $method );
					}
				);
				$oldest = max( $oldest, $this->now - $fetched[ $method ] );
				++$reads;
			}
		};

		$this->now = (int) Config::get( 'cron.nightly_hour' ) * self::SECONDS_PER_HOUR;
		$read_all();
		for ( $this->now = self::FIRST_READ_HOUR * self::SECONDS_PER_HOUR; $this->now <= self::LAST_READ_HOUR * self::SECONDS_PER_HOUR; $this->now += self::READ_STEP_SECONDS ) {
			$read_all();
		}

		echo esc_html( sprintf( "\nStatsCacheBench ttl=%d upstream_calls_per_day=%d greatest_age_hours=%.2f reads=%d\n", $ttl, $calls, $oldest / self::SECONDS_PER_HOUR, $reads ) );
		$this->assertGreaterThan( 0, $reads );
		$this->assertGreaterThanOrEqual( count( $methods ), $calls );
	}
}
