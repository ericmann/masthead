<?php
/**
 * SwapCheck tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Desk;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Masthead\Desk\Reasons;
use Masthead\Desk\Scoring;
use Masthead\Desk\SwapCheck;
use Masthead\Plan\PlanReader;
use Masthead\Plan\SeriesSource;
use Masthead\Stats\NullStats;
use Masthead\Stats\StatsSource;
use Masthead\Support\Clock;
use Masthead\Tests\Unit\TestCase;

/**
 * SwapCheck tests.
 */
final class SwapCheckTest extends TestCase {

	/**
	 * PlanPosts the reader knows, keyed by ID.
	 *
	 * @var array<int,array>
	 */
	public array $posts = array();

	/**
	 * Series parts by term ID.
	 *
	 * @var array<int,array>
	 */
	public array $parts = array();

	/**
	 * Undated drafts the reader knows.
	 *
	 * @var array<int,array>
	 */
	public array $drafts = array();

	/**
	 * Config overrides.
	 *
	 * @var array<string,mixed>
	 */
	private array $config = array();

	/**
	 * Lane exception flags by term ID.
	 *
	 * @var array<int,bool>
	 */
	private array $exceptions = array();

	/**
	 * Cadence of series 3, or null.
	 *
	 * @var array|null
	 */
	private ?array $cadence = null;

	protected function setUp(): void {
		parent::setUp();
		$this->posts      = array();
		$this->drafts     = array();
		$this->config     = array();
		$this->parts      = array();
		$this->exceptions = array();
		$this->cadence    = array(
			'every_weeks' => 4,
			'weekday'     => null,
			'time'        => '06:23',
		);
		Functions\when( 'get_option' )->alias(
			static fn( $name, $fallback = false ) => 'masthead_settings' === $name ? array() : $fallback
		);
		Functions\when( 'wp_date' )->alias(
			static fn( $format, $timestamp, $zone ) => ( new \DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $zone )->format( $format )
		);
		Filters\expectApplied( 'masthead_now' )->zeroOrMoreTimes()->andReturn( '2026-11-09 14:00:00' );
		Filters\expectApplied( 'masthead_config' )->zeroOrMoreTimes()->andReturnUsing(
			fn( $flat ) => array_merge( $flat, $this->config )
		);
	}

	private function post( int $id, string $title, string $date, string $primary, array $series = array() ): array {
		$post = array(
			'id'               => $id,
			'title'            => $title,
			'status'           => 'future',
			'date'             => $date . 'T06:23:00-08:00',
			'categories'       => array( $primary ),
			'primary_category' => $primary,
		);
		if ( array() !== $series ) {
			$post['series'] = array_merge(
				array(
					'term_id' => 3,
					'name'    => 'The Mantle',
					'total'   => 4,
				),
				$series
			);
		}
		$this->posts[ $id ] = $post;
		return $post;
	}

	private function part( int $post_id, int $part, string $date, string $status = 'future' ): void {
		$this->parts[3][] = array(
			'post_id' => $post_id,
			'part'    => $part,
			'status'  => $status,
			'date'    => $date . ' 06:23:00',
			'dated'   => true,
		);
	}

	private function check( ?StatsSource $stats = null ): SwapCheck {
		$reader = new class( $this ) extends PlanReader {
			public function __construct( private SwapCheckTest $test ) {}

			public function scheduled_between( string $from, string $to ): array {
				return array_values(
					array_filter(
						$this->test->posts(),
						static fn( $post ) => 'publish' !== $post['status'] && substr( $post['date'], 0, 10 ) >= $from && substr( $post['date'], 0, 10 ) <= $to
					)
				);
			}

			public function post( int $post_id ): ?array {
				return $this->test->posts()[ $post_id ] ?? null;
			}

			public function unscheduled(): array {
				return $this->test->drafts;
			}
		};
		$series = new class( $this ) extends SeriesSource {
			public function __construct( private SwapCheckTest $test ) {}

			public function parts( int $term_id ): array {
				return $this->test->parts_of( $term_id );
			}

			public function cadence( int $term_id ): ?array {
				return $this->test->cadence_of();
			}

			public function lane_exception( int $term_id ): bool {
				return $this->test->exception_of( $term_id );
			}

			public function rest_base(): string {
				return 'masthead_series';
			}
		};
		return new SwapCheck( $reader, $series, new Scoring(), new Reasons( new Clock() ), $stats ?? new NullStats(), new Clock() );
	}

