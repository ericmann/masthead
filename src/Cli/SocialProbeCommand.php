<?php
/**
 * Command masthead social:probe.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Cli;

use Masthead\Social\JetpackSocial;
use Masthead\Social\ShareSource;
use Masthead\Support\Json;

/**
 * Prints whether Jetpack Social is usable, with no names, ids or text.
 */
final class SocialProbeCommand {

	/**
	 * Constructor.
	 *
	 * @param ShareSource $source The share source the factory built.
	 */
	public function __construct( private ShareSource $source ) {}

	/**
	 * Prints availability, the connection count and which routes exist.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Options.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		if ( ! $this->source instanceof JetpackSocial ) {
			\WP_CLI::line( 'Jetpack Social is not available on this site.' );
			return;
		}
		\WP_CLI::line( Json::encode( $this->source->probe() ) );
	}
}
