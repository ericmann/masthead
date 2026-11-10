<?php
/**
 * Loads every test-only mu-plugin from masthead-e2e/ in name order.
 *
 * WordPress does not load mu-plugins from subdirectories. Never packaged.
 *
 * @package Masthead
 */

$masthead_e2e_files = glob( __DIR__ . '/masthead-e2e/*.php' );
if ( is_array( $masthead_e2e_files ) ) {
	sort( $masthead_e2e_files );
	foreach ( $masthead_e2e_files as $masthead_e2e_file ) {
		require_once $masthead_e2e_file;
	}
}
