<?php
/**
 * JetpackSocial integration tests.
 *
 * @package Masthead
 */

namespace Masthead\Tests\Integration\Social;

use Masthead\Social\Factory;
use Masthead\Social\JetpackSocial;
use Masthead\Social\NullShare;
use Masthead\Tests\Integration\TestCase;

/**
 * JetpackSocial integration tests.
 */
final class JetpackSocialTest extends TestCase {

	public function test_unconnected_jetpack_yields_null_share_from_the_factory(): void {
		$this->assertTrue( class_exists( 'Jetpack' ) );
		$this->assertFalse( JetpackSocial::usable() );
		$source = Factory::make();
		$this->assertInstanceOf( NullShare::class, $source );
		$this->assertFalse( $source->available() );
		$this->assertSame( array(), $source->connections() );
		$this->assertSame( 'unavailable', $source->share_now( 1, 'x', array( 1 ) )['status'] );
	}
}
