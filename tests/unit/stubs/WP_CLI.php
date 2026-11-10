<?php
/**
 * Minimal WP_CLI stub for unit tests.
 *
 * @package Masthead
 */

// phpcs:ignoreFile

namespace {
	if ( ! class_exists( 'WP_CLI' ) ) {
		class WP_CLI {
			public static array $lines  = array();
			public static array $errors = array();
			public static array $warnings = array();

			public static function reset(): void {
				self::$lines  = array();
				self::$errors = array();
				self::$warnings = array();
			}

			public static function warning( $message ) {
				self::$warnings[] = $message;
			}

			public static function line( $text = '' ) {
				self::$lines[] = $text;
			}

			public static function error( $message ) {
				self::$errors[] = $message;
			}

			public static function success( $message ) {
				self::$lines[] = $message;
			}

			public static function add_command( $name, $callable ) {}
		}
	}
}

namespace WP_CLI\Utils {
	if ( ! function_exists( 'WP_CLI\Utils\format_items' ) ) {
		function format_items( $format, $items, $fields ) {
			\WP_CLI::$lines[] = array( $format, $items, $fields );
		}
	}
}
