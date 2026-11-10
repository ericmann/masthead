<?php
/**
 * The nightly run.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Cron;

use Masthead\Config;
use Masthead\Desk\SwapCheck;
use Masthead\Stats\StatsSource;
use Masthead\Support\Clock;
use Masthead\Support\Dates;

/**
 * Warms the stats cache and stores the swap-check findings. It reads and caches only.
 */
final class Nightly {

	/**
	 * The event name.
	 */
	public const HOOK = 'masthead_nightly';

	/**
	 * The option holding the findings.
	 */
	public const OPTION = 'masthead_findings';

	/**
	 * Constructor.
	 *
	 * @param SwapCheck   $swap_check Swap check.
	 * @param StatsSource $stats      Stats source.
	 * @param Clock       $clock      Clock.
	 */
	public function __construct( private SwapCheck $swap_check, private StatsSource $stats, private Clock $clock ) {}

	/**
	 * Schedules the daily event when none exists, first at the next occurrence of the configured site hour.
	 *
	 * @return void
	 */
	public function schedule(): void {
		if ( false !== wp_next_scheduled( self::HOOK ) ) {
			return;
		}
		$now   = $this->clock->now();
		$hour  = sprintf( '%02d:00', (int) Config::get( 'cron.nightly_hour' ) );
		$first = Dates::parse_local( Dates::combine( Dates::to_ymd( $now ), $hour ) );
		if ( null === $first ) {
			return;
		}
		if ( $first <= $now ) {
			$first = Dates::parse_local( Dates::combine( Dates::add_days( Dates::to_ymd( $now ), 1 ), $hour ) );
		}
		if ( null !== $first ) {
			wp_schedule_event( $first->getTimestamp(), 'daily', self::HOOK );
		}
	}

	/**
	 * Clears the event.
	 *
	 * @return void
	 */
	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * The event callback: warm the stats cache, run the swap check, store the findings.
	 *
	 * @return void
	 */
	public function run(): void {
		if ( $this->stats->available() ) {
			$days = (int) Config::get( 'stats.window_days' );
			$this->stats->views_by_category( $days );
			$this->stats->views_by_weekday( $days );
			$this->stats->top_posts( $days, (int) Config::get( 'desk.candidate_pool' ) );
		}
		$items = $this->swap_check->run();
		update_option(
			self::OPTION,
			array(
				'generated_at' => Dates::to_iso( $this->clock->now() ),
				'items'        => $items,
			),
			false
		);
		/**
		 * Fires after the nightly run stored its findings.
		 *
		 * @param int $count Number of findings.
		 */
		do_action( 'masthead_findings_updated', count( $items ) );
	}
}