	public function posts(): array {
		return $this->posts;
	}

	public function parts_of( int $term_id ): array {
		return $this->parts[ $term_id ] ?? array();
	}

	public function cadence_of(): ?array {
		return $this->cadence;
	}

	public function exception_of( int $term_id ): bool {
		return $this->exceptions[ $term_id ] ?? false;
	}

	private function types( array $findings ): array {
		return array_map( static fn( $f ) => $f['type'] . ':' . $f['post_id'], $findings );
	}

	public function test_post_in_its_lane_has_no_finding(): void {
		$this->post( 12177, 'Nineteen Years', '2026-11-11', 'technology' );
		$this->post( 10194, 'Embeddings', '2026-11-12', 'technology' );
		$this->assertSame( array(), $this->check()->run() );
	}

	public function test_lane_mismatch_offers_nearest_fitting_date(): void {
		$this->post( 20, 'A business post', '2026-11-17', 'business' );
		$findings = $this->check()->run();
		$this->assertSame( array( 'lane_mismatch:20' ), $this->types( $findings ) );
		$finding = $findings[0];
		$this->assertSame( 'lane_mismatch:20', $finding['key'] );
		$this->assertSame( 'review', $finding['severity'] );
		$this->assertSame( 'A business post is on a Tuesday, which your rhythm gives to Technical series.', $finding['why'] );
		$this->assertSame(
			array(
				array(
					'label'   => 'Move to Monday: 2026-11-16',
					'ability' => 'masthead/reschedule',
					'input'   => array(
						'post_id' => 20,
						'date'    => '2026-11-16',
					),
				),
			),
			$finding['options']
		);
		$this->assertArrayNotHasKey( 'numbers', $finding );
	}

	public function test_lane_mismatch_without_target_offers_no_move(): void {
		$this->post( 21, 'Opinion piece', '2026-11-17', 'opinion' );
		$finding = $this->check()->run()[0];
		$this->assertSame( 'lane_mismatch', $finding['type'] );
		$this->assertSame( array(), $finding['options'] );
	}

	public function test_series_option_lists_every_remaining_part_and_carries_also_calls(): void {
		$this->part( 12155, 1, '2026-11-09', 'publish' );
		$this->post( 12156, 'Part 2', '2026-12-14', 'writing', array( 'part' => 2 ) );
		$this->post( 12157, 'Part 3', '2027-01-11', 'writing', array( 'part' => 3 ) );
		$this->post( 12158, 'Part 4', '2027-02-08', 'writing', array( 'part' => 4 ) );
		$this->part( 12156, 2, '2026-12-14' );
		$this->part( 12157, 3, '2027-01-11' );
		$this->part( 12158, 4, '2027-02-08' );
		$this->cadence = null;
		$findings      = $this->check()->run();
		$this->assertSame( array( 'lane_mismatch:12156', 'lane_mismatch:12157', 'lane_mismatch:12158' ), $this->types( $findings ), 'parts 3 and 4 lie beyond the lookahead but are examined' );

		$first = $findings[0]['options'][0];
		$this->assertSame( 'Move to Saturdays: 2026-12-12, 2027-01-09, 2027-02-06', $first['label'] );
		$this->assertSame(
			array(
				'post_id' => 12156,
				'date'    => '2026-12-12',
			),
			$first['input']
		);
		$this->assertSame(
			array(
				array(
					'ability' => 'masthead/reschedule',
					'input'   => array(
						'post_id' => 12157,
						'date'    => '2027-01-09',
					),
				),
				array(
					'ability' => 'masthead/reschedule',
					'input'   => array(
						'post_id' => 12158,
						'date'    => '2027-02-06',
					),
				),
			),
			$first['also']
		);
		$second = $findings[1]['options'][0];
		$this->assertSame(
			array(
				'post_id' => 12157,
				'date' => '2027-01-09',
			),
			$second['input'] 
		);
		$this->assertCount( 2, $second['also'] );

		$keep = $findings[0]['options'][1];
		$this->assertSame( 'Keep Mondays and mark this series as a lane exception', $keep['label'] );
		$this->assertNull( $keep['ability'] );
		$this->assertSame(
			array(
				'path'   => '/wp/v2/masthead_series/3',
				'method' => 'POST',
				'data'   => array( 'meta' => array( 'masthead_lane_exception' => true ) ),
			),
			$keep['input']['rest']
		);
	}

