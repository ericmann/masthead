<?php
/**
 * Tuning harness for the Desk assumptions (P4-11).
 *
 * Run: npx wp-env run tests-cli --env-cwd=wp-content/plugins/masthead vendor/bin/phpunit -c phpunit.integration.xml.dist --group tuning --filter DeskBench
 *
 * @package Masthead
 */

namespace Masthead\Tests\Integration\Tuning;

use Masthead\Cli\Seeder;
use Masthead\Desk\Candidates\CategoryGap;
use Masthead\Desk\Candidates\DraftMatch;
use Masthead\Desk\Candidates\Reshare;
use Masthead\Desk\Candidates\SeriesNext;
use Masthead\Desk\Dismissals;
use Masthead\Desk\Ranker;
use Masthead\Desk\Reasons;
use Masthead\Desk\Suggester;
use Masthead\Plan\Lanes;
use Masthead\Plan\PlanReader;
use Masthead\Plan\SeriesSource;
use Masthead\Plan\Slots;
use Masthead\Social\ShareSource;
use Masthead\Stats\FixtureStats;
use Masthead\Support\Categories;
use Masthead\Support\Clock;
use Masthead\Support\Dates;
use Masthead\Tests\Integration\TestCase;

/**
 * Measures the Desk's assumption keys on the fixture plus 300 synthetic posts.
 *
 * @group tuning
 */
final class DeskBench extends TestCase {

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
	 * Plan reader.
	 *
	 * @var PlanReader
	 */
	private PlanReader $reader;

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
		$slots        = new Slots( $this->series );
		$this->reader = new PlanReader( new Clock(), $this->series );

		$seeder = new Seeder( $slots, $this->series );
		$seeder->reset();
		$fixture = json_decode( (string) file_get_contents( dirname( __DIR__, 3 ) . '/docs/fixtures/queue-2026-11-09.json' ), true );
		$seeder->seed( $fixture, self::TODAY . ' 14:00:00' );

