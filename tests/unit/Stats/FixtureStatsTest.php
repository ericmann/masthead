<?php
/**
 * FixtureStats tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Stats;

use Masthead\Stats\FixtureStats;
use Masthead\Tests\Unit\TestCase;

/**
 * FixtureStats tests.
 */
final class FixtureStatsTest extends TestCase {

	private function path(): string {
		return dirname( __DIR__, 3 ) . '/docs/fixtures/stats-sample.json';
	}

	public function test_reads_sample_fixture(): void {
		$primary = static fn( int $id ) => array(
			12155 => 'writing',
			10193 => 'technology',
			10192 => 'technology',
			10191 => 'technology',
		)[ $id ] ?? null;
		$stats   = new FixtureStats( $this->path(), $primary );
		$this->assertTrue( $stats->available() );
		$this->assertSame( '2026-11-09T13:00:00-08:00', $stats->fetched_at() );
		$this->assertSame(
			array(
				'technology' => 325,
				'writing'    => 210,
			),
			$stats->views_by_category( 30 )
		);
		$this->assertSame( 52.0, $stats->views_by_weekday( 30 )[3] );
		$this->assertSame(
			array(
				12155 => 210,
				10193 => 160,
			),
			$stats->top_posts( 30, 2 )
		);
		$this->assertSame( 95, $stats->post_views( 10192, 30 ) );
		$this->assertNull( $stats->post_views( 99999, 30 ) );
	}

	public function test_unreadable_file_is_unavailable(): void {
		$stats = new FixtureStats( '/nonexistent/stats.json', static fn() => null );
		$this->assertFalse( $stats->available() );
		$this->assertNull( $stats->views_by_category( 30 ) );
		$this->assertNull( $stats->views_by_weekday( 30 ) );
		$this->assertNull( $stats->top_posts( 30, 10 ) );
		$this->assertNull( $stats->post_views( 1, 30 ) );
		$this->assertNull( $stats->fetched_at() );
	}
}
