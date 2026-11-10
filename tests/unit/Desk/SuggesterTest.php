<?php
/**
 * Suggester tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Desk;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Masthead\Desk\Candidates\Candidate;
use Masthead\Desk\Candidates\CategoryGap;
use Masthead\Desk\Candidates\DraftMatch;
use Masthead\Desk\Candidates\Reshare;
use Masthead\Desk\Candidates\SeriesNext;
use Masthead\Desk\Dismissals;
use Masthead\Desk\Ideas;
use Masthead\Desk\Ranker;
use Masthead\Desk\Reasons;
use Masthead\Desk\Suggester;
use Masthead\Plan\PlanReader;
use Masthead\Support\Clock;
use Masthead\Tests\Unit\TestCase;

/**
 * Suggester tests.
 */
final class SuggesterTest extends TestCase {

	/**
	 * Dismissed keys returned for any date.
	 *
	 * @var string[]
	 */
	private array $dismissed = array();

	/**
	 * Posts on the day, as the plan reader reports them.
	 *
	 * @var array
	 */
	private array $day_posts = array();

	protected function setUp(): void {
		parent::setUp();
		$this->dismissed = array();
		$this->day_posts = array();
		Functions\when( 'get_option' )->alias(
			static fn( $name, $fallback = false ) => 'masthead_settings' === $name ? array() : $fallback
		);
		Functions\when( 'wp_date' )->alias(
			static fn( $format, $timestamp, $zone ) => ( new \DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $zone )->format( $format )
		);
		Filters\expectApplied( 'masthead_now' )->zeroOrMoreTimes()->andReturn( '2026-11-09 14:00:00' );
	}

	private function candidate( string $kind, string $key, array $fields = array() ): Candidate {
		return Candidate::make(
			$kind,
			$key,
			$key . ' title',
			1.0,
			array_merge(
				array(
					'sort_id' => 1,
					'data'    => array(
						'modified'      => '2026-10-29T21:46:13-07:00',
						'category_name' => 'Security',
						'expected'      => '2026-11-18',
						'every_weeks'   => 4,
						'part'          => 2,
						'published'     => '2026-02-01',
					),
				),
				$fields
			)
		);
	}

	/**
	 * A suggester whose four sources return fixed candidates.
	 *
	 * @param array $by_kind Candidates by kind.
	 * @return Suggester
	 */
	private function suggester( array $by_kind, ?Ideas $ideas = null ): Suggester {
		$drafts = new class( $by_kind['draft'] ?? array() ) extends DraftMatch {
			public function __construct( private array $items ) {}

			public function for_date( string $date, array $lane ): array {
				return $this->items;
			}
		};
		$series = new class( $by_kind['series'] ?? array() ) extends SeriesNext {
			public function __construct( private array $items ) {}

			public function for_date( string $date, array $lane ): array {
				return $this->items;
			}
		};
		$shares = new class( $by_kind['reshare'] ?? array() ) extends Reshare {
			public function __construct( private array $items ) {}

			public function for_date( string $date, array $lane ): array {
				return $this->items;
			}
		};
		$gaps   = new class( $by_kind['gap'] ?? array() ) extends CategoryGap {
			public function __construct( private array $items ) {}

			public function for_date( string $date, array $lane ): array {
				return $this->items;
			}
		};
		$dismissals = new class( $this->dismissed ) extends Dismissals {
			public function __construct( private array $keys ) {}

			public function for_date( int $user_id, string $date ): array {
				return $this->keys;
			}
		};
		$reader = new class( $this->day_posts ) extends PlanReader {
			public function __construct( private array $posts ) {}

			public function range( string $from, string $to ): array {
				return array(
					'days'        => array(
						array(
							'date' => $from,
							'posts' => $this->posts,
						),
					),
					'unscheduled' => array(),
					'truncated'   => false,
				);
			}
		};
		return new Suggester( $drafts, $series, $shares, $gaps, new Ranker(), new Reasons( new Clock() ), $dismissals, $reader, new Clock(), $ideas );
	}

	private function keys( array $result ): array {
		return array_column( $result['suggestions'], 'key' );
	}