		$categories = get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => false,
				'orderby'    => 'term_id',
				'number'     => 50,
			)
		);
		$ids        = wp_list_pluck( $categories, 'term_id' );
		for ( $i = 0; $i < 300; $i++ ) {
			$day   = Dates::add_days( self::TODAY, -( intdiv( $i * 400, 300 ) + 1 ) );
			$local = $day . ' 08:00:00';
			$slots->insert(
				array(
					'post_title'    => 'Synthetic ' . $i,
					'post_date'     => $local,
					'post_date_gmt' => Dates::local_to_gmt( $local ),
					'edit_date'     => true,
					'post_category' => array( (int) $ids[ $i % count( $ids ) ] ),
				),
				'publish'
			);
		}
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
	}

	private function share(): ShareSource {
		return new class() implements ShareSource {
			public function available(): bool {
				return true;
			}

			public function connections(): array {
				return array();
			}

			public function share_now( int $post_id, string $message, array $connection_ids ): array {
				return array();
			}

			public function share_at( int $post_id, string $message, array $connection_ids, string $iso_datetime ): array {
				return array();
			}
		};
	}

	private function stats(): FixtureStats {
		return new FixtureStats(
			dirname( __DIR__, 3 ) . '/docs/fixtures/stats-sample.json',
			static fn( int $post_id ): ?string => Categories::for_post( $post_id )['primary']
		);
	}

	private function suggester(): Suggester {
		$clock = new Clock();
		return new Suggester(
			new DraftMatch( $this->reader ),
			new SeriesNext( $this->series ),
			new Reshare( $this->stats(), $this->share(), $clock ),
			new CategoryGap( $clock ),
			new Ranker(),
			new Reasons( $clock ),
			new Dismissals( $clock ),
			$this->reader,
			$clock
		);
	}

	/**
	 * Seconds to run the Suggester for every day from today through $days days ahead.
	 *
	 * @param int $days Days ahead.
	 * @return float
	 */
	private function batch_seconds( int $days ): float {
		$suggester = $this->suggester();
		$began     = microtime( true );
		foreach ( Dates::range( self::TODAY, Dates::add_days( self::TODAY, $days ) ) as $date ) {
			$suggester->suggest( $date, get_current_user_id() );
		}
		return microtime( true ) - $began;
	}

	private function say( string $line ): void {
		echo esc_html( "\nDeskBench " . $line . "\n" );
	}

	public function test_measure_desk_assumptions(): void {
		// Ghost days and horizon days: wall time of the batch.
		foreach ( array(
			'ghost_days' => array( 7, 14, 21 ),
			'horizon_days' => array( 14, 21, 30 ),
		) as $key => $values ) {
			foreach ( $values as $value ) {
				$this->say( sprintf( 'desk.%s=%d batch_seconds=%.2f', $key, $value, $this->batch_seconds( $value ) ) );
			}
		}

		// Gap days: categories that are gaps, and the share among categories that published in the last year.
		$year_ago = Dates::add_days( self::TODAY, -365 );
		foreach ( array( 30, 45, 60 ) as $value ) {
			$this->overrides = array( 'desk.gap_days' => $value );
			$gaps            = ( new CategoryGap( new Clock() ) )->gaps();
			$published       = 0;
			$gaps_among      = 0;
			$gap_ids         = array_map( static fn( $c ) => $c->to_array()['data'], $gaps );
			foreach ( $gap_ids as $data ) {
				if ( null !== $data['last_published'] && $data['last_published'] >= $year_ago ) {
					++$gaps_among;
				}
			}
			foreach ( Categories::all() as $category ) {
				$latest = get_posts(
					array(
						'post_type'     => 'post',
						'post_status'   => 'publish',
						'cat'           => $category['term_id'],
						'numberposts'   => 1,
						'no_found_rows' => true,
						'date_query'    => array( array( 'after' => $year_ago ) ),
					)
				);
				if ( array() !== $latest ) {
					++$published;
				}
			}
			$this->say( sprintf( 'desk.gap_days=%d gaps=%d categories_published_last_year=%d gaps_among_those=%d', $value, count( $gaps ), $published, $gaps_among ) );
		}
		$this->overrides = array();

		// Reshare ages: candidates for a Sunday (2026-11-15) from the fixture stats.
		$lane = Lanes::for_date( '2026-11-15' );
		foreach ( array( 60, 90, 180 ) as $value ) {
			$this->overrides = array( 'desk.reshare_min_age_days' => $value );
			$count           = count( ( new Reshare( $this->stats(), $this->share(), new Clock() ) )->for_date( '2026-11-15', $lane ) );
			$this->say( sprintf( 'desk.reshare_min_age_days=%d sunday_reshare_candidates=%d', $value, $count ) );
		}
		foreach ( array( 90, 180, 365 ) as $value ) {
			$this->overrides = array( 'desk.reshare_cooldown_days' => $value );
			$count           = count( ( new Reshare( $this->stats(), $this->share(), new Clock() ) )->for_date( '2026-11-15', $lane ) );
			$this->say( sprintf( 'desk.reshare_cooldown_days=%d sunday_reshare_candidates=%d', $value, $count ) );
		}
		$this->overrides = array();

		// Series window: a two-week series whose part 1 is dated 2026-11-09; dates in the next 28 days that count as due.
		$term = wp_insert_term( 'Bench Series', $this->series->taxonomy() );
		$term_id = (int) $term['term_id'];
		update_term_meta( $term_id, 'masthead_cadence', '{"every_weeks":2,"weekday":null,"time":"08:00"}' );
		update_term_meta( $term_id, $this->series->total_meta(), 10 );
		$slots = new Slots( $this->series );
		$first = $slots->insert(
			array(
				'post_title'    => 'Bench part 1',
				'post_date'     => self::TODAY . ' 08:00:00',
				'post_date_gmt' => Dates::local_to_gmt( self::TODAY . ' 08:00:00' ),
				'edit_date'     => true,
			),
			'future'
		);
		$this->series->set_part( $first, $term_id, 1 );
		foreach ( array( 2, 3, 4 ) as $value ) {
			$this->overrides = array( 'desk.series_window_days' => $value );
			$next            = new SeriesNext( $this->series );
			$due             = 0;
			foreach ( Dates::range( self::TODAY, Dates::add_days( self::TODAY, 27 ) ) as $date ) {
				foreach ( $next->for_date( $date, array() ) as $candidate ) {
					if ( 'series:' . $term_id . ':2' === $candidate->to_array()['key'] ) {
						++$due;
					}
				}
			}
			$target = Lanes::nearest_date_for_category( '2026-12-14', 'writing', $value );
			$this->say( sprintf( 'desk.series_window_days=%d due_dates_in_28_days=%d monday_to_saturday_target=%s', $value, $due, $target ?? 'none' ) );
		}
		$this->overrides = array();

		// Candidate pool: Suggester wall time for one date.
		foreach ( array( 25, 50, 100 ) as $value ) {
			$this->overrides = array( 'desk.candidate_pool' => $value );
			$suggester       = $this->suggester();
			$began           = microtime( true );
			$suggester->suggest( '2026-11-18', get_current_user_id() );
			$this->say( sprintf( 'desk.candidate_pool=%d one_date_seconds=%.3f', $value, microtime( true ) - $began ) );
		}
		$this->overrides = array();

		$this->assertTrue( true, 'every measurement ran' );
	}
}
