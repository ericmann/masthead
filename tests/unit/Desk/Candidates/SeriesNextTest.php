<?php
/**
 * SeriesNext tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Desk\Candidates;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Masthead\Desk\Candidates\SeriesNext;
use Masthead\Plan\SeriesSource;
use Masthead\Tests\Unit\TestCase;

/**
 * SeriesNext tests.
 */
final class SeriesNextTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_option' )->alias(
			static fn( $name, $fallback = false ) => 'masthead_settings' === $name ? array() : $fallback
		);
		Functions\when( 'get_the_terms' )->justReturn(
			array(
				(object) array(
					'term_id' => 4,
					'slug'    => 'writing',
				),
			)
		);
		Functions\when( 'get_the_title' )->alias( static fn( $id ) => 'Draft title ' . $id );
	}

	/**
	 * Builds a series source double.
	 *
	 * @param array $parts  Parts of the one series.
	 * @param int   $total  Total parts, 0 for unknown.
	 * @param array $cadence Cadence, or null for none.
	 * @return SeriesSource
	 */
	private function series( array $parts, int $total = 4, ?array $cadence = null ): SeriesSource {
		$cadence ??= array(
			'every_weeks' => 4,
			'weekday'     => null,
			'time'        => '06:23',
		);
		return new class( $parts, $total, $cadence ) extends SeriesSource {
			public function __construct( private array $parts, private int $total, private ?array $cadence ) {}

			public function terms(): array {
				return array(
					(object) array(
						'term_id' => 3,
						'name'    => 'The Mantle',
					),
				);
			}

			public function cadence( int $term_id ): ?array {
				return $this->cadence;
			}

			public function parts( int $term_id ): array {
				return $this->parts;
			}

			public function for_post( int $post_id ): ?array {
				return array(
					'term_id' => 3,
					'name'    => 'The Mantle',
					'slug'    => 'the-mantle',
					'part'    => 1,
					'total'   => $this->total > 0 ? $this->total : null,
				);
			}
		};
	}

	private function part( int $post_id, int $part, ?string $date ): array {
		return array(
			'post_id' => $post_id,
			'part'    => $part,
			'status'  => null === $date ? 'draft' : 'future',
			'date'    => $date,
			'dated'   => null !== $date,
		);
	}

	public function test_due_series_yields_next_part_on_expected_date(): void {
		$next       = new SeriesNext( $this->series( array( $this->part( 12155, 1, '2026-11-09 06:23:15' ) ) ) );
		$candidates = $next->for_date( '2026-12-07', array() );
		$this->assertCount( 1, $candidates );
		$array = $candidates[0]->to_array();
		$this->assertSame( 'series:3:2', $array['key'] );
		$this->assertSame( 'series', $array['kind'] );
		$this->assertSame( 'The Mantle: Part 2', $array['title'] );
		$this->assertSame( 1.0, $array['score'] );
		$this->assertSame(
			array(
				'term_id' => 3,
				'part'    => 2,
			),
			$array['series']
		);
		$this->assertSame( 'writing', $array['category'] );
		$this->assertSame( '2026-12-07', $array['data']['expected'] );
		$this->assertSame( 4, $array['data']['every_weeks'] );
		$this->assertSame( 3, $array['sort_id'] );
		$this->assertArrayNotHasKey( 'post_id', $array );
	}

	public function test_date_outside_window_yields_nothing(): void {
		$next = new SeriesNext( $this->series( array( $this->part( 12155, 1, '2026-11-09 06:23:15' ) ) ) );
		$this->assertSame( array(), $next->for_date( '2026-12-11', array() ) );
		$this->assertSame( array(), $next->for_date( '2026-12-03', array() ) );
		$this->assertCount( 1, $next->for_date( '2026-12-10', array() ) );
		$none = new SeriesNext( $this->series( array( $this->part( 12155, 1, '2026-11-09 06:23:15' ) ), 4, null ) );
		$this->assertCount( 1, $none->for_date( '2026-12-07', array() ) );
		$this->assertSame( array(), ( new SeriesNext( $this->series( array(), 4 ) ) )->for_date( '2026-12-07', array() ) );
	}

	public function test_series_with_all_parts_dated_yields_nothing(): void {
		$parts = array(
			$this->part( 12155, 1, '2026-11-09 06:23:15' ),
			$this->part( 12156, 2, '2026-12-14 06:23:23' ),
			$this->part( 12157, 3, '2027-01-11 06:20:00' ),
			$this->part( 12158, 4, '2027-02-08 06:23:33' ),
		);
		$next  = new SeriesNext( $this->series( $parts ) );
		$this->assertSame( array(), $next->for_date( '2027-03-08', array() ) );
	}

	public function test_series_past_its_total_yields_nothing(): void {
		$parts = array( $this->part( 1, 1, '2026-11-09 06:23:15' ), $this->part( 2, 2, '2026-12-07 06:23:15' ) );
		$this->assertSame( array(), ( new SeriesNext( $this->series( $parts, 2 ) ) )->for_date( '2027-01-04', array() ) );
		$this->assertCount( 1, ( new SeriesNext( $this->series( $parts, 3 ) ) )->for_date( '2027-01-04', array() ) );
		$this->assertCount( 1, ( new SeriesNext( $this->series( $parts, 0 ) ) )->for_date( '2027-01-04', array() ), 'unknown total does not block' );
	}

	public function test_undated_draft_for_the_next_part_supplies_post_id(): void {
		$parts = array( $this->part( 12155, 1, '2026-11-09 06:23:15' ), $this->part( 12156, 2, null ) );
		$array = ( new SeriesNext( $this->series( $parts ) ) )->for_date( '2026-12-07', array() )[0]->to_array();
		$this->assertSame( 12156, $array['post_id'] );
		$this->assertSame( 'Draft title 12156', $array['title'] );
		$this->assertSame( 'series:3:2', $array['key'] );
	}

	public function test_score_falls_with_distance(): void {
		$next   = new SeriesNext( $this->series( array( $this->part( 12155, 1, '2026-11-09 06:23:15' ) ) ) );
		$scores = array();
		foreach ( array( '2026-12-07', '2026-12-08', '2026-12-09', '2026-12-10' ) as $date ) {
			$scores[] = $next->for_date( $date, array() )[0]->to_array()['score'];
		}
		$this->assertSame( array( 1.0, 0.75, 0.5, 0.25 ), $scores );
	}

	public function test_window_comes_from_config(): void {
		Filters\expectApplied( 'masthead_config' )->zeroOrMoreTimes()->andReturnUsing(
			static function ( $flat ) {
				$flat['desk.series_window_days'] = 1;
				return $flat;
			}
		);
		$next = new SeriesNext( $this->series( array( $this->part( 12155, 1, '2026-11-09 06:23:15' ) ) ) );
		$this->assertCount( 1, $next->for_date( '2026-12-08', array() ) );
		$this->assertSame( array(), $next->for_date( '2026-12-09', array() ) );
		$this->assertSame( 0.5, $next->for_date( '2026-12-08', array() )[0]->to_array()['score'] );
	}
}
