<?php
/**
 * Command masthead stats:probe.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Cli;

use Masthead\Config;
use Masthead\Stats\JetpackStats;
use Masthead\Stats\StatsSource;
use Masthead\Support\Json;

/**
 * Prints scrubbed payload shapes from Jetpack Stats, for building spike fixtures.
 */
final class StatsProbeCommand {

	/**
	 * Constructor.
	 *
	 * @param StatsSource $source The stats source the factory built.
	 */
	public function __construct( private StatsSource $source ) {}

	/**
	 * Prints payload shapes with numbers replaced by 0.
	 *
	 * ## OPTIONS
	 *
	 * [--days=<n>]
	 * : Window in days. Defaults to the stats.window_days setting.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Options.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		if ( ! $this->source instanceof JetpackStats ) {
			\WP_CLI::line( 'Jetpack Stats is not available on this site.' );
			return;
		}
		$days = isset( $assoc_args['days'] ) ? (int) $assoc_args['days'] : (int) Config::get( 'stats.window_days' );
		\WP_CLI::line( Json::encode( $this->source->probe( $days ) ) );
	}
}
