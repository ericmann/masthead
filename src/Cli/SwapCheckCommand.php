<?php
/**
 * Command masthead swap-check.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Cli;

use Masthead\Desk\SwapCheck;

/**
 * Runs the swap check from the shell.
 */
final class SwapCheckCommand {

	/**
	 * Constructor.
	 *
	 * @param SwapCheck $check Swap check.
	 */
	public function __construct( private SwapCheck $check ) {}

	/**
	 * Prints the findings, or stores them like the nightly run.
	 *
	 * ## OPTIONS
	 *
	 * [--write]
	 * : Store the findings the way the nightly run does, and print how many were stored.
	 *
	 * [--now=<datetime>]
	 * : Pin the clock to this site-time datetime.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Options.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		Commands::apply_now( $assoc_args );
		if ( isset( $assoc_args['write'] ) ) {
			// The same code path as the cron; the Cli module may not import Cron.
			do_action( 'masthead_nightly' );
			$stored = get_option( 'masthead_findings' );
			\WP_CLI::success( sprintf( 'Stored %d findings.', is_array( $stored ) ? count( (array) ( $stored['items'] ?? array() ) ) : 0 ) );
			return;
		}
		foreach ( $this->check->run() as $finding ) {
			\WP_CLI::line( $finding['type'] . "\t" . $finding['post_id'] . "\t" . $finding['date'] . "\t" . $finding['why'] );
		}
	}
}
