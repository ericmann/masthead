<?php
/**
 * Lanes tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Plan;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Masthead\Plan\Lanes;
use Masthead\Tests\Unit\TestCase;

/**
 * Lanes tests.
 */
final class LanesTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_option' )->justReturn( false );
	}

	public function test_2026_11_18_is_the_standalone_lane(): void {
		$lane = Lanes::for_date( '2026-11-18' );
		$this->assertSame( 'standalone', $lane['slug'] );
		$this->assertSame( 'Standalone', $lane['label'] );
		$this->assertSame( array(), $lane['categories'] );
		$this->assertSame( '08:00', $lane['time'] );
		$this->assertSame( 3, $lane['weekday'] );
	}

	public function test_filter_can_replace_the_lane(): void {
		Filters\expectApplied( 'masthead_lane_for_date' )->once()->andReturnUsing(
			static function ( $lane, $ymd ) {
				$lane['slug'] = 'special-' . $ymd;
				return $lane;
			}
		);
		$this->assertSame( 'special-2026-11-18', Lanes::for_date( '2026-11-18' )['slug'] );
	}

	public function test_fit_is_one_for_primary_match_and_for_empty_lane(): void {
		$lane = array( 'categories' => array( 'business' ) );
		$this->assertSame( 1.0, Lanes::fit( 'business', array( 'business', 'faith' ), $lane ) );
		$this->assertSame( 1.0, Lanes::fit( 'faith', array( 'faith' ), array( 'categories' => array() ) ) );
		$this->assertSame( 1.0, Lanes::fit( null, array(), array( 'categories' => array() ) ) );
	}

	public function test_fit_is_half_for_secondary_match(): void {
		$lane = array( 'categories' => array( 'business' ) );
		$this->assertSame( 0.5, Lanes::fit( 'faith', array( 'faith', 'business' ), $lane ) );
	}

	public function test_fit_is_zero_without_match(): void {
		$lane = array( 'categories' => array( 'business' ) );
		$this->assertSame( 0.0, Lanes::fit( 'writing', array( 'writing' ), $lane ) );
		$this->assertSame( 0.0, Lanes::fit( null, array(), $lane ) );
	}

	public function test_nearest_date_for_writing_from_monday_2026_12_14_is_saturday_2026_12_12(): void {
		$this->assertSame( '2026-12-12', Lanes::nearest_date_for_category( '2026-12-14', 'writing', 3 ) );
		$this->assertNull( Lanes::nearest_date_for_category( '2026-12-14', 'writing', 1 ) );
	}

	public function test_nearest_date_prefers_earlier_on_tie(): void {
		// Wednesday 2026-11-18: Tuesday 17th and Thursday 19th both list technology; the earlier wins.
		$this->assertSame( '2026-11-17', Lanes::nearest_date_for_category( '2026-11-18', 'technology', 3 ) );
	}

	public function test_nearest_date_ignores_empty_category_lanes(): void {
		// Wednesday is the empty-category lane; it is never a target.
		$this->assertNull( Lanes::nearest_date_for_category( '2026-11-17', 'nonexistent', 3 ) );
		$this->assertSame( '2026-11-19', Lanes::nearest_date_for_category( '2026-11-17', 'security', 3 ) );
	}
}
