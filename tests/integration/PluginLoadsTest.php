<?php
/**
 * Plugin load tests.
 *
 * @package Masthead
 */

namespace Masthead\Tests\Integration;

use Masthead\Support\Clock;

/**
 * Proves the plugin and environment are what the SPEC says.
 */
final class PluginLoadsTest extends TestCase {

	public function test_plugin_is_loaded_with_expected_version(): void {
		$this->assertTrue( defined( 'Masthead\VERSION' ) );
		$this->assertSame( '1.0.0', \Masthead\VERSION );
		$this->assertTrue( class_exists( \Masthead\Plugin::class ) );
		$this->assertNotFalse( has_action( 'plugins_loaded', array( \Masthead\Plugin::class, 'boot' ) ) );
	}

	public function test_wordpress_and_php_meet_spec_minimums(): void {
		$this->assertTrue( version_compare( get_bloginfo( 'version' ), '7.1', '>=' ), get_bloginfo( 'version' ) );
		$this->assertTrue( version_compare( PHP_VERSION, '8.3', '>=' ), PHP_VERSION );
	}

	public function test_environment_type_is_local(): void {
		$this->assertSame( 'local', wp_get_environment_type() );
	}

	public function test_jetpack_is_loaded(): void {
		$this->assertTrue( class_exists( 'Jetpack' ) || defined( 'JETPACK__VERSION' ) );
	}

	public function test_clock_is_pinned_in_tests(): void {
		$now = ( new Clock() )->now();
		$this->assertSame( '2026-11-09 14:00:00', $now->format( 'Y-m-d H:i:s' ) );
		$this->assertSame( 'America/Los_Angeles', $now->getTimezone()->getName() );
	}
}
