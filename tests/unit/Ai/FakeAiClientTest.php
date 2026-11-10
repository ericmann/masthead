<?php
/**
 * FakeAiClient tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Ai;

use Masthead\Ai\Schemas;
use Masthead\Tests\Support\FakeAiClient;
use Masthead\Tests\Unit\TestCase;

/**
 * FakeAiClient tests.
 */
final class FakeAiClientTest extends TestCase {

	public function test_every_fixture_validates_against_its_schema(): void {
		$fake    = new FakeAiClient();
		$schemas = array(
			Schemas::ideas( array( 'opinion', 'technology', 'security' ), 3 ),
			Schemas::outline(),
			Schemas::share_copy(),
			Schemas::series_proposal( array( 'security' ), 12 ),
		);
		foreach ( $schemas as $schema ) {
			$data = $fake->json( 's', 'u', $schema, 'fast' );
			$this->assertIsArray( $data, $schema['title'] );
			$this->assertTrue( Schemas::validate( $data, $schema ), $schema['title'] );
		}
		$this->assertCount( 3, $fake->json( 's', 'u', $schemas[0], 'fast' )['ideas'] );
		$this->assertCount( 4, $fake->json( 's', 'u', $schemas[1], 'fast' )['outline'] );
		$this->assertCount( 4, $fake->json( 's', 'u', $schemas[3], 'fast' )['parts'] );
	}

	public function test_failure_and_alternative_modes(): void {
		$this->assertTrue( ( new FakeAiClient() )->supported() );
		$this->assertIsString( ( new FakeAiClient() )->text( 's', 'u', 'fast' ) );
		$failing = new FakeAiClient( true );
		$this->assertInstanceOf( \WP_Error::class, $failing->json( 's', 'u', Schemas::outline(), 'fast' ) );
		$this->assertInstanceOf( \WP_Error::class, $failing->text( 's', 'u', 'fast' ) );
		$wrong = ( new FakeAiClient( false, 'outline' ) )->json( 's', 'u', Schemas::share_copy(), 'fast' );
		$this->assertNotTrue( Schemas::validate( $wrong, Schemas::share_copy() ) );
	}

	public function test_reads_the_demo_fixtures_from_another_directory(): void {
		// The demo site answers from demo/site/ai; its ideas are for a Thursday (Technology and Security).
		$fake    = new FakeAiClient( false, null, dirname( __DIR__, 3 ) . '/demo/site/ai/' );
		$schemas = array(
			Schemas::ideas( array( 'technology', 'security' ), 3 ),
			Schemas::outline(),
			Schemas::share_copy(),
			Schemas::series_proposal( array( 'technology', 'business', 'faith', 'journal', 'writing', 'security', 'opinion' ), 12 ),
		);
		foreach ( $schemas as $schema ) {
			$data = $fake->json( 's', 'u', $schema, 'fast' );
			$this->assertIsArray( $data, $schema['title'] );
			$this->assertTrue( Schemas::validate( $data, $schema ), $schema['title'] );
		}
		$this->assertSame( 'Locked Down', $fake->json( 's', 'u', $schemas[3], 'strong' )['name'] );

		$missing = new FakeAiClient( false, null, __DIR__ . '/no-such-directory' );
		$this->assertInstanceOf( \WP_Error::class, $missing->json( 's', 'u', Schemas::outline(), 'fast' ) );
	}
}
