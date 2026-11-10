<?php
/**
 * Admin page tests.
 *
 * @package Masthead
 */

namespace Masthead\Tests\Integration;

/**
 * Calendar page and asset loading.
 */
final class AdminPagesTest extends TestCase {

	public function test_calendar_page_is_registered_under_posts(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		set_current_screen( 'dashboard' );
		global $submenu, $menu;
		$menu    = array();
		$submenu = array();
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		do_action( 'admin_menu' );
		$slugs = wp_list_pluck( $submenu['edit.php'] ?? array(), 2 );
		$this->assertContains( 'masthead', $slugs );
		$this->assertNotEmpty( menu_page_url( 'masthead', false ) );
	}

	public function test_assets_enqueue_only_on_masthead_screen(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		set_current_screen( 'dashboard' );
		do_action( 'admin_menu' );
		wp_dequeue_script( 'masthead' );
		wp_deregister_script( 'masthead' );

		do_action( 'admin_enqueue_scripts', 'edit.php' );
		$this->assertFalse( wp_script_is( 'masthead', 'enqueued' ) );

		do_action( 'admin_enqueue_scripts', get_plugin_page_hookname( 'masthead', 'edit.php' ) );
		$this->assertTrue( wp_script_is( 'masthead', 'enqueued' ), 'run npm run build first' );
		$this->assertStringContainsString( 'window.mastheadBoot', implode( '', (array) wp_scripts()->get_data( 'masthead', 'before' ) ) );
	}

	public function test_subscriber_cannot_open_calendar_page(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		global $submenu, $menu;
		$menu    = array();
		$submenu = array();
		do_action( 'admin_menu' );
		$slugs = wp_list_pluck( $submenu['edit.php'] ?? array(), 2 );
		$this->assertNotContains( 'masthead', $slugs );
	}
}
