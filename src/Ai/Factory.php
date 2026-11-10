<?php
/**
 * AI client factory.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Ai;

/**
 * Chooses the AI client. The test filter is honoured only in a local environment.
 */
final class Factory {

	/**
	 * Builds the client.
	 *
	 * @param string|null   $environment Environment type; defaults to wp_get_environment_type().
	 * @param callable|null $on_model    Passed to WpAiClient.
	 * @return AiClient
	 */
	public static function make( ?string $environment = null, ?callable $on_model = null ): AiClient {
		$environment = $environment ?? wp_get_environment_type();
		$default     = new WpAiClient( null, $on_model );
		if ( 'local' !== $environment ) {
			return $default;
		}
		$filtered = apply_filters( 'masthead_ai_client', $default );
		return $filtered instanceof AiClient ? $filtered : $default;
	}
}
