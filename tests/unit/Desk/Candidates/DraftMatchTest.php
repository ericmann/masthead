<?php
/**
 * DraftMatch tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Desk\Candidates;

use Brain\Monkey\Functions;
use Masthead\Desk\Candidates\DraftMatch;
use Masthead\Plan\PlanReader;
use Masthead\Plan\SeriesSource;
use Masthead\Support\Clock;
use Masthead\Tests\Unit\TestCase;

/**
 * DraftMatch tests.
 */
final class DraftMatchTest extends TestCase {

	/**
	 * Builds a reader whose unscheduled() list is fixed.
	 *
	 * @param array $drafts PlanPost-like arrays.
	 * @return PlanReader
	 */
	private function reader( array $drafts ): PlanReader {
		return new class( $drafts ) extends PlanReader {
			public function __construct( private array $drafts ) {}

			public function unscheduled(): array {
				return $this->drafts;
			}
		};
	}

	private function draft( int $id, string $title, array $categories ): array {
		return array(
			'id'               => $id,
			'title'            => $title,
			'categories'       => $categories,
			'primary_category' => $categories[0] ?? null,
			'modified'         => '2026-10-29T21:46:13-07:00',
			'edit_url'         => 'http://example.test/?p=' . $id,
		);
	}

	private function lane( array $categories ): array {
		return array(
			'slug'       => 's',
			'label'      => 'L',
			'categories' => $categories,
		);
	}

	public function test_empty_lane_accepts_every_unscheduled_draft(): void {
		$match      = new DraftMatch( $this->reader( array( $this->draft( 1, 'A', array( 'faith' ) ), $this->draft( 2, 'B', array() ) ) ) );
		$candidates = $match->for_date( '2026-11-18', $this->lane( array() ) );
		$this->assertCount( 2, $candidates );
		foreach ( $candidates as $candidate ) {
			$this->assertSame( 1.0, $candidate->to_array()['score'] );
		}
	}

	public function test_draft_outside_lane_categories_is_excluded(): void {
		$match      = new DraftMatch( $this->reader( array( $this->draft( 1, 'A', array( 'faith' ) ), $this->draft( 2, 'B', array( 'technology' ) ) ) ) );
		$candidates = $match->for_date( '2026-11-17', $this->lane( array( 'technology' ) ) );
		$this->assertCount( 1, $candidates );
		$this->assertSame( 'draft:2', $candidates[0]->to_array()['key'] );
	}

	public function test_primary_match_scores_higher_than_secondary_match(): void {
		$match      = new DraftMatch(
			$this->reader(
				array(
					$this->draft( 1, 'Secondary', array( 'faith', 'technology' ) ),
					$this->draft( 2, 'Primary', array( 'technology', 'faith' ) ),
				)
			)
		);
		$candidates = $match->for_date( '2026-11-17', $this->lane( array( 'technology' ) ) );
		$scores     = array_column( array_map( static fn( $c ) => $c->to_array(), $candidates ), 'score', 'key' );
		$this->assertSame( 0.5, $scores['draft:1'] );
		$this->assertSame( 1.0, $scores['draft:2'] );
	}

	public function test_key_is_draft_colon_post_id(): void {
		$match = new DraftMatch( $this->reader( array( $this->draft( 10178, 'Swarms', array( 'technology' ) ) ) ) );
		$array = $match->for_date( '2026-11-17', $this->lane( array( 'technology' ) ) )[0]->to_array();
		$this->assertSame( 'draft:10178', $array['key'] );
		$this->assertSame( 'draft', $array['kind'] );
		$this->assertSame( 10178, $array['sort_id'] );
		$this->assertSame( 10178, $array['post_id'] );
		$this->assertSame( 'Swarms', $array['tiebreak'] );
		$this->assertSame( 'technology', $array['category'] );
		$this->assertSame( '2026-10-29T21:46:13-07:00', $array['data']['modified'] );
	}
}