	public function test_lane_exception_suppresses_lane_mismatch(): void {
		$this->part( 12155, 1, '2026-11-09', 'publish' );
		$this->post( 12156, 'Part 2', '2026-12-14', 'writing', array( 'part' => 2 ) );
		$this->part( 12156, 2, '2026-12-14' );
		$this->exceptions[3] = true;
		$this->assertSame( array( 'cadence_break:12156' ), $this->types( $this->check()->run( '2026-11-10', '2027-01-08' ) ) );
	}

	public function test_cadence_break_reports_expected_date(): void {
		$this->part( 12155, 1, '2026-11-09', 'publish' );
		$this->post( 12156, 'Part 2', '2026-12-14', 'writing', array( 'part' => 2 ) );
		$this->part( 12156, 2, '2026-12-14' );
		$this->exceptions[3] = true;
		$finding             = $this->check()->run()[0];
		$this->assertSame( 'cadence_break', $finding['type'] );
		$this->assertSame( 'Part 2 is on Dec 14; the series cadence (every 4 weeks) expects Dec 7.', $finding['why'] );
		$this->assertSame( 'Move to 2026-12-07', $finding['options'][0]['label'] );
		$this->assertSame(
			array(
				'post_id' => 12156,
				'date'    => '2026-12-07',
			),
			$finding['options'][0]['input']
		);
		$this->part( 12157, 3, '2027-01-11' );
		$this->post( 12157, 'Part 3', '2027-01-11', 'writing', array( 'part' => 3 ) );
		$this->assertNotContains( 'cadence_break:12157', $this->types( $this->check()->run( '2026-11-10', '2027-01-31' ) ), 'part 3 follows part 2 by four weeks' );
	}

	public function test_series_parts_beyond_lookahead_are_examined_when_one_part_is_inside(): void {
		$this->post( 12157, 'Part 3', '2027-01-11', 'writing', array( 'part' => 3 ) );
		$this->part( 12157, 3, '2027-01-11' );
		$this->assertSame( array(), $this->check()->run(), 'a part outside the range with no part inside is not examined' );
		$this->post( 12156, 'Part 2', '2026-12-14', 'writing', array( 'part' => 2 ) );
		$this->part( 12156, 2, '2026-12-14' );
		$this->cadence = null;
		$this->assertContains( 'lane_mismatch:12157', $this->types( $this->check()->run() ) );
	}

	public function test_findings_are_ordered_by_date_post_and_type(): void {
		$this->post( 31, 'Later business', '2026-11-24', 'business' );
		$this->post( 30, 'Earlier business', '2026-11-17', 'business' );
		$this->post( 29, 'Same day business', '2026-11-17', 'business' );
		$findings = $this->check()->run();
		$this->assertSame( array( 'lane_mismatch:29', 'collision:29', 'lane_mismatch:30', 'collision:30', 'lane_mismatch:31' ), $this->types( $findings ), 'date, then post, then type order' );
		$this->assertSame( SwapCheck::TYPES, array( 'lane_mismatch', 'collision', 'cadence_break', 'better_candidate' ) );
	}

	private function draft( int $id, string $title, string $primary ): array {
		$draft = array(
			'id'               => $id,
			'title'            => $title,
			'status'           => 'draft',
			'date'             => null,
			'categories'       => array( $primary ),
			'primary_category' => $primary,
		);
		$this->drafts[ $id ] = $draft;
		return $draft;
	}

