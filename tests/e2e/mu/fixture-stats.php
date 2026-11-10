<?php
/**
 * Switches e2e runs to fixture stats. Test code, never packaged.
 *
 * SPEC 7 names MASTHEAD_STATS_FIXTURE in .wp-env.json; defining it here keeps the default e2e
 * state "stats unavailable". Turn it on with option masthead_e2e_stats = 1. Option
 * masthead_e2e_stats_file names another stats file inside the plugin (the demo site uses demo/site/stats.json).
 *
 * @package Masthead
 */

if ( ! defined( 'MASTHEAD_STATS_FIXTURE' ) && '1' === (string) get_option( 'masthead_e2e_stats' ) ) {
	$masthead_e2e_root  = realpath( WP_PLUGIN_DIR . '/masthead' );
	$masthead_e2e_other = (string) get_option( 'masthead_e2e_stats_file' );
	$masthead_e2e_other = '' === $masthead_e2e_other ? false : realpath( WP_PLUGIN_DIR . '/masthead/' . $masthead_e2e_other );
	define(
		'MASTHEAD_STATS_FIXTURE',
		( false !== $masthead_e2e_root && false !== $masthead_e2e_other && str_starts_with( $masthead_e2e_other, $masthead_e2e_root . '/' ) && is_file( $masthead_e2e_other ) )
			? $masthead_e2e_other
			: WP_CONTENT_DIR . '/masthead-fixtures/stats-sample.json'
	);
}
