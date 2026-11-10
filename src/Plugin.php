<?php
/**
 * Composition root.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead;

/**
 * Builds every service and wires every hook. Later tasks add their wiring in boot().
 */
final class Plugin {

	/**
	 * Whether boot() has already run.
	 *
	 * @var bool
	 */
	private static bool $booted = false;

	/**
	 * Boots the plugin. Idempotent.
	 *
	 * @return void
	 */
	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;
	}
}