	private function stats( ?array $by_category, ?array $by_weekday, bool $available = true ): StatsSource {
		return new class( $by_category, $by_weekday, $available ) implements StatsSource {
			public function __construct( private ?array $by_category, private ?array $by_weekday, private bool $available ) {}

			public function available(): bool {
				return $this->available;
			}

			public function views_by_category( int $days ): ?array {
				return $this->by_category;
			}

			public function views_by_weekday( int $days ): ?array {
				return $this->by_weekday;
			}

			public function top_posts( int $days, int $limit ): ?array {
				return null;
			}

			public function post_views( int $post_id, int $days ): ?int {
				return null;
			}

			public function fetched_at(): ?string {
				return '2026-11-09T13:00:00-08:00';
			}
		};
	}

	public function test_two_posts_on_one_date_collide(): void {
		$this->post( 40, 'First', '2026-11-18', 'writing' );
		$this->post( 41, 'Second', '2026-11-18', 'technology' );
		$collisions = array_values( array_filter( $this->check()->run(), static fn( $f ) => 'collision' === $f['type'] ) );
		$this->assertSame( array( 'collision:40', 'collision:41' ), array_column( $collisions, 'key' ) );
		$this->assertSame( 'First shares Nov 18 with Second.', $collisions[0]['why'] );
		$this->assertSame( array(), $collisions[0]['options'] );
		$this->assertSame( 'review', $collisions[0]['severity'] );
	}

	public function test_exempt_category_does_not_collide(): void {
		$this->post( 40, 'Build log', '2026-11-20', 'journal' );
		$this->post( 41, 'Other', '2026-11-20', 'journal' );
		$types = array_column( $this->check()->run(), 'type' );
		$this->assertNotContains( 'collision', $types );
		$this->post( 42, 'Third', '2026-11-20', 'technology' );
		$this->assertNotContains( 'collision', array_column( $this->check()->run(), 'type' ), 'one exempt post exempts the date' );
		$this->config = array( 'swap.collision_exempt_categories' => array() );
		$this->assertContains( 'collision', array_column( $this->check()->run(), 'type' ) );
	}

	public function test_better_candidate_needs_the_minimum_delta(): void {
		// Tuesday 2026-11-17 is a technology day. A business post there scores 0.0; a technology draft scores 1.0.
		$this->post( 50, 'Business on technology day', '2026-11-17', 'business' );
		$this->draft( 60, 'Technology draft', 'technology' );
		$findings = array_values( array_filter( $this->check()->run(), static fn( $f ) => 'better_candidate' === $f['type'] ) );
		$this->assertCount( 1, $findings );
		$this->assertSame( 'info', $findings[0]['severity'] );
		$this->assertSame( 'Technology draft fits Tuesday\'s Technical series lane better than Business on technology day.', $findings[0]['why'] );
		$this->assertSame( 'Put Technology draft on 2026-11-17', $findings[0]['options'][0]['label'] );
		$this->assertSame(
			array(
				'post_id' => 60,
				'date' => '2026-11-17',
			),
			$findings[0]['options'][0]['input'] 
		);
		$this->assertArrayNotHasKey( 'also', $findings[0]['options'][0] );

		$this->drafts = array();
		$this->draft( 61, 'Half fit', 'business' );
		$this->drafts[61]['categories'] = array( 'business', 'technology' );
		$this->post( 51, 'Faith', '2026-11-24', 'faith' );
		$this->config = array( 'swap.min_score_delta' => 0.75 );
		$this->assertNotContains( 'better_candidate', array_column( $this->check()->run(), 'type' ), 'a half-fit draft does not beat zero by 0.75' );
	}

