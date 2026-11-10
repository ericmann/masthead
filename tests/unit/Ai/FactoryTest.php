<?php
/**
 * Factory tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Ai;

use Brain\Monkey\Filters;
use Masthead\Ai\Factory;
use Masthead\Ai\WpAiClient;
use Masthead\Tests\Support\FakeAiClient;
use Masthead\Tests\Unit\TestCase;

/**
 * Factory tests.
 */
final class FactoryTest extends TestCase {

	public function test_filter_is_ignored_in_production(): void {
		Filters\expectApplied( 'masthead_ai_client' )->never();
		foreach ( array( 'production', 'staging', 'development', 'Local', '' ) as $environment ) {
			$this->assertInstanceOf( WpAiClient::class, Factory::make( $environment ), $environment );
		}
	}

	public function test_filter_is_honoured_in_local(): void {
		$fake = new FakeAiClient();
		Filters\expectApplied( 'masthead_ai_client' )->once()->andReturn( $fake );
		$this->assertSame( $fake, Factory::make( 'local' ) );
	}

	public function test_filter_returning_a_non_client_is_ignored(): void {
		Filters\expectApplied( 'masthead_ai_client' )->once()->andReturn( 'nope' );
		$this->assertInstanceOf( WpAiClient::class, Factory::make( 'local' ) );
	}
}
