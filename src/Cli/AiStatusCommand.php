<?php
/**
 * Command masthead ai:status.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Cli;

use Masthead\Config;
use Masthead\Desk\Ideas;

/**
 * Prints whether the AI can be used and how much of the monthly budget is left. Shows no credential.
 */
final class AiStatusCommand {

	/**
	 * Constructor.
	 *
	 * @param Ideas $ideas Ideas service, which knows the provider and budget state.
	 */
	public function __construct( private Ideas $ideas ) {}

	/**
	 * Prints the AI state: provider, requests left, the monthly cap and the configured models.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Options.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$status = $this->ideas->status();
		\WP_CLI::line( 'supported: ' . ( $status['supported'] ? 'yes' : 'no' ) );
		\WP_CLI::line( 'remaining: ' . $status['remaining'] );
		\WP_CLI::line( 'cap: ' . (int) Config::get( 'ai.monthly_request_cap' ) );
		foreach ( array( 'fast', 'strong' ) as $tier ) {
			\WP_CLI::line( 'models.' . $tier . ': ' . implode( ', ', (array) Config::get( 'ai.models.' . $tier ) ) );
		}
	}
}