	private function allKinds(): array {
		return array(
			'gap'     => array( $this->candidate( 'gap', 'gap:14', array( 'category' => 'security' ) ) ),
			'reshare' => array( $this->candidate( 'reshare', 'reshare:10191', array( 'post_id' => 10191 ) ) ),
			'series'  => array(
				$this->candidate(
					'series',
					'series:3:2',
					array(
						'series' => array(
							'term_id' => 3,
							'part' => 2,
						),
					) 
				),
			),
			'draft'   => array( $this->candidate( 'draft', 'draft:10178', array( 'post_id' => 10178 ) ) ),
		);
	}

	public function test_day_with_a_post_gets_no_suggestions(): void {
		$this->day_posts = array( array( 'id' => 12177 ) );
		$result          = $this->suggester( $this->allKinds() )->suggest( '2026-11-11', 5 );
		$this->assertSame( array(), $result['suggestions'] );
		$this->assertSame( 'standalone', $result['lane']['slug'] );
	}

	public function test_past_date_gets_no_suggestions(): void {
		$result = $this->suggester( $this->allKinds() )->suggest( '2026-11-08', 5 );
		$this->assertSame( array(), $result['suggestions'] );
		$this->assertNotEmpty( $this->suggester( $this->allKinds() )->suggest( '2026-11-09', 5 )['suggestions'], 'today is not past' );
	}

	public function test_sources_are_ordered_draft_series_reshare_gap(): void {
		$result = $this->suggester( $this->allKinds() )->suggest( '2026-11-18', 5 );
		$this->assertSame( array( 'draft:10178', 'series:3:2', 'reshare:10191' ), $this->keys( $result ), 'max_suggestions of three truncates the gap' );
		$this->assertSame(
			array(
				'used' => false,
				'remaining' => 0,
			),
			$result['ai'] 
		);
		$this->assertSame( '2026-11-18', $result['date'] );
		$this->assertSame( "Wednesday is your Standalone slot. This draft isn't on the calendar; last edited Oct 29.", $result['suggestions'][0]['why'] );
	}

	public function test_dismissed_suggestion_is_absent(): void {
		$this->dismissed = array( 'draft:10178' );
		$result          = $this->suggester( $this->allKinds() )->suggest( '2026-11-18', 5 );
		$this->assertSame( array( 'series:3:2', 'reshare:10191', 'gap:14' ), $this->keys( $result ) );
	}

	public function test_kinds_filter_restricts_sources(): void {
		$result = $this->suggester( $this->allKinds() )->suggest( '2026-11-18', 5, array( 'gap', 'reshare' ) );
		$this->assertSame( array( 'reshare:10191', 'gap:14' ), $this->keys( $result ) );
	}

	public function test_filter_masthead_suggestions_can_change_the_list(): void {
		Filters\expectApplied( 'masthead_suggestions' )->once()->andReturnUsing(
			static function ( $list, $date ) {
				$list[] = array( 'key' => 'extra:' . $date );
				return $list;
			}
		);
		$result = $this->suggester( array( 'gap' => array( $this->candidate( 'gap', 'gap:14' ) ) ) )->suggest( '2026-11-18', 5 );
		$this->assertSame( array( 'gap:14', 'extra:2026-11-18' ), $this->keys( $result ) );
	}

	public function test_actions_per_kind(): void {
		$by_kind = $this->allKinds();
		$by_kind['series'][] = $this->candidate( 'series', 'series:4:1', array( 'sort_id' => 2 ) );
		$result  = $this->suggester( $by_kind )->suggest( '2026-11-18', 5 );
		$actions = array_column( $result['suggestions'], 'actions', 'key' );
		$this->assertSame( array( 'schedule', 'open', 'dismiss' ), $actions['draft:10178'] );
		$this->assertSame( array( 'create_draft', 'dismiss' ), $actions['series:3:2'], 'a series part with no draft' );
		$all = $this->suggester(
			array(
				'series' => array( $this->candidate( 'series', 'series:3:2', array( 'post_id' => 12156 ) ) ),
				'reshare' => $by_kind['reshare'],
				'gap' => $by_kind['gap'],
			) 
		)->suggest( '2026-11-18', 5 );
		$actions = array_column( $all['suggestions'], 'actions', 'key' );
		$this->assertSame( array( 'schedule', 'open', 'dismiss' ), $actions['series:3:2'] );
		$this->assertSame( array( 'share', 'open', 'dismiss' ), $actions['reshare:10191'] );
		$this->assertSame( array( 'create_draft', 'suggest_topics', 'dismiss' ), $actions['gap:14'] );
	}

