<?php
/**
 * Integration test bootstrap (wp-env tests-cli).
 *
 * @package Masthead
 */

$masthead_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $masthead_tests_dir ) {
	$masthead_tests_dir = '/wordpress-phpunit';
}

$masthead_root = dirname( __DIR__, 2 );

require_once $masthead_root . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';
require_once $masthead_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () use ( $masthead_root ) {
		if ( file_exists( WP_PLUGIN_DIR . '/jetpack/jetpack.php' ) ) {
			require_once WP_PLUGIN_DIR . '/jetpack/jetpack.php';
		}
		require_once $masthead_root . '/masthead.php';
	}
);

require_once $masthead_tests_dir . '/includes/bootstrap.php';
require_once __DIR__ . '/TestCase.php';
