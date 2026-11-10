<?php
/**
 * Tuning harness for calendar.max_posts_per_range (P1-16).
 *
 * Run: npx wp-env run tests-cli --env-cwd=wp-content/plugins/masthead vendor/bin/phpunit -c phpunit.integration.xml.dist --group tuning --filter PlanRangeBench
 *
 * @package Masthead
 */

namespace Masthead\Tests\Integration\Tuning;

use Masthead\Plan\PlanReader;
use Masthead\Plan\SeriesSource;
use Masthead\Plan\Slots;
use Masthead\Support\Clock;
use Masthead\Support\Dates;
use Masthead\Tests\Integration\TestCase;

/**
 * Measures PlanReader::range() at several caps.
 *
 * @group tuning
 */
final class PlanRangeBench extends TestCase {

	private const RUNS = 5;

	/**
	 * Caps to measure.
	 *
	 * @return array<string,array{0:int}>
	 */
	public static function caps(): array {
		return array(
			'250'  => array( 250 ),
			'500'  => array( 500 ),
			'1000' => array( 1000 ),
		);
	}

	/**
	 * Measures one cap.
	 *
	 * @dataProvider caps
	 * @param int $cap Cap under test.
	 * @return void
	 */
	public function test_measure_range_at_each_cap( int $cap ): void {
		$slots = new Slots( new SeriesSource() );
		$days  = (int) \Masthead\Config::get( 'calendar.max_range_days' );
		$start = '2026-11-01';
		for ( $i = 0; $i < $cap; $i++ ) {
			$date  = Dates::add_days( $start, $i % $days ) . ' 08:00:00';
			$slots->insert(
				array(
					'post_title'    => 'Bench ' . $i,
					'post_date'     => $date,
					'post_date_gmt' => Dates::local_to_gmt( $date ),
					'edit_date'     => true,
				),
				'future'
			);
		}
		add_filter(
			'masthead_config',
			static function ( $flat ) use ( $cap ) {
				$flat['calendar.max_posts_per_range'] = $cap;
				return $flat;
			}
		);

		$reader = new PlanReader( new Clock(), new SeriesSource() );
		$end    = Dates::add_days( $start, $days - 1 );
		$times  = array();
		$count  = 0;
		for ( $run = 0; $run < self::RUNS; $run++ ) {
			$began = microtime( true );
			$out   = $reader->range( $start, $end );
			$times[] = ( microtime( true ) - $began ) * 1000;
			$count = array_sum( array_map( static fn( $day ) => count( $day['posts'] ), $out['days'] ) );
		}
		sort( $times );
		$median = $times[ intdiv( self::RUNS, 2 ) ];
		$peak   = memory_get_peak_usage( true ) / 1048576;
		echo esc_html( sprintf( "\nPlanRangeBench cap=%d posts=%d median_ms=%.1f peak_mb=%.1f\n", $cap, $count, $median, $peak ) );

		$this->assertSame( $cap, $count );
	}
}
