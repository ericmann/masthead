<?php
/**
 * Switches e2e runs to fixture stats. Test code, never packaged.
 *
 * SPEC 7 names MASTHEAD_STATS_FIXTURE in .wp-env.json; defining it here keeps the default e2e
 * state "stats unavailable". Turn it on with option masthead_e2e_stats = 1.
 *
 * @package Masthead
 */

if ( ! defined( 'MASTHEAD_STATS_FIXTURE' ) && '1' === (string) get_option( 'masthead_e2e_stats' ) ) {
	define( 'MASTHEAD_STATS_FIXTURE', WP_CONTENT_DIR . '/masthead-fixtures/stats-sample.json' );
}
