<?php
/**
 * Ranker tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Desk;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Masthead\Desk\Candidates\Candidate;
use Masthead\Desk\Ranker;
use Masthead\Tests\Unit\TestCase;

/**
 * Ranker tests.
 */
final class RankerTest extends TestCase {

	/**
	 * Value of desk.max_suggestions for the test.
	 *
	 * @var int
	 */
	private int $max = 10;

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_option' )->alias(
			static fn( $name, $fallback = false ) => 'masthead_settings' === $name ? array() : $fallback
		);
		Filters\expectApplied( 'masthead_config' )->zeroOrMoreTimes()->andReturnUsing(
			function ( $flat ) {
				$flat['desk.max_suggestions'] = $this->max;
				return $flat;
			}
		);
	}

	private function make( string $kind, string $key, float $score, int $id = 0, string $title = '' ): Candidate {
		return Candidate::make(
			$kind,
			$key,
			'' === $title ? $key : $title,
			$score,
			array(
				'sort_id'  => $id,
				'tiebreak' => '' === $title ? $key : $title,
			)
		);
	}

	private function keys( array $candidates ): array {
		return array_map( static fn( $c ) => $c->to_array()['key'], $candidates );
	}

	public function test_orders_by_kind_before_score(): void {
		$ranked = ( new Ranker() )->rank(
			array(
				$this->make( 'idea', 'idea:a', 1.0 ),
				$this->make( 'gap', 'gap:1', 1.0 ),
				$this->make( 'reshare', 'reshare:1', 0.1 ),
				$this->make( 'series', 'series:1:2', 0.1 ),
				$this->make( 'draft', 'draft:1', 0.1 ),
			),
			array()
		);
		$this->assertSame( array( 'draft:1', 'series:1:2', 'reshare:1', 'gap:1', 'idea:a' ), $this->keys( $ranked ) );
	}

	public function test_orders_by_score_within_a_kind(): void {
		$ranked = ( new Ranker() )->rank(
			array(
				$this->make( 'draft', 'draft:1', 0.5, 1 ),
				$this->make( 'draft', 'draft:2', 1.0, 2 ),
				$this->make( 'draft', 'draft:3', 0.75, 3 ),
			),
			array()
		);
		$this->assertSame( array( 'draft:2', 'draft:3', 'draft:1' ), $this->keys( $ranked ) );
	}

	public function test_ties_break_by_id_then_tiebreak(): void {
		$ranked = ( new Ranker() )->rank(
			array(
				$this->make( 'draft', 'draft:9', 1.0, 9, 'Alpha' ),
				$this->make( 'draft', 'draft:2', 1.0, 2, 'Zulu' ),
				$this->make( 'gap', 'gap:b', 1.0, 0, 'Bravo' ),
				$this->make( 'gap', 'gap:a', 1.0, 0, 'Alpha' ),
			),
			array()
		);
		$this->assertSame( array( 'draft:2', 'draft:9', 'gap:a', 'gap:b' ), $this->keys( $ranked ) );
	}

	public function test_gap_candidates_order_never_published_first_then_slug(): void {
		$ranked = ( new Ranker() )->rank(
			array(
				$this->make( 'gap', 'gap:1', 100 / 145, 0, 'technology' ),
				$this->make( 'gap', 'gap:2', 1.0, 0, 'security' ),
				$this->make( 'gap', 'gap:3', 1.0, 0, 'faith' ),
			),
			array()
		);
		$this->assertSame( array( 'gap:3', 'gap:2', 'gap:1' ), $this->keys( $ranked ) );
	}

	public function test_dismissed_keys_are_dropped_before_truncation(): void {
		$this->max = 2;
		$ranked = ( new Ranker() )->rank(
			array(
				$this->make( 'draft', 'draft:1', 1.0, 1 ),
				$this->make( 'draft', 'draft:2', 0.9, 2 ),
				$this->make( 'draft', 'draft:3', 0.8, 3 ),
			),
			array( 'draft:1' )
		);
		$this->assertSame( array( 'draft:2', 'draft:3' ), $this->keys( $ranked ) );
	}

	public function test_truncates_to_max_suggestions_from_config(): void {
		$candidates = array();
		for ( $i = 1; $i <= 6; $i++ ) {
			$candidates[] = $this->make( 'draft', 'draft:' . $i, 1.0, $i );
		}
		$this->assertCount( 6, ( new Ranker() )->rank( $candidates, array() ) );
		$this->max = 3;
		$this->assertSame( array( 'draft:1', 'draft:2', 'draft:3' ), $this->keys( ( new Ranker() )->rank( $candidates, array() ) ) );
	}

	public function test_same_input_in_any_order_gives_the_same_output(): void {
		$base = array(
			$this->make( 'draft', 'draft:7', 0.5, 7, 'Seven' ),
			$this->make( 'series', 'series:1:2', 0.75, 1, 'Series' ),
			$this->make( 'gap', 'gap:1', 1.0, 0, 'faith' ),
			$this->make( 'gap', 'gap:2', 1.0, 0, 'faith' ),
			$this->make( 'idea', 'idea:x', 0.5, 0, 'Idea' ),
		);
		$expected = $this->keys( ( new Ranker() )->rank( $base, array() ) );
		foreach ( $this->permutations( $base ) as $permutation ) {
			$this->assertSame( $expected, $this->keys( ( new Ranker() )->rank( $permutation, array() ) ) );
		}
	}

	/**
	 * Every permutation of a list.
	 *
	 * @param array $items Items.
	 * @return array<int,array>
	 */
	private function permutations( array $items ): array {
		if ( count( $items ) <= 1 ) {
			return array( $items );
		}
		$out = array();
		foreach ( $items as $index => $item ) {
			$rest = $items;
			unset( $rest[ $index ] );
			foreach ( $this->permutations( array_values( $rest ) ) as $tail ) {
				$out[] = array_merge( array( $item ), $tail );
			}
		}
		return $out;
	}
}
