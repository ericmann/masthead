<?php
/**
 * Pins the Masthead clock from the masthead_seed_now option. Test code, never packaged.
 *
 * @package Masthead
 */

add_filter(
	'masthead_now',
	static function ( $now ) {
		$pinned = get_option( 'masthead_seed_now' );
		if ( is_string( $pinned ) && 1 === preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $pinned ) ) {
			return $pinned;
		}
		return $now;
	}
);
