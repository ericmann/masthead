<?php
/**
 * Assets tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Admin;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Masthead\Admin\Assets;
use Masthead\Support\Clock;
use Masthead\Tests\Unit\TestCase;

/**
 * Assets tests.
 */
final class AssetsTest extends TestCase {

	/**
	 * Fixture plugin dir holding a build.
	 *
	 * @var string
	 */
	private string $dir = '';

	protected function setUp(): void {
		parent::setUp();
		$this->dir = dirname( __DIR__ ) . '/fixtures/plugin';
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'wp_timezone_string' )->justReturn( self::ZONE );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'plugins_url' )->alias(
			static fn( $path ) => 'http://example.test/' . $path
		);
		Filters\expectApplied( 'masthead_now' )->zeroOrMoreTimes()->andReturn( '2026-11-09 14:00:00' );
		Filters\expectApplied( 'masthead_config' )->zeroOrMoreTimes()->andReturnFirstArg();
	}

	public function test_enqueue_returns_early_on_foreign_screen(): void {
		Functions\expect( 'wp_enqueue_script' )->never();
		Functions\expect( 'wp_add_inline_script' )->never();
		$assets = new Assets( new Clock(), $this->dir . '/masthead.php' );
		$assets->add_page_hook( 'posts_page_masthead' );
		$assets->enqueue( 'edit.php' );
		$this->assertTrue( true );
	}

	public function test_enqueue_loads_script_and_boot_data_on_registered_screen(): void {
		Functions\expect( 'wp_enqueue_script' )->once()->with( 'masthead', 'http://example.test/build/index.js', array( 'wp-element' ), 'abc', true );
		Functions\expect( 'wp_enqueue_style' )->atLeast()->once();
		Functions\expect( 'wp_set_script_translations' )->once()->with( 'masthead', 'masthead' );
		Functions\expect( 'wp_add_inline_script' )->once()->andReturnUsing(
			function ( $handle, $code, $position ) {
				$this->assertSame( 'masthead', $handle );
				$this->assertSame( 'before', $position );
				$this->assertStringStartsWith( 'window.mastheadBoot = {', $code );
				$this->assertStringContainsString( '"today":"2026-11-09"', $code );
				$this->assertStringContainsString( '"weekStartsOn":0', $code );
				$this->assertStringContainsString( '"timezone":"America\/Los_Angeles"', $code );
				return true;
			}
		);
		$assets = new Assets( new Clock(), $this->dir . '/masthead.php' );
		$assets->add_page_hook( 'posts_page_masthead' );
		$assets->enqueue( 'posts_page_masthead' );
	}

	public function test_boot_data_merges_registered_providers(): void {
		$assets = new Assets( new Clock(), $this->dir . '/masthead.php' );
		$assets->add_boot_data( 'extra', static fn() => array( 'a' => 1 ) );
		$data = $assets->boot_data();
		$this->assertSame( array( 'a' => 1 ), $data['extra'] );
		$this->assertSame( '2026-11-09', $data['today'] );
		$this->assertSame( '0.0.0', $data['version'] );
		$this->assertStringStartsWith( '2026-11-09T14:00:00', $data['now'] );
	}

	public function test_enqueue_does_nothing_when_build_is_missing(): void {
		Functions\expect( 'wp_enqueue_script' )->never();
		$assets = new Assets( new Clock(), '/nonexistent/masthead.php' );
		$assets->add_page_hook( 'posts_page_masthead' );
		$assets->enqueue( 'posts_page_masthead' );
		$this->assertTrue( true );
	}
}
