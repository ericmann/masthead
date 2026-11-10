<?php
/**
 * The prompt_ai fallback.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Ai;

use Masthead\Config;

/**
 * Grants prompt_ai to users who hold the ai.fallback_capability capability, but only while nothing else has decided it.
 *
 * WordPress 7.1 neither defines nor grants prompt_ai (docs/spikes/P5-01-ai-client.md, fact 7), so without this every AI
 * ability is denied on a stock site. A role, a user or another user_has_cap filter that sets prompt_ai to true or false
 * always wins: this filter is hooked last and only fills a missing key.
 */
final class Capability {

	/**
	 * The capability the AI abilities check.
	 */
	public const PROMPT_AI = 'prompt_ai';

	/**
	 * Arguments user_has_cap passes that grant() reads ($allcaps and $caps). Structural, not a tunable.
	 */
	public const ACCEPTED_ARGS = 2;

	/**
	 * Filter callback for user_has_cap.
	 *
	 * @param mixed $allcaps Capabilities the user holds, name => bool.
	 * @param mixed $caps    Primitive capabilities being checked.
	 * @return mixed The same value, with prompt_ai set to true when the fallback applies.
	 */
	public function grant( $allcaps, $caps = array() ) {
		if ( ! is_array( $allcaps ) || ! in_array( self::PROMPT_AI, (array) $caps, true ) ) {
			return $allcaps;
		}
		if ( array_key_exists( self::PROMPT_AI, $allcaps ) ) {
			return $allcaps;
		}
		$fallback = Config::get( 'ai.fallback_capability' );
		if ( is_string( $fallback ) && '' !== $fallback && ! empty( $allcaps[ $fallback ] ) ) {
			$allcaps[ self::PROMPT_AI ] = true;
		}
		return $allcaps;
	}
}
