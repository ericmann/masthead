<?php
/**
 * Nightly tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Cron;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Masthead\Cron\Nightly;
use Masthead\Desk\Reasons;
use Masthead\Desk\Scoring;
use Masthead\Desk\SwapCheck;
use Masthead\Plan\PlanReader;
use Masthead\Plan\SeriesSource;
use Masthead\Stats\StatsSource;
use Masthead\Stats\NullStats;
use Masthead\Support\Clock;
use Masthead\Tests\Unit\TestCase;

/**
 * Nightly tests.
 */
final class NightlyTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_option' )->alias(
			static fn( $name, $fallback = false ) => 'masthead_settings' === $name ? array() : $fallback
		);
		Filters\expectApplied( 'masthead_now' )->zeroOrMoreTimes()->andReturn( '2026-11-09 14:00:00' );
	}

	private function swap_check( array $items ): SwapCheck {
		return new class( $items ) extends SwapCheck {
			public function __construct( private array $items ) {}

			public function run( ?string $from = null, ?string $to = null ): array {
				return $this->items;
			}
		};
	}

	/**
	 * A stats double that records which methods were called.
	 *
	 * @param bool $available Availability.
	 * @return StatsSource
	 */
	private function stats( bool $available ): object {
		return new class( $available ) implements StatsSource {
			public array $calls = array();

			public function __construct( private bool $available ) {}

			public function available(): bool {
				return $this->available;
			}

			public function views_by_category( int $days ): ?array {
				$this->calls[] = array( 'category', $days );
				return array();
			}

			public function views_by_weekday( int $days ): ?array {
				$this->calls[] = array( 'weekday', $days );
				return array();
			}

			public function top_posts( int $days, int $limit ): ?array {
				$this->calls[] = array( 'top', $days, $limit );
				return array();
			}

			public function post_views( int $post_id, int $days ): ?int {
				return null;
			}

			public function fetched_at(): ?string {
				return null;
			}
		};
	}

	public function test_run_warms_stats_stores_findings_and_fires_action(): void {
		$stored = null;
		Functions\expect( 'update_option' )->once()->andReturnUsing(
			function ( $name, $value, $autoload ) use ( &$stored ) {
				$this->assertSame( 'masthead_findings', $name );
				$this->assertFalse( $autoload );
				$stored = $value;
				return true;
			}
		);
		Actions\expectDone( 'masthead_findings_updated' )->once()->with( 2 );
		$stats = $this->stats( true );
		( new Nightly( $this->swap_check( array( array( 'key' => 'a' ), array( 'key' => 'b' ) ) ), $stats, new Clock() ) )->run();
		$this->assertSame( '2026-11-09T14:00:00-08:00', $stored['generated_at'] );
		$this->assertCount( 2, $stored['items'] );
		$this->assertSame( array( array( 'category', 30 ), array( 'weekday', 30 ), array( 'top', 30, 50 ) ), $stats->calls );
	}

	public function test_run_skips_warming_when_stats_are_unavailable(): void {
		Functions\when( 'update_option' )->justReturn( true );
		Actions\expectDone( 'masthead_findings_updated' )->once()->with( 0 );
		$stats = $this->stats( false );
		( new Nightly( $this->swap_check( array() ), $stats, new Clock() ) )->run();
		$this->assertSame( array(), $stats->calls );
	}

	public function test_first_run_is_scheduled_at_the_configured_site_hour(): void {
		Functions\when( 'wp_next_scheduled' )->justReturn( false );
		$expected = ( new \DateTimeImmutable( '2026-11-10 03:00:00', new \DateTimeZone( self::ZONE ) ) )->getTimestamp();
		Functions\expect( 'wp_schedule_event' )->once()->with( $expected, 'daily', 'masthead_nightly' );
		( new Nightly( $this->swap_check( array() ), new NullStats(), new Clock() ) )->schedule();
		$this->addToAssertionCount( 1 );
	}

	public function test_schedule_does_not_duplicate_an_existing_event(): void {
		Functions\when( 'wp_next_scheduled' )->justReturn( 1794000000 );
		Functions\expect( 'wp_schedule_event' )->never();
		( new Nightly( $this->swap_check( array() ), new NullStats(), new Clock() ) )->schedule();
		$this->assertTrue( true );
	}

	public function test_hour_before_now_schedules_today_when_still_ahead(): void {
		Functions\when( 'wp_next_scheduled' )->justReturn( false );
		Filters\expectApplied( 'masthead_config' )->once()->andReturnUsing(
			static function ( $flat ) {
				$flat['cron.nightly_hour'] = 20;
				return $flat;
			}
		);
		$expected = ( new \DateTimeImmutable( '2026-11-09 20:00:00', new \DateTimeZone( self::ZONE ) ) )->getTimestamp();
		Functions\expect( 'wp_schedule_event' )->once()->with( $expected, 'daily', 'masthead_nightly' );
		( new Nightly( $this->swap_check( array() ), new NullStats(), new Clock() ) )->schedule();
		$this->addToAssertionCount( 1 );
	}
}
