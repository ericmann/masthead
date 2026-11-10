<?php
/**
 * Plugin Name:       Masthead
 * Description:       Editorial calendar, Desk suggestions, nightly swap check and Jetpack Social re-sharing.
 * Version:           1.0.0
 * Requires at least: 7.1
 * Requires PHP:      8.3
 * License:           GPL-2.0-or-later
 * Text Domain:       masthead
 *
 * @package Masthead
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'Masthead\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$file     = __DIR__ . '/src/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

require_once __DIR__ . '/src/constants.php';

add_action( 'plugins_loaded', array( \Masthead\Plugin::class, 'boot' ) );

register_activation_hook( __FILE__, array( \Masthead\Plugin::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \Masthead\Plugin::class, 'deactivate' ) );
