<?php
/**
 * Social Factory tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Social;

use Masthead\Social\Factory;
use Masthead\Social\JetpackSocial;
use Masthead\Social\NullShare;
use PHPUnit\Framework\TestCase;

/**
 * Social Factory tests.
 */
final class FactoryTest extends TestCase {

	public function test_returns_null_share_when_jetpack_social_is_not_usable(): void {
		$this->assertInstanceOf( NullShare::class, Factory::make( false ) );
		$this->assertInstanceOf( NullShare::class, Factory::make(), 'Jetpack is not loaded in unit tests' );
		$this->assertInstanceOf( JetpackSocial::class, Factory::make( true ) );
	}
}
