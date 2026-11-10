<?php
/**
 * Tuning harness for the swap-check assumptions (P4-17).
 *
 * Run: npx wp-env run tests-cli --env-cwd=wp-content/plugins/masthead vendor/bin/phpunit -c phpunit.integration.xml.dist --group tuning --filter SwapBench
 *
 * @package Masthead
 */

namespace Masthead\Tests\Integration\Tuning;

use Masthead\Cli\Seeder;
use Masthead\Desk\Reasons;
use Masthead\Desk\Scoring;
use Masthead\Desk\SwapCheck;
use Masthead\Plan\Lanes;
use Masthead\Plan\PlanReader;
use Masthead\Plan\SeriesSource;
use Masthead\Plan\Slots;
use Masthead\Stats\FixtureStats;
use Masthead\Stats\NullStats;
use Masthead\Stats\StatsSource;
use Masthead\Support\Categories;
use Masthead\Support\Clock;
use Masthead\Support\Dates;
use Masthead\Tests\Integration\TestCase;

/**
 * Measures the swap-check assumption keys on the fixture plus twenty synthetic scheduled posts.
 *
 * @group tuning
 */
final class SwapBench extends TestCase {

	private const TODAY = '2026-11-09';

	/**
	 * Config overrides for the current measurement.
	 *
	 * @var array<string,mixed>
	 */
	private array $overrides = array();

	/**
	 * Series adapter.
	 *
	 * @var SeriesSource
	 */
	private SeriesSource $series;

	/**
	 * Synthetic posts: post ID => in_lane flag.
	 *
	 * @var array<int,bool>
	 */
	private array $synthetic = array();

	public function set_up(): void {
		parent::set_up();
		add_filter(
			'masthead_config',
			function ( $flat ) {
				return array_merge( $flat, $this->overrides );
			}
		);
		$this->series = new SeriesSource();
		$this->series->register();
		$slots  = new Slots( $this->series );
		$seeder = new Seeder( $slots, $this->series );
		$seeder->reset();
		$seeder->seed( json_decode( (string) file_get_contents( dirname( __DIR__, 3 ) . '/docs/fixtures/queue-2026-11-09.json' ), true ), self::TODAY . ' 14:00:00' );

		$ids   = array();
		foreach ( get_terms(
			array(
				'taxonomy' => 'category',
				'hide_empty' => false,
				'number' => 50,
			) 
		) as $term ) {
			$ids[ $term->slug ] = (int) $term->term_id;
		}
		$count = 0;
		for ( $offset = 3; $count < 20 && $offset < 90; $offset += 4 ) {
			$date = Dates::add_days( self::TODAY, $offset );
			$lane = Lanes::for_date( $date );
			if ( array() === $lane['categories'] ) {
				continue;
			}
			$in_lane  = 0 === $count % 2;
			$category = $in_lane ? $lane['categories'][0] : ( in_array( 'technology', $lane['categories'], true ) ? 'opinion' : 'technology' );
			$local    = $date . ' 09:00:00';
			$id       = $slots->insert(
				array(
					'post_title'    => ( $in_lane ? 'In lane ' : 'Out of lane ' ) . $count,
					'post_date'     => $local,
					'post_date_gmt' => Dates::local_to_gmt( $local ),
					'edit_date'     => true,
					'post_category' => array( $ids[ $category ] ),
				),
				'future'
			);
			$this->synthetic[ $id ] = $in_lane;
			++$count;
		}
	}

	private function fixture_stats(): StatsSource {
		return new FixtureStats(
			dirname( __DIR__, 3 ) . '/docs/fixtures/stats-sample.json',
			static fn( int $post_id ): ?string => Categories::for_post( $post_id )['primary']
		);
	}

	private function check( StatsSource $stats ): SwapCheck {
		$clock  = new Clock();
		$reader = new PlanReader( $clock, $this->series );
		return new SwapCheck( $reader, $this->series, new Scoring(), new Reasons( $clock ), $stats, $clock );
	}

	/**
	 * Runs the check and counts findings by type.
	 *
	 * @param StatsSource $stats Stats source.
	 * @return array{counts:array<string,int>,findings:array,seconds:float}
	 */
	private function measure( StatsSource $stats ): array {
		$began    = microtime( true );
		$findings = $this->check( $stats )->run();
		$seconds  = microtime( true ) - $began;
		$counts   = array_fill_keys( SwapCheck::TYPES, 0 );
		foreach ( $findings as $finding ) {
			++$counts[ $finding['type'] ];
		}
		return array(
			'counts'   => $counts,
			'findings' => $findings,
			'seconds'  => $seconds,
		);
	}

	private function say( string $line ): void {
		echo esc_html( "\nSwapBench " . $line . "\n" );
	}

