<?php
/**
 * Command masthead suggest.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Cli;

use Masthead\Abilities\Ability;

/**
 * Prints the Desk suggestions for a date from the same ability the UI uses.
 */
final class SuggestCommand {

	/**
	 * Constructor.
	 *
	 * @param Ability $ability The masthead/suggest-for-date ability.
	 */
	public function __construct( private Ability $ability ) {}

	/**
	 * Prints one line per suggestion: kind, title, reason.
	 *
	 * ## OPTIONS
	 *
	 * <date>
	 * : The date, YYYY-MM-DD.
	 *
	 * [--ideas]
	 * : Ask for AI ideas too (needs Phase 5).
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
		if ( isset( $assoc_args['ideas'] ) ) {
			\WP_CLI::warning( '--ideas needs Phase 5' );
		}
		$result = $this->ability->execute( array( 'date' => (string) ( $args[0] ?? '' ) ) );
		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() );
			return;
		}
		foreach ( $result['suggestions'] as $suggestion ) {
			\WP_CLI::line( $suggestion['kind'] . "\t" . $suggestion['title'] . "\t" . $suggestion['why'] );
		}
	}
}
