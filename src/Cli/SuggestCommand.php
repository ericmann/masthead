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
	 * : Ask for AI ideas too (needs a provider and the prompt_ai capability).
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
		$input = array( 'date' => (string) ( $args[0] ?? '' ) );
		if ( isset( $assoc_args['ideas'] ) ) {
			$input['include_ideas'] = true;
		}
		$result = $this->ability->execute( $input );
		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() );
			return;
		}
		if ( isset( $result['ai']['error'] ) ) {
			\WP_CLI::warning( 'AI ideas unavailable: ' . $result['ai']['error'] );
		}
		foreach ( $result['suggestions'] as $suggestion ) {
			\WP_CLI::line( $suggestion['kind'] . "\t" . $suggestion['title'] . "\t" . $suggestion['why'] );
		}
	}
}
