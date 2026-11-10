<?php
/**
 * Settings integration tests.
 *
 * @package Masthead
 */

namespace Masthead\Tests\Integration\Admin;

use Masthead\Config;
use Masthead\Plan\Rhythm;
use Masthead\Tests\Integration\TestCase;

/**
 * Settings integration tests.
 */
final class SettingsTest extends TestCase {

	private function post_settings( array $body ): \WP_REST_Response {
		do_action( 'rest_api_init' );
		$request = new \WP_REST_Request( 'POST', '/wp/v2/settings' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $body ) );
		return rest_do_request( $request );
	}

	public function test_admin_saves_rhythm_through_rest_settings(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$rhythm = Rhythm::DEFAULTS;
		$rhythm[5]['time'] = '09:15';
		$rhythm[5]['label'] = ' Journal day ';
		$response = $this->post_settings( array( 'masthead_rhythm' => $rhythm ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( '09:15', Rhythm::get()[5]['time'] );
		$this->assertSame( 'Journal day', Rhythm::get()[5]['label'] );
		$this->assertSame( '09:15', $response->get_data()['masthead_rhythm']['5']['time'] );
	}

	public function test_editor_cannot_save_settings(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$response = $this->post_settings( array( 'masthead_settings' => array( 'desk' => array( 'horizon_days' => 30 ) ) ) );
		$this->assertContains( $response->get_status(), array( 401, 403 ) );
		$this->assertFalse( get_option( 'masthead_settings' ) );
	}

	public function test_saved_desk_override_changes_config_get(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertSame( 21, Config::get( 'desk.horizon_days' ) );
		$response = $this->post_settings( array( 'masthead_settings' => array( 'desk' => array( 'horizon_days' => 30 ) ) ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 30, Config::get( 'desk.horizon_days' ) );
		$this->assertSame( 14, Config::get( 'desk.ghost_days' ) );
	}

	public function test_non_settable_key_in_request_is_ignored(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		// Core's settings endpoint forbids additional properties on an object setting, so the request is refused outright.
		$response = $this->post_settings(
			array(
				'masthead_settings' => array(
					'desk'     => array( 'gap_days' => 60 ),
					'calendar' => array( 'max_posts_per_range' => 5 ),
				),
			)
		);
		$this->assertSame( 400, $response->get_status() );
		$this->assertFalse( get_option( 'masthead_settings' ) );

		// Anything that reaches the option another way is cut down by the sanitize callback.
		( new \Masthead\Admin\SettingsPage( new \Masthead\Admin\Assets( new \Masthead\Support\Clock(), dirname( __DIR__, 3 ) . '/masthead.php' ) ) )->register_settings();
		update_option(
			'masthead_settings',
			array(
				'desk'     => array( 'gap_days' => 60 ),
				'calendar' => array( 'max_posts_per_range' => 5 ),
			)
		);
		$this->assertSame( array( 'desk' => array( 'gap_days' => 60 ) ), get_option( 'masthead_settings' ) );
		$this->assertSame( 500, Config::get( 'calendar.max_posts_per_range' ) );
	}

	public function test_settings_assets_load_only_on_the_settings_screen(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'dashboard' );
		do_action( 'admin_menu' );
		wp_dequeue_script( 'masthead' );
		wp_deregister_script( 'masthead' );

		do_action( 'admin_enqueue_scripts', 'options-general.php' );
		$this->assertFalse( wp_script_is( 'masthead', 'enqueued' ) );

		do_action( 'admin_enqueue_scripts', get_plugin_page_hookname( 'masthead-settings', 'options-general.php' ) );
		$this->assertTrue( wp_script_is( 'masthead', 'enqueued' ), 'run npm run build first' );

		$page = menu_page_url( 'masthead-settings', false );
		$this->assertStringContainsString( 'options-general.php?page=masthead-settings', $page );
	}
}
