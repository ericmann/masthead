<?php
/**
 * The AI interface.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Ai;

/**
 * Text generation as Masthead uses it. The tier is 'fast' or 'strong'.
 */
interface AiClient {

	/**
	 * Whether a provider can generate text. Makes no network call.
	 *
	 * @return bool
	 */
	public function supported(): bool;

	/**
	 * Generates JSON matching a schema.
	 *
	 * @param string $system System instruction.
	 * @param string $user   User prompt.
	 * @param array  $schema JSON schema.
	 * @param string $tier   'fast' or 'strong'.
	 * @return array|\WP_Error Decoded JSON, or an error.
	 */
	public function json( string $system, string $user, array $schema, string $tier ): array|\WP_Error;

	/**
	 * Generates plain text.
	 *
	 * @param string $system System instruction.
	 * @param string $user   User prompt.
	 * @param string $tier   'fast' or 'strong'.
	 * @return string|\WP_Error Text, or an error.
	 */
	public function text( string $system, string $user, string $tier ): string|\WP_Error;
}
