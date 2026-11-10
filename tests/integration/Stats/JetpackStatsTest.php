<?php
/**
 * JetpackStats integration tests.
 *
 * @package Masthead
 */

namespace Masthead\Tests\Integration\Stats;

use Masthead\Stats\Factory;
use Masthead\Stats\JetpackStats;
use Masthead\Stats\NullStats;
use Masthead\Tests\Integration\TestCase;

/**
 * JetpackStats integration tests.
 */
final class JetpackStatsTest extends TestCase {

	public function test_unconnected_jetpack_is_not_usable_and_factory_returns_null_stats(): void {
		$this->assertTrue( class_exists( 'Automattic\Jetpack\Stats\WPCOM_Stats' ), 'Jetpack is loaded' );
		$this->assertFalse( JetpackStats::usable() );
		$source = Factory::make( 'production', null );
		$this->assertInstanceOf( NullStats::class, $source );
		$this->assertFalse( $source->available() );
		$this->assertNull( $source->top_posts( 30, 10 ) );
	}
}
