<?php
/**
 * Factory tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Stats;

use Masthead\Stats\Factory;
use Masthead\Stats\FixtureStats;
use Masthead\Stats\JetpackStats;
use Masthead\Stats\NullStats;
use PHPUnit\Framework\TestCase;

/**
 * Factory tests.
 */
final class FactoryTest extends TestCase {

	private function fixture(): string {
		return dirname( __DIR__, 3 ) . '/docs/fixtures/stats-sample.json';
	}

	public function test_production_never_returns_fixture_stats_even_with_a_readable_fixture(): void {
		$this->assertInstanceOf( NullStats::class, Factory::make( 'production', $this->fixture() ) );
	}

	public function test_staging_and_development_never_return_fixture_stats(): void {
		foreach ( array( 'staging', 'development', 'Local', '' ) as $environment ) {
			$this->assertInstanceOf( NullStats::class, Factory::make( $environment, $this->fixture() ), $environment );
		}
	}

	public function test_local_with_readable_fixture_returns_fixture_stats(): void {
		$source = Factory::make( 'local', $this->fixture() );
		$this->assertInstanceOf( FixtureStats::class, $source );
		$this->assertTrue( $source->available() );
	}

	public function test_local_without_fixture_returns_null_stats(): void {
		$this->assertInstanceOf( NullStats::class, Factory::make( 'local', '/nonexistent/stats.json' ) );
		$this->assertInstanceOf( NullStats::class, Factory::make( 'local', dirname( __DIR__ ) ) );
		$this->assertInstanceOf( NullStats::class, Factory::make( 'local', '' ) );
	}

	public function test_production_with_usable_jetpack_returns_jetpack_stats(): void {
		$this->assertInstanceOf( JetpackStats::class, Factory::make( 'production', null, true ) );
		$this->assertInstanceOf( NullStats::class, Factory::make( 'production', null, false ) );
		$this->assertInstanceOf( FixtureStats::class, Factory::make( 'local', $this->fixture(), true ), 'the fixture branch is first, local only' );
		$this->assertInstanceOf( JetpackStats::class, Factory::make( 'local', '/nonexistent/stats.json', true ) );
	}
}
