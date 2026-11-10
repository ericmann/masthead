<?php
/**
 * CategoryGap tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Desk\Candidates;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Masthead\Desk\Candidates\CategoryGap;
use Masthead\Support\Clock;
use Masthead\Tests\Unit\TestCase;

/**
 * CategoryGap tests.
 */
final class CategoryGapTest extends TestCase {

	/**
	 * Latest publish date by category term ID, or absent for never published.
	 *
	 * @var array<int,string>
	 */
	private array $last = array();

	protected function setUp(): void {
		parent::setUp();
		$this->last = array();
		Functions\when( 'get_option' )->alias(
			static fn( $name, $fallback = false ) => 'masthead_settings' === $name ? array() : $fallback
		);
		Filters\expectApplied( 'masthead_now' )->zeroOrMoreTimes()->andReturn( '2026-11-09 14:00:00' );
		$terms = array(
			(object) array(
				'term_id' => 8,
				'slug'    => 'technology',
				'name'    => 'Technology',
			),
			(object) array(
				'term_id' => 106,
				'slug'    => 'faith',
				'name'    => 'Faith',
			),
			(object) array(
				'term_id' => 14,
				'slug'    => 'security',
				'name'    => 'Security',
			),
		);
		Functions\when( 'get_terms' )->justReturn( $terms );
		Functions\when( 'get_posts' )->alias(
			function ( $args ) {
				$date = $this->last[ $args['cat'] ] ?? null;
				return null === $date ? array() : array( (object) array( 'post_date' => $date . ' 07:57:00' ) );
			}
		);
	}

	private function gap(): CategoryGap {
		return new CategoryGap( new Clock() );
	}

	private function lane( array $categories ): array {
		return array(
			'slug'       => 's',
			'label'      => 'L',
			'categories' => $categories,
		);
	}

	public function test_never_published_category_is_a_gap_with_score_one(): void {
		$this->last = array(
			8   => '2026-11-09',
			106 => '2026-11-08',
		);
		$gaps       = $this->gap()->gaps();
		$this->assertCount( 1, $gaps );
		$array = $gaps[0]->to_array();
		$this->assertSame( 'security', $array['category'] );
		$this->assertSame( 1.0, $array['score'] );
		$this->assertSame( 'Something under Security', $array['title'] );
		$this->assertNull( $array['data']['last_published'] );
		$this->assertSame( 'Security', $array['data']['category_name'] );
	}

	public function test_category_published_within_the_window_is_not_a_gap(): void {
		// 45 days before 2026-11-09 is 2026-09-25: exactly at the window is not older than it.
		$this->last = array(
			8   => '2026-09-25',
			106 => '2026-11-08',
			14  => '2026-10-01',
		);
		$this->assertSame( array(), $this->gap()->gaps() );
		$this->last[8] = '2026-09-24';
		$this->assertSame( array( 'technology' ), array_map( static fn( $c ) => $c->to_array()['category'], $this->gap()->gaps() ) );
	}

	public function test_older_gap_scores_higher(): void {
		$this->last = array(
			8   => '2026-08-01',
			106 => '2026-09-01',
			14  => '2026-11-01',
		);
		$gaps       = $this->gap()->gaps();
		$this->assertSame( array( 'technology', 'faith' ), array_map( static fn( $c ) => $c->to_array()['category'], $gaps ) );
		$scores = array_map( static fn( $c ) => $c->to_array()['score'], $gaps );
		$this->assertGreaterThan( $scores[1], $scores[0] );
		$this->assertSame( 100 / ( 100 + 45 ), $scores[0] );
		$this->assertLessThan( 1.0, $scores[0] );
	}

	public function test_lane_categories_restrict_the_candidates(): void {
		$candidates = $this->gap()->for_date( '2026-11-17', $this->lane( array( 'faith' ) ) );
		$this->assertSame( array( 'gap:106' ), array_map( static fn( $c ) => $c->to_array()['key'], $candidates ) );
	}

	public function test_empty_lane_considers_every_category(): void {
		$candidates = $this->gap()->for_date( '2026-11-18', $this->lane( array() ) );
		$this->assertCount( 3, $candidates );
		$this->assertSame( array( 'faith', 'security', 'technology' ), array_map( static fn( $c ) => $c->to_array()['category'], $candidates ), 'never published, then by slug' );
	}

	public function test_gap_window_comes_from_config(): void {
		$this->last = array(
			8   => '2026-10-20',
			106 => '2026-11-08',
			14  => '2026-11-08',
		);
		$this->assertSame( array(), $this->gap()->gaps() );
		Filters\expectApplied( 'masthead_config' )->zeroOrMoreTimes()->andReturnUsing(
			static function ( $flat ) {
				$flat['desk.gap_days'] = 7;
				return $flat;
			}
		);
		$gaps = $this->gap()->gaps();
		$this->assertSame( array( 'technology' ), array_map( static fn( $c ) => $c->to_array()['category'], $gaps ) );
		$this->assertSame( 20 / ( 20 + 7 ), $gaps[0]->to_array()['score'] );
	}

	public function test_key_is_gap_colon_term_id(): void {
		$this->assertSame( 'gap:8', $this->gap()->gaps()[2]->to_array()['key'] );
		$this->assertSame( 0, $this->gap()->gaps()[0]->to_array()['sort_id'] );
		$this->assertSame( 'gap', $this->gap()->gaps()[0]->to_array()['kind'] );
	}
}
