<?php
/**
 * Installs the fake AI client. Test code, never packaged. prompt_ai comes from Masthead's own fallback (Ai\Capability).
 *
 * @package Masthead
 */

add_filter(
	'masthead_ai_client',
	static function ( $client ) {
		// Option masthead_e2e_ai = 0 leaves the real client in place, so specs can see the "no provider" state.
		if ( '0' === (string) get_option( 'masthead_e2e_ai' ) ) {
			return $client;
		}
		$file = WP_PLUGIN_DIR . '/masthead/tests/support/FakeAiClient.php';
		if ( ! class_exists( 'Masthead\Tests\Support\FakeAiClient' ) && is_readable( $file ) ) {
			require_once $file;
		}
		// Option masthead_e2e_ai_dir names another fixture directory inside the plugin (the demo site uses demo/site/ai).
		$root      = realpath( WP_PLUGIN_DIR . '/masthead' );
		$relative  = (string) get_option( 'masthead_e2e_ai_dir' );
		$directory = '' === $relative ? false : realpath( WP_PLUGIN_DIR . '/masthead/' . $relative );
		$directory = ( false !== $root && false !== $directory && str_starts_with( $directory, $root . '/' ) && is_dir( $directory ) ) ? $directory : null;
		return class_exists( 'Masthead\Tests\Support\FakeAiClient' ) ? new Masthead\Tests\Support\FakeAiClient( false, null, $directory ) : $client;
	}
);