	private function counts_text( array $counts ): string {
		return sprintf( 'lane_mismatch=%d collision=%d cadence_break=%d better_candidate=%d', $counts['lane_mismatch'], $counts['collision'], $counts['cadence_break'], $counts['better_candidate'] );
	}

	/**
	 * In-lane synthetic posts that received a better_candidate finding.
	 *
	 * @param array $findings Findings.
	 * @return int
	 */
	private function in_lane_with_better( array $findings ): int {
		$hit = 0;
		foreach ( $findings as $finding ) {
			if ( 'better_candidate' === $finding['type'] && ( $this->synthetic[ $finding['post_id'] ] ?? false ) ) {
				++$hit;
			}
		}
		return $hit;
	}

	public function test_measure_swap_assumptions(): void {
		$null  = new NullStats();
		$stats = $this->fixture_stats();

		foreach ( array( 30, 60, 92 ) as $value ) {
			$this->overrides = array( 'swap.lookahead_days' => $value );
			foreach ( array(
				'null' => $null,
				'fixture' => $stats,
			) as $name => $source ) {
				$run   = $this->measure( $source );
				$keys  = array_column( $run['findings'], 'key' );
				$mantle = in_array( 'lane_mismatch:12156', $keys, true ) && in_array( 'lane_mismatch:12157', $keys, true ) && in_array( 'lane_mismatch:12158', $keys, true ) && in_array( 'cadence_break:12156', $keys, true );
				$this->say( sprintf( 'swap.lookahead_days=%d stats=%s %s seconds=%.3f mantle_acceptance=%s', $value, $name, $this->counts_text( $run['counts'] ), $run['seconds'], $mantle ? 'pass' : 'FAIL' ) );
			}
		}

		$this->overrides = array();
		foreach ( array( 0.15, 0.25, 0.35 ) as $value ) {
			$this->overrides = array( 'swap.min_score_delta' => $value );
			foreach ( array(
				'null' => $null,
				'fixture' => $stats,
			) as $name => $source ) {
				$run = $this->measure( $source );
				$this->say( sprintf( 'swap.min_score_delta=%.2f stats=%s %s in_lane_posts_with_better_candidate=%d', $value, $name, $this->counts_text( $run['counts'] ), $this->in_lane_with_better( $run['findings'] ) ) );
			}
		}

		$this->overrides = array();
		foreach ( array( 2, 3, 5 ) as $value ) {
			$this->overrides = array( 'swap.candidate_window_days' => $value );
			foreach ( array(
				'null' => $null,
				'fixture' => $stats,
			) as $name => $source ) {
				$run = $this->measure( $source );
				$this->say( sprintf( 'swap.candidate_window_days=%d stats=%s %s in_lane_posts_with_better_candidate=%d_of_%d', $value, $name, $this->counts_text( $run['counts'] ), $this->in_lane_with_better( $run['findings'] ), count( array_filter( $this->synthetic ) ) ) );
			}
		}

		$this->overrides = array();
		$alternatives    = array(
			'default 0.5/0.3/0.2' => array(
				'lane'     => 0.5,
				'category' => 0.3,
				'weekday'  => 0.2,
			),
			'0.6/0.2/0.2'         => array(
				'lane'     => 0.6,
				'category' => 0.2,
				'weekday'  => 0.2,
			),
			'0.4/0.4/0.2'         => array(
				'lane'     => 0.4,
				'category' => 0.4,
				'weekday'  => 0.2,
			),
		);
		$days = (int) \Masthead\Config::get( 'stats.window_days' );
		$by_category = $stats->views_by_category( $days );
		$by_weekday  = $stats->views_by_weekday( $days );
		$reader      = new PlanReader( new Clock(), $this->series );
		$scoring     = new Scoring();
		foreach ( $alternatives as $label => $weights ) {
			$this->overrides = array( 'swap.weights' => $weights );
			$outscored       = 0;
			$pairs           = 0;
			foreach ( $this->synthetic as $in_id => $in_lane ) {
				if ( ! $in_lane ) {
					continue;
				}
				$post = $reader->post( $in_id );
				$date = explode( 'T', $post['date'] )[0];
				$lane = Lanes::for_date( $date );
				$own  = $scoring->score( $scoring->terms( $post, $date, $lane, false, $by_category, $by_weekday ) );
				foreach ( $this->synthetic as $out_id => $out_flag ) {
					if ( $out_flag ) {
						continue;
					}
					++$pairs;
					$other = $reader->post( $out_id );
					if ( $scoring->score( $scoring->terms( $other, $date, $lane, false, $by_category, $by_weekday ) ) > $own ) {
						++$outscored;
					}
				}
			}
			$this->say( sprintf( 'swap.weights=%s in_lane_outscored_by_out_of_lane_pairs=%d_of_%d', $label, $outscored, $pairs ) );
		}

		$this->overrides = array();
		$this->assertTrue( true, 'every measurement ran' );
	}
}