	public function test_better_candidate_picks_highest_score_then_lowest_id(): void {
		$this->post( 50, 'Wrong day', '2026-11-17', 'business' );
		$this->draft( 70, 'Technology B', 'technology' );
		$this->draft( 62, 'Technology A', 'technology' );
		$this->draft( 63, 'Partial', 'faith' );
		$this->drafts[63]['categories'] = array( 'faith', 'technology' );
		$finding = array_values( array_filter( $this->check()->run(), static fn( $f ) => 'better_candidate' === $f['type'] ) )[0];
		$this->assertSame( 62, $finding['options'][0]['input']['post_id'] );
	}

	public function test_candidate_outside_the_window_is_ignored(): void {
		$this->post( 50, 'Wrong day', '2026-11-17', 'business' );
		$this->post( 52, 'Technology post', '2026-11-24', 'technology' );
		$types = array_column( $this->check()->run(), 'type', 'post_id' );
		$this->assertNotContains( 'better_candidate', $types, 'seven days away is outside the three day window' );
		$this->post( 53, 'Close technology post', '2026-11-19', 'technology' );
		$better = array_values( array_filter( $this->check()->run(), static fn( $f ) => 'better_candidate' === $f['type'] && 50 === $f['post_id'] ) );
		$this->assertCount( 1, $better );
		$this->assertSame( 'Swap dates with Close technology post', $better[0]['options'][0]['label'] );
	}

	public function test_swap_option_carries_the_reverse_move_in_also(): void {
		$this->post( 50, 'Wrong day', '2026-11-17', 'business' );
		$this->post( 53, 'Close technology post', '2026-11-19', 'technology' );
		$option = array_values( array_filter( $this->check()->run(), static fn( $f ) => 'better_candidate:50' === $f['key'] ) )[0]['options'][0];
		$this->assertSame(
			array(
				'post_id' => 50,
				'date' => '2026-11-19',
			),
			$option['input'] 
		);
		$this->assertSame(
			array(
				array(
					'ability' => 'masthead/reschedule',
					'input'   => array(
						'post_id' => 53,
						'date'    => '2026-11-17',
					),
				),
			),
			$option['also']
		);
	}

	public function test_numbers_present_only_with_available_stats(): void {
		$this->post( 50, 'Wrong day', '2026-11-17', 'business' );
		$this->draft( 60, 'Technology draft', 'technology' );
		$without = array_values( array_filter( $this->check()->run(), static fn( $f ) => 'better_candidate' === $f['type'] ) )[0];
		$this->assertArrayNotHasKey( 'numbers', $without );
		$unavailable = array_values( array_filter( $this->check( $this->stats( array( 'technology' => 9 ), array( 2 => 5.0 ), false ) )->run(), static fn( $f ) => 'better_candidate' === $f['type'] ) )[0];
		$this->assertArrayNotHasKey( 'numbers', $unavailable );

		$with = array_values(
			array_filter(
				$this->check(
					$this->stats(
						array(
							'technology' => 400,
							'business' => 100,
						),
						array(
							2 => 30.0,
							1 => 60.0,
						) 
					) 
				)->run(),
				static fn( $f ) => 'better_candidate' === $f['type'] 
			) 
		)[0];
		$this->assertSame(
			array(
				'source'     => 'Jetpack Stats',
				'fetched_at' => '2026-11-09T13:00:00-08:00',
				'values'     => array(
					'scheduled_category_views' => 100,
					'candidate_category_views' => 400,
					'weekday_avg_views'        => 30.0,
				),
			),
			$with['numbers']
		);
		$this->assertSame( 0, preg_match( '/\d/', $with['why'] ), 'the sentence states no number' );
	}

	public function test_delta_and_window_come_from_config(): void {
		$this->post( 50, 'Wrong day', '2026-11-17', 'business' );
		$this->post( 52, 'Technology post', '2026-11-24', 'technology' );
		$this->config = array( 'swap.candidate_window_days' => 7 );
		$this->assertContains( 'better_candidate:50', array_column( $this->check()->run(), 'key' ) );
		$this->config = array(
			'swap.candidate_window_days' => 7,
			'swap.min_score_delta'       => 1.5,
		);
		$this->assertNotContains( 'better_candidate:50', array_column( $this->check()->run(), 'key' ) );
	}
}
