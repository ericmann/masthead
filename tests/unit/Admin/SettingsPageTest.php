<?php
/**
 * SettingsPage tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use Masthead\Admin\Assets;
use Masthead\Admin\SettingsPage;
use Masthead\Support\Clock;
use Masthead\Tests\Unit\TestCase;

/**
 * SettingsPage tests.
 */
final class SettingsPageTest extends TestCase {

	private function page(): array {
		$assets = new Assets( new Clock(), '/tmp/masthead.php' );
		return array( new SettingsPage( $assets ), $assets );
	}

	public function test_page_requires_manage_options(): void {
		list( $page, $assets ) = $this->page();
		Functions\expect( 'add_options_page' )->once()->with( \Mockery::type( 'string' ), 'Calendar', 'manage_options', 'masthead-settings', array( $page, 'render' ) )->andReturn( 'settings_page_masthead-settings' );
		$page->register();
		$this->assertTrue( $assets->is_page_hook( 'settings_page_masthead-settings' ) );
		$this->assertFalse( $assets->is_page_hook( 'posts_page_masthead' ) );
	}

	public function test_registers_both_settings_with_sanitizers_and_rest_schema(): void {
		list( $page ) = $this->page();
		$seen         = array();
		Functions\when( 'register_setting' )->alias(
			static function ( $group, $name, $args ) use ( &$seen ) {
				$seen[ $name ] = array( $group, $args );
			}
		);
		$page->register_settings();

		$this->assertSame( array( 'masthead_rhythm', 'masthead_settings' ), array_keys( $seen ) );
		$rhythm = $seen['masthead_rhythm'];
		$this->assertSame( 'masthead', $rhythm[0] );
		$this->assertSame( array( \Masthead\Plan\Rhythm::class, 'sanitize' ), $rhythm[1]['sanitize_callback'] );
		$properties = $rhythm[1]['show_in_rest']['schema']['properties'];
		$this->assertSame( array( '0', '1', '2', '3', '4', '5', '6' ), array_map( 'strval', array_keys( $properties ) ) );
		$this->assertSame( 'string', $properties['3']['properties']['time']['type'] );

		$settings = $seen['masthead_settings'];
		$this->assertSame( array( \Masthead\Config::class, 'sanitize_overrides' ), $settings[1]['sanitize_callback'] );
		$schema = $settings[1]['show_in_rest']['schema']['properties'];
		$this->assertSame( 'integer', $schema['desk']['properties']['horizon_days']['type'] );
		$this->assertSame( 'number', $schema['swap']['properties']['min_score_delta']['type'] );
		$this->assertSame( 'number', $schema['swap']['properties']['weights']['properties']['lane']['type'] );
		$this->assertSame( 'array', $schema['ai']['properties']['models']['properties']['fast']['type'] );
		$this->assertSame( 'boolean', $schema['abilities']['properties']['expose_share']['type'] );
		$this->assertArrayNotHasKey( 'calendar', $schema );
		$this->assertArrayNotHasKey( 'cron', $schema );
	}

	public function test_render_outputs_the_settings_root(): void {
		list( $page ) = $this->page();
		ob_start();
		$page->render();
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( '<div id="masthead-settings-root"></div>', $html );
	}
}
