<?php
/**
 * Composition root.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead;

use Masthead\Admin\Assets;
use Masthead\Admin\CalendarPage;
use Masthead\Admin\Notices;
use Masthead\Plan\Colors;
use Masthead\Plan\Rhythm;
use Masthead\Support\Clock;

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

		$clock  = new Clock();
		$assets = new Assets( $clock, dirname( __DIR__ ) . '/masthead.php' );

		$calendar = new CalendarPage( $assets );
		$notices  = new Notices( $assets );
		add_action( 'admin_menu', array( $calendar, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $assets, 'enqueue' ) );
		add_action( 'admin_notices', array( $notices, 'render' ) );
		add_action( 'init', array( Colors::class, 'register_meta' ) );
	}

	/**
	 * Activation hook: installs the default rhythm.
	 *
	 * @return void
	 */
	public static function activate(): void {
		Rhythm::install_defaults();
	}
}
