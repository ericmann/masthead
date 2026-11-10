<?php
/**
 * SeriesSource tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Plan;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Masthead\Plan\SeriesSource;
use Masthead\Tests\Unit\TestCase;

/**
 * SeriesSource tests.
 */
final class SeriesSourceTest extends TestCase {

	public function test_taxonomy_is_series_when_registered(): void {
		Functions\when( 'taxonomy_exists' )->alias( static fn( $name ) => 'series' === $name );
		$this->assertSame( 'series', ( new SeriesSource() )->taxonomy() );
	}

	public function test_taxonomy_falls_back_to_masthead_series(): void {
		Functions\when( 'taxonomy_exists' )->justReturn( false );
		$this->assertSame( 'masthead_series', ( new SeriesSource() )->taxonomy() );
	}

	public function test_filter_value_must_be_a_registered_taxonomy(): void {
		Functions\when( 'taxonomy_exists' )->alias( static fn( $name ) => 'shows' === $name );
		Filters\expectApplied( 'masthead_series_taxonomy' )->once()->andReturn( 'shows' );
		$this->assertSame( 'shows', ( new SeriesSource() )->taxonomy() );
		Filters\expectApplied( 'masthead_series_taxonomy' )->once()->andReturn( 'nope' );
		$this->assertSame( 'masthead_series', ( new SeriesSource() )->taxonomy() );
		Filters\expectApplied( 'masthead_series_taxonomy' )->once()->andReturn( 42 );
		$this->assertSame( 'masthead_series', ( new SeriesSource() )->taxonomy() );
	}

	public function test_meta_names_follow_the_taxonomy(): void {
		Functions\when( 'taxonomy_exists' )->alias( static fn( $name ) => 'series' === $name );
		$ttm = new SeriesSource();
		$this->assertSame( 'ttm_series_part', $ttm->part_meta() );
		$this->assertSame( 'ttm_total_parts', $ttm->total_meta() );

		Functions\when( 'taxonomy_exists' )->justReturn( false );
		$own = new SeriesSource();
		$this->assertSame( 'masthead_series_part', $own->part_meta() );
		$this->assertSame( 'masthead_total_parts', $own->total_meta() );
	}
}
