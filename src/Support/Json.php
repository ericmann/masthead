<?php
/**
 * JSON helpers.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Support;

/**
 * Small JSON helpers.
 */
final class Json {

	/**
	 * Decode depth. Structural: nothing Masthead stores nests deeper.
	 */
	private const DEPTH = 32;

	/**
	 * Encodes with unescaped slashes and unicode.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public static function encode( $value ): string {
		return (string) wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	/**
	 * Decodes a JSON object to an associative array.
	 *
	 * @param string $text JSON text.
	 * @return array<mixed>|null Null when the text is not a JSON object.
	 */
	public static function decode_object( string $text ): ?array {
		if ( ! str_starts_with( ltrim( $text ), '{' ) ) {
			return null;
		}
		$decoded = json_decode( $text, true, self::DEPTH );
		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * Keeps trimmed non-empty strings, re-indexed.
	 *
	 * @param mixed $value Anything.
	 * @return string[]
	 */
	public static function string_list( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$out = array();
		foreach ( $value as $item ) {
			if ( is_string( $item ) && '' !== trim( $item ) ) {
				$out[] = trim( $item );
			}
		}
		return $out;
	}
}