	public function test_output_is_identical_on_repeat_calls(): void {
		$suggester = $this->suggester( $this->allKinds() );
		$this->assertSame( $suggester->suggest( '2026-11-18', 5 ), $suggester->suggest( '2026-11-18', 5 ) );
		$this->assertSame( '2026-11-30', $suggester->horizon_end() );
	}

	/**
	 * Ideas stub.
	 *
	 * @param array|\WP_Error $result    What ideas() returns.
	 * @param bool            $supported Whether a provider is available.
	 * @return Ideas
	 */
	private function ideas( array|\WP_Error $result, bool $supported = true ): Ideas {
		return new class( $result, $supported ) extends Ideas {
			public int $calls = 0;

			public function __construct( private array|\WP_Error $result, private bool $supported ) {}

			public function status(): array {
				return array(
					'supported' => $this->supported,
					'remaining' => 41,
				);
			}

			public function ideas( string $date, array $lane, array $dismissed ): array|\WP_Error {
				++$this->calls;
				return $this->result;
			}
		};
	}

	public function test_ideas_are_appended_after_deterministic_suggestions(): void {
		$idea   = Candidate::make(
			'idea',
			'idea:abc',
			'An idea',
			0.0,
			array(
				'category' => 'security',
				'data' => array( 'angle' => 'Because.' ),
			) 
		);
		$result = $this->suggester( $this->allKinds(), $this->ideas( array( $idea ) ) )->suggest( '2026-11-18', 5, array(), true );
		$this->assertSame( array( 'draft:10178', 'series:3:2', 'reshare:10191', 'idea:abc' ), $this->keys( $result ) );
		$last = end( $result['suggestions'] );
		$this->assertSame( 'idea', $last['kind'] );
		$this->assertSame( 'Because.', $last['why'] );
		$this->assertSame( array( 'create_draft', 'dismiss' ), $last['actions'] );
		$this->assertSame( 'security', $last['category'] );
		$this->assertSame(
			array(
				'used'      => true,
				'remaining' => 41,
			),
			$result['ai']
		);
	}

	public function test_ai_error_leaves_the_deterministic_list_unchanged(): void {
		$plain  = $this->suggester( $this->allKinds() )->suggest( '2026-11-18', 5 );
		$error  = new \WP_Error( 'masthead_ai_budget', 'Limit.' );
		$result = $this->suggester( $this->allKinds(), $this->ideas( $error ) )->suggest( '2026-11-18', 5, array(), true );
		$this->assertSame( $plain['suggestions'], $result['suggestions'] );
		$this->assertFalse( $result['ai']['used'] );
		$this->assertSame( 'masthead_ai_budget', $result['ai']['error'] );
	}

	public function test_ideas_are_not_requested_without_include_ideas(): void {
		$ideas = $this->ideas( array() );
		$this->suggester( $this->allKinds(), $ideas )->suggest( '2026-11-18', 5 );
		$this->assertSame( 0, $ideas->calls );
		$this->suggester( $this->allKinds(), $ideas )->suggest( '2026-11-18', 5, array( 'gap' ), true );
		$this->assertSame( 0, $ideas->calls );
		$unsupported = $this->ideas( array(), false );
		$result      = $this->suggester( $this->allKinds(), $unsupported )->suggest( '2026-11-18', 5, array(), true );
		$this->assertSame( 0, $unsupported->calls );
		$this->assertFalse( $result['ai']['used'] );
		$this->assertArrayNotHasKey( 'error', $result['ai'] );
	}
}
