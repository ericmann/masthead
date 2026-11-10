<?php
/**
 * Composition root.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead;

use Masthead\Abilities\CreatePlannedDraft;
use Masthead\Abilities\GetPlan;
use Masthead\Abilities\Registrar;
use Masthead\Admin\Assets;
use Masthead\Admin\CalendarPage;
use Masthead\Admin\Notices;
use Masthead\Plan\Colors;
use Masthead\Plan\PlanReader;
use Masthead\Plan\Rhythm;
use Masthead\Plan\SeriesSource;
use Masthead\Plan\Slots;
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

		$series = new SeriesSource();
		add_action( 'init', array( $series, 'register' ), PHP_INT_MAX );

		$slots = new Slots( $series );
		add_action( 'init', array( $slots, 'register_meta' ) );

		$reader    = new PlanReader( $clock, $series );
		$registrar = new Registrar(
			new GetPlan( $reader ),
			new CreatePlannedDraft( $slots, $series )
		);
		add_action( 'wp_abilities_api_categories_init', array( $registrar, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( $registrar, 'register' ) );
		$assets->add_boot_data( 'abilities', array( $registrar, 'describe' ) );
		$assets->add_boot_data(
			'run',
			static fn() => array(
				'path'     => '/wp-abilities/v1/abilities/%s/run',
				'readonly' => array(
					'method' => 'GET',
					'input'  => 'query',
				),
				'write'    => array(
					'method' => 'POST',
					'input'  => 'body',
				),
			)
		);
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
