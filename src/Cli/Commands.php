<?php
/**
 * WP-CLI command registry.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Cli;

use Masthead\Support\Dates;

/**
 * Registers `wp masthead <name>` commands and holds what they share.
 */
final class Commands {

	/**
	 * Registers each command.
	 *
	 * @param array<string,callable> $commands Command name to callable.
	 * @return void
	 */
	public static function register( array $commands ): void {
		foreach ( $commands as $name => $callable ) {
			\WP_CLI::add_command( 'masthead ' . $name, $callable );
		}
	}

	/**
	 * Reads --now and pins the clock for this process.
	 *
	 * @param array<string,mixed> $assoc_args Command options.
	 * @return string|null The pinned datetime, or null when --now was not given.
	 */
	public static function apply_now( array $assoc_args ): ?string {
		$now = $assoc_args['now'] ?? null;
		if ( null === $now ) {
			return null;
		}
		if ( ! is_string( $now ) || null === Dates::parse_local( $now ) ) {
			\WP_CLI::error( 'The --now value must be a datetime like 2026-11-09 14:00:00.' );
		}
		add_filter(
			'masthead_now',
			static fn() => $now
		);
		return $now;
	}
}
