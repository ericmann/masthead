<?php
/**
 * NullStats tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Stats;

use Masthead\Stats\NullStats;
use PHPUnit\Framework\TestCase;

/**
 * NullStats tests.
 */
final class NullStatsTest extends TestCase {

	public function test_is_unavailable_and_returns_null_everywhere(): void {
		$stats = new NullStats();
		$this->assertFalse( $stats->available() );
		$this->assertNull( $stats->views_by_category( 30 ) );
		$this->assertNull( $stats->views_by_weekday( 30 ) );
		$this->assertNull( $stats->top_posts( 30, 10 ) );
		$this->assertNull( $stats->post_views( 12155, 30 ) );
		$this->assertNull( $stats->fetched_at() );
	}
}
