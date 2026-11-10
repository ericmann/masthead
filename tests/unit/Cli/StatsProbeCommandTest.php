<?php
/**
 * StatsProbeCommand tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Cli;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Masthead\Cli\StatsProbeCommand;
use Masthead\Stats\Cache;
use Masthead\Stats\JetpackStats;
use Masthead\Stats\NullStats;
use Masthead\Support\Clock;
use Masthead\Tests\Unit\TestCase;

require_once dirname( __DIR__ ) . '/stubs/WP_CLI.php';

/**
 * StatsProbeCommand tests.
 */
final class StatsProbeCommandTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		\WP_CLI::reset();
		Functions\when( 'get_option' )->alias(
			static fn( $name, $fallback = false ) => 'masthead_settings' === $name ? array() : $fallback
		);
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'is_wp_error' )->alias( static fn( $v ) => $v instanceof \WP_Error );
		Functions\when( 'get_posts' )->justReturn( array() );
		Filters\expectApplied( 'masthead_now' )->zeroOrMoreTimes()->andReturn( '2026-11-09 13:00:00' );
	}

	public function test_reports_unavailable_without_jetpack_stats(): void {
		( new StatsProbeCommand( new NullStats() ) )->__invoke( array(), array() );
		$this->assertSame( array( 'Jetpack Stats is not available on this site.' ), \WP_CLI::$lines );
	}

	public function test_days_default_comes_from_config(): void {
		$client = new class() {
			public array $quantities = array();

			public function __call( string $method, array $arguments ) {
				if ( 'get_visits' === $method ) {
					$this->quantities[] = $arguments[0]['quantity'];
				}
				return array( 'data' => array( array( '2026-11-09', 5 ) ) );
			}
		};
		$stats  = new JetpackStats( new Cache( new Clock() ), static fn() => null, $client );
		$command = new StatsProbeCommand( $stats );
		$command->__invoke( array(), array() );
		$command->__invoke( array(), array( 'days' => '7' ) );
		$this->assertSame( array( 30, 7 ), $client->quantities );
		$printed = json_decode( \WP_CLI::$lines[0], true );
		$this->assertSame( array( array( '', 0 ) ), $printed['get_visits']['data'] );
	}
}
