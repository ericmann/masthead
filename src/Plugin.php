<?php
/**
 * Composition root.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead;

use Masthead\Abilities\CreatePlannedDraft;
use Masthead\Abilities\DraftOutline;
use Masthead\Abilities\DraftShareCopy;
use Masthead\Ai\Factory as AiFactory;
use Masthead\Abilities\GetPlan;
use Masthead\Abilities\Registrar;
use Masthead\Abilities\DismissSuggestion;
use Masthead\Abilities\Reschedule;
use Masthead\Abilities\ShareConnections;
use Masthead\Abilities\SharePost;
use Masthead\Abilities\SuggestForDate;
use Masthead\Abilities\SwapCheck as SwapCheckAbility;
use Masthead\Abilities\StatsSummary;
use Masthead\Admin\Assets;
use Masthead\Admin\CalendarPage;
use Masthead\Admin\Notices;
use Masthead\Admin\SettingsPage;
use Masthead\Cli\Commands;
use Masthead\Cli\PlanCommand;
use Masthead\Cli\SeedCommand;
use Masthead\Cli\SocialProbeCommand;
use Masthead\Cli\StatsProbeCommand;
use Masthead\Cli\SuggestCommand;
use Masthead\Cli\SwapCheckCommand;
use Masthead\Cli\Seeder;
use Masthead\Desk\Candidates\CategoryGap;
use Masthead\Desk\Candidates\DraftMatch;
use Masthead\Desk\Candidates\Reshare;
use Masthead\Desk\Candidates\SeriesNext;
use Masthead\Desk\Budget;
use Masthead\Desk\BudgetedAi;
use Masthead\Desk\Dismissals;
use Masthead\Desk\Ideas;
use Masthead\Cron\Nightly;
use Masthead\Desk\Ranker;
use Masthead\Desk\Reasons;
use Masthead\Desk\Scoring;
use Masthead\Desk\ShareCopy;
use Masthead\Desk\Suggester;
use Masthead\Desk\SwapCheck as DeskSwapCheck;
use Masthead\Plan\Colors;
use Masthead\Plan\PlanReader;
use Masthead\Plan\Reschedule as PlanReschedule;
use Masthead\Plan\Rhythm;
use Masthead\Plan\SeriesSource;
use Masthead\Plan\Slots;
use Masthead\Social\Factory as SocialFactory;
use Masthead\Stats\Factory as StatsFactory;
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

		$settings = new SettingsPage( $assets );
		add_action( 'admin_menu', array( $settings, 'register' ) );
		add_action( 'admin_init', array( $settings, 'register_settings' ) );
		add_action( 'rest_api_init', array( $settings, 'register_settings' ) );
		add_action( 'init', array( Colors::class, 'register_meta' ) );

		$series = new SeriesSource();
		add_action( 'init', array( $series, 'register' ), PHP_INT_MAX );

		$slots = new Slots( $series );
		add_action( 'init', array( $slots, 'register_meta' ) );

		$assets->add_planning_boot_data( $series );

		$stats     = StatsFactory::make();
		$share     = SocialFactory::make();
		$reader    = new PlanReader( $clock, $series );
		$budget     = new Budget( $clock );
		$ai         = new BudgetedAi( AiFactory::make( null, array( $budget, 'note_model' ) ), $budget );
		$dismissals = new Dismissals( $clock );
		$swap_check = new DeskSwapCheck( $reader, $series, new Scoring(), new Reasons( $clock ), $stats, $clock );
		$nightly    = new Nightly( $swap_check, $stats, $clock );
		add_action( 'init', array( $nightly, 'schedule' ) );
		add_action( Nightly::HOOK, array( $nightly, 'run' ) );
		$gap_source = new CategoryGap( $clock );
		$ideas      = new Ideas( $ai, $reader, $series, $gap_source, $budget );
		$suggester  = new Suggester(
			new DraftMatch( $reader ),
			new SeriesNext( $series ),
			new Reshare( $stats, $share, $clock ),
			$gap_source,
			new Ranker(),
			new Reasons( $clock ),
			$dismissals,
			$reader,
			$clock,
			$ideas
		);
		$registrar = new Registrar(
			new GetPlan( $reader ),
			new CreatePlannedDraft( $slots, $series ),
			new Reschedule( new PlanReschedule( $clock ) ),
			new StatsSummary( $stats ),
			new ShareConnections( $share ),
			new SharePost( $share, $clock ),
			new SuggestForDate( $suggester ),
			new DismissSuggestion( $dismissals ),
			new SwapCheckAbility( $reader, $series ),
			new DraftOutline( $ideas ),
			new DraftShareCopy( new ShareCopy( $ai ) )
		);
		add_action( 'wp_abilities_api_categories_init', array( $registrar, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( $registrar, 'register' ) );
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			Commands::register(
				array(
					'seed' => new SeedCommand( new Seeder( $slots, $series ) ),
					'plan' => new PlanCommand( $registrar->get( 'masthead/get-plan' ), $clock ),
					'stats:probe' => new StatsProbeCommand( $stats ),
					'social:probe' => new SocialProbeCommand( $share ),
					'suggest' => new SuggestCommand( $registrar->get( 'masthead/suggest-for-date' ) ),
					'swap-check' => new SwapCheckCommand( $swap_check ),
				)
			);
		}

		$assets->add_boot_data(
			'ai',
			static fn() => array(
				'supported' => $ai->supported(),
				'remaining' => $budget->remaining(),
			)
		);
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

	/**
	 * Deactivation hook: clears the nightly event.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		Nightly::unschedule();
	}
}
