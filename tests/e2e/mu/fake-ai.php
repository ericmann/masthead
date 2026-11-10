<?php
/**
 * Installs the fake AI client and grants prompt_ai to administrators. Test code, never packaged.
 *
 * @package Masthead
 */

add_filter(
	'masthead_ai_client',
	static function ( $client ) {
		$file = WP_PLUGIN_DIR . '/masthead/tests/support/FakeAiClient.php';
		if ( ! class_exists( 'Masthead\Tests\Support\FakeAiClient' ) && is_readable( $file ) ) {
			require_once $file;
		}
		return class_exists( 'Masthead\Tests\Support\FakeAiClient' ) ? new Masthead\Tests\Support\FakeAiClient() : $client;
	}
);

add_filter(
	'user_has_cap',
	static function ( $allcaps ) {
		if ( ! empty( $allcaps['manage_options'] ) ) {
			$allcaps['prompt_ai'] = true;
		}
		return $allcaps;
	}
);
