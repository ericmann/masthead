<?php
/**
 * Reshare tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Desk\Candidates;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Masthead\Desk\Candidates\Reshare;
use Masthead\Social\NullShare;
use Masthead\Social\ShareSource;
use Masthead\Stats\NullStats;
use Masthead\Stats\StatsSource;
use Masthead\Support\Clock;
use Masthead\Tests\Unit\TestCase;

/**
 * Reshare tests.
 */
final class ReshareTest extends TestCase {

	/**
	 * Post fixtures by ID: status, date, categories, last shared.
	 *
	 * @var array<int,array>
	 */
	private array $posts = array();

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_option' )->alias(
			static fn( $name, $fallback = false ) => 'masthead_settings' === $name ? array() : $fallback
		);
		Filters\expectApplied( 'masthead_now' )->zeroOrMoreTimes()->andReturn( '2026-11-09 14:00:00' );
		$this->posts = array(
			10192 => array( 'publish', '2026-10-01 07:57:00', array( 'technology' ), '' ),
			10191 => array( 'publish', '2026-02-01 07:57:00', array( 'technology' ), '' ),
			12155 => array( 'publish', '2026-01-01 06:23:00', array( 'writing' ), '' ),
			10190 => array( 'publish', '2025-12-01 06:23:00', array( 'technology' ), '2026-09-01T08:00:00-07:00' ),
			10189 => array( 'draft', '2025-12-01 06:23:00', array( 'technology' ), '' ),
		);
		Functions\when( 'get_post' )->alias(
			fn( $id ) => isset( $this->posts[ $id ] )
				? (object) array(
					'ID'          => $id,
					'post_status' => $this->posts[ $id ][0],
					'post_date'   => $this->posts[ $id ][1],
					'post_title'  => 'Post ' . $id,
				)
				: null
		);
		Functions\when( 'get_the_terms' )->alias(
			fn( $id ) => array_map(
				static fn( $slug ) => (object) array(
					'term_id' => strlen( $slug ),
					'slug'    => $slug,
				),
				$this->posts[ $id ][2] ?? array()
			)
		);
		Functions\when( 'get_post_meta' )->alias( fn( $id ) => $this->posts[ $id ][3] ?? '' );
	}

	private function stats( ?array $top, bool $available = true ): StatsSource {
		return new class( $top, $available ) implements StatsSource {
			public function __construct( private ?array $top, private bool $available ) {}

			public function available(): bool {
				return $this->available;
			}

			public function views_by_category( int $days ): ?array {
				return null;
			}

			public function views_by_weekday( int $days ): ?array {
				return null;
			}

			public function top_posts( int $days, int $limit ): ?array {
				return $this->top;
			}

			public function post_views( int $post_id, int $days ): ?int {
				return null;
			}

			public function fetched_at(): ?string {
				return null;
			}
		};
	}

	private function share( bool $available = true ): ShareSource {
		return new class( $available ) implements ShareSource {
			public function __construct( private bool $available ) {}

			public function available(): bool {
				return $this->available;
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

	private function lane( array $categories = array() ): array {
		return array(
			'slug'       => 's',
			'label'      => 'L',
			'categories' => $categories,
		);
	}

	private function keys( array $candidates ): array {
		return array_map( static fn( $c ) => $c->to_array()['key'], $candidates );
	}

	public function test_unavailable_stats_yield_nothing(): void {
		$this->assertSame( array(), ( new Reshare( new NullStats(), $this->share(), new Clock() ) )->for_date( '2026-11-18', $this->lane() ) );
		$this->assertSame( array(), ( new Reshare( $this->stats( null ), $this->share(), new Clock() ) )->for_date( '2026-11-18', $this->lane() ) );
		$this->assertSame( array(), ( new Reshare( $this->stats( array( 10191 => 5 ), false ), $this->share(), new Clock() ) )->for_date( '2026-11-18', $this->lane() ) );
	}

	public function test_unavailable_sharing_yields_nothing(): void {
		$this->assertSame( array(), ( new Reshare( $this->stats( array( 10191 => 5 ) ), new NullShare(), new Clock() ) )->for_date( '2026-11-18', $this->lane() ) );
		$this->assertSame( array(), ( new Reshare( $this->stats( array( 10191 => 5 ) ), $this->share( false ), new Clock() ) )->for_date( '2026-11-18', $this->lane() ) );
	}

	public function test_post_younger_than_min_age_is_excluded(): void {
		$reshare = new Reshare(
			$this->stats(
				array(
					10192 => 9,
					10191 => 5,
				) 
			),
			$this->share(),
			new Clock() 
		);
		// 10192 is 39 days old on 2026-11-09, younger than 90 days.
		$this->assertSame( array( 'reshare:10191' ), $this->keys( $reshare->for_date( '2026-11-18', $this->lane() ) ) );
	}

	private function days_before_today( int $days ): string {
		return ( new \DateTimeImmutable( '2026-11-09' ) )->modify( '-' . $days . ' days' )->format( 'Y-m-d' );
	}

	public function test_post_exactly_min_age_days_old_is_excluded_and_one_day_older_is_included(): void {
		$min_age     = (int) \Masthead\Config::get( 'desk.reshare_min_age_days' );
		$this->posts = array(
			20001 => array( 'publish', $this->days_before_today( $min_age ) . ' 07:00:00', array( 'technology' ), '' ),
			20002 => array( 'publish', $this->days_before_today( $min_age + 1 ) . ' 07:00:00', array( 'technology' ), '' ),
		);
		$reshare     = new Reshare(
			$this->stats(
				array(
					20001 => 9,
					20002 => 5,
				)
			),
			$this->share(),
			new Clock()
		);
		$this->assertSame( array( 'reshare:20002' ), $this->keys( $reshare->for_date( '2026-11-18', $this->lane() ) ) );
	}

	public function test_post_shared_exactly_cooldown_days_ago_is_excluded_and_one_day_earlier_is_included(): void {
		$cooldown    = (int) \Masthead\Config::get( 'desk.reshare_cooldown_days' );
		$this->posts = array(
			20001 => array( 'publish', '2025-01-01 07:00:00', array( 'technology' ), $this->days_before_today( $cooldown ) . 'T08:00:00-07:00' ),
			20002 => array( 'publish', '2025-01-01 07:00:00', array( 'technology' ), $this->days_before_today( $cooldown + 1 ) . 'T08:00:00-07:00' ),
		);
		$reshare     = new Reshare(
			$this->stats(
				array(
					20001 => 9,
					20002 => 5,
				)
			),
			$this->share(),
			new Clock()
		);
		$this->assertSame( array( 'reshare:20002' ), $this->keys( $reshare->for_date( '2026-11-18', $this->lane() ) ) );
	}

	public function test_post_shared_within_cooldown_is_excluded(): void {
		$reshare = new Reshare(
			$this->stats(
				array(
					10190 => 9,
					10191 => 5,
				) 
			),
			$this->share(),
			new Clock() 
		);
		// 10190 was shared 69 days ago, inside the 180-day cooldown.
		$this->assertSame( array( 'reshare:10191' ), $this->keys( $reshare->for_date( '2026-11-18', $this->lane() ) ) );
	}

	public function test_post_outside_lane_categories_is_excluded(): void {
		$reshare = new Reshare(
			$this->stats(
				array(
					12155 => 9,
					10191 => 5,
					10189 => 3,
				) 
			),
			$this->share(),
			new Clock() 
		);
		$this->assertSame( array( 'reshare:10191' ), $this->keys( $reshare->for_date( '2026-11-17', $this->lane( array( 'technology' ) ) ) ), 'drafts and other categories drop out' );
		$this->assertSame( array( 'reshare:12155', 'reshare:10191' ), $this->keys( $reshare->for_date( '2026-11-18', $this->lane() ) ) );
	}

	public function test_score_is_relative_to_the_most_viewed_candidate(): void {
		$reshare    = new Reshare(
			$this->stats(
				array(
					12155 => 200,
					10191 => 50,
				) 
			),
			$this->share(),
			new Clock() 
		);
		$candidates = $reshare->for_date( '2026-11-18', $this->lane() );
		$this->assertSame( array( 1.0, 0.25 ), array_map( static fn( $c ) => $c->to_array()['score'], $candidates ) );
		$array = $candidates[1]->to_array();
		$this->assertSame( 10191, $array['sort_id'] );
		$this->assertSame( 10191, $array['post_id'] );
		$this->assertSame( 'Post 10191', $array['title'] );
		$this->assertSame( '2026-02-01', $array['data']['published'] );
		$this->assertSame( 'technology', $array['category'] );
		$this->assertArrayNotHasKey( 'views', $array['data'] );
	}

	public function test_ages_come_from_config(): void {
		Filters\expectApplied( 'masthead_config' )->zeroOrMoreTimes()->andReturnUsing(
			static function ( $flat ) {
				$flat['desk.reshare_min_age_days']      = 30;
				$flat['desk.reshare_cooldown_days']     = 30;
				return $flat;
			}
		);
		$reshare = new Reshare(
			$this->stats(
				array(
					10192 => 9,
					10190 => 8,
				) 
			),
			$this->share(),
			new Clock() 
		);
		$this->assertSame( array( 'reshare:10192', 'reshare:10190' ), $this->keys( $reshare->for_date( '2026-11-18', $this->lane() ) ) );
	}
}
