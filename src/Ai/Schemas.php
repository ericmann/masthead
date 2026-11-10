<?php
/**
 * JSON schemas for AI output and the validator that enforces them.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Ai;

/**
 * Static and pure. Invalid output is rejected, never repaired.
 */
final class Schemas {

	/**
	 * Ideas schema.
	 *
	 * @param string[] $allowed_categories Category slugs.
	 * @param int      $count              Maximum ideas.
	 * @return array
	 */
	public static function ideas( array $allowed_categories, int $count ): array {
		return self::object_of(
			'ideas',
			array(
				'ideas' => array(
					'type'     => 'array',
					'maxItems' => $count,
					'items'    => self::object_of(
						'idea',
						array(
							'title'    => self::string(),
							'angle'    => self::string(),
							'category' => self::category( $allowed_categories ),
						)
					),
				),
			)
		);
	}

	/**
	 * Outline schema.
	 *
	 * @return array
	 */
	public static function outline(): array {
		return self::object_of(
			'outline',
			array(
				'outline' => self::strings(),
			)
		);
	}

	/**
	 * Share copy schema.
	 *
	 * @return array
	 */
	public static function share_copy(): array {
		return self::object_of(
			'share_copy',
			array(
				'message' => self::string(),
			)
		);
	}

	/**
	 * Series proposal schema.
	 *
	 * @param string[] $allowed_categories Category slugs.
	 * @param int      $max_parts          Maximum parts.
	 * @return array
	 */
	public static function series_proposal( array $allowed_categories, int $max_parts ): array {
		return self::object_of(
			'series_proposal',
			array(
				'name'        => self::string(),
				'category'    => self::category( $allowed_categories ),
				'every_weeks' => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
				'parts'       => array(
					'type'     => 'array',
					'maxItems' => $max_parts,
					'items'    => self::object_of(
						'part',
						array(
							'title'   => self::string(),
							'outline' => self::strings(),
						)
					),
				),
			)
		);
	}

	/**
	 * The schema as sent to the provider: without the constraint keywords that structured-output APIs reject
	 * (Anthropic's returns 400 for minLength, minimum and maxItems), each one restated in the description instead.
	 * validate() still enforces the full schema on the reply.
	 *
	 * @param array $schema Schema from this class.
	 * @return array
	 */
	public static function wire( array $schema ): array {
		$notes = array();
		if ( isset( $schema['minLength'] ) ) {
			$notes[] = 1 === $schema['minLength'] ? 'Not empty.' : sprintf( 'At least %d characters.', (int) $schema['minLength'] );
		}
		if ( isset( $schema['minimum'] ) ) {
			$notes[] = sprintf( 'At least %d.', (int) $schema['minimum'] );
		}
		if ( isset( $schema['maxItems'] ) ) {
			$notes[] = sprintf( 'At most %d items.', (int) $schema['maxItems'] );
		}
		if ( isset( $schema['minItems'] ) && $schema['minItems'] > 1 ) {
			$notes[] = sprintf( 'At least %d items.', (int) $schema['minItems'] );
		}
		$wire = array();
		foreach ( $schema as $key => $value ) {
			if ( in_array( $key, array( 'title', 'minLength', 'minimum', 'maxItems' ), true ) ) {
				continue;
			}
			if ( 'minItems' === $key && $value > 1 ) {
				continue;
			}
			if ( 'properties' === $key && is_array( $value ) ) {
				$value = array_map( array( self::class, 'wire' ), $value );
			} elseif ( 'items' === $key && is_array( $value ) ) {
				$value = self::wire( $value );
			}
			$wire[ $key ] = $value;
		}
		if ( array() !== $notes ) {
			$description         = isset( $wire['description'] ) ? rtrim( (string) $wire['description'] ) . ' ' : '';
			$wire['description'] = $description . implode( ' ', $notes );
		}
		return $wire;
	}

	/**
	 * Validates data against a schema without changing it.
	 *
	 * @param mixed $data   Decoded data.
	 * @param array $schema Schema from this class.
	 * @return true|string True, or the path of the first violation.
	 */
	public static function validate( mixed $data, array $schema ): true|string {
		return self::check( $data, $schema, '$' );
	}

	/**
	 * Recursive check.
	 *
	 * @param mixed  $data   Value.
	 * @param array  $schema Schema.
	 * @param string $path   Path so far.
	 * @return true|string
	 */
	private static function check( mixed $data, array $schema, string $path ): true|string {
		$type = $schema['type'] ?? null;
		if ( 'object' === $type ) {
			return self::check_object( $data, $schema, $path );
		}
		if ( 'array' === $type ) {
			return self::check_array( $data, $schema, $path );
		}
		if ( 'string' === $type ) {
			if ( ! is_string( $data ) ) {
				return $path;
			}
			if ( isset( $schema['minLength'] ) && strlen( $data ) < $schema['minLength'] ) {
				return $path;
			}
		} elseif ( 'integer' === $type ) {
			if ( ! is_int( $data ) ) {
				return $path;
			}
			if ( isset( $schema['minimum'] ) && $data < $schema['minimum'] ) {
				return $path;
			}
		}
		if ( isset( $schema['enum'] ) && ! in_array( $data, $schema['enum'], true ) ) {
			return $path;
		}
		return true;
	}

	/**
	 * Object check.
	 *
	 * @param mixed  $data   Value.
	 * @param array  $schema Schema.
	 * @param string $path   Path.
	 * @return true|string
	 */
	private static function check_object( mixed $data, array $schema, string $path ): true|string {
		if ( ! is_array( $data ) || ( array() !== $data && array_is_list( $data ) ) ) {
			return $path;
		}
		$properties = $schema['properties'] ?? array();
		foreach ( $schema['required'] ?? array() as $name ) {
			if ( ! array_key_exists( $name, $data ) ) {
				return $path . '.' . $name;
			}
		}
		foreach ( $data as $name => $value ) {
			if ( ! isset( $properties[ $name ] ) ) {
				if ( false === ( $schema['additionalProperties'] ?? true ) ) {
					return $path . '.' . $name;
				}
				continue;
			}
			$result = self::check( $value, $properties[ $name ], $path . '.' . $name );
			if ( true !== $result ) {
				return $result;
			}
		}
		return true;
	}

	/**
	 * Array check.
	 *
	 * @param mixed  $data   Value.
	 * @param array  $schema Schema.
	 * @param string $path   Path.
	 * @return true|string
	 */
	private static function check_array( mixed $data, array $schema, string $path ): true|string {
		if ( ! is_array( $data ) || ! array_is_list( $data ) ) {
			return $path;
		}
		if ( isset( $schema['minItems'] ) && count( $data ) < $schema['minItems'] ) {
			return $path;
		}
		if ( isset( $schema['maxItems'] ) && count( $data ) > $schema['maxItems'] ) {
			return $path;
		}
		foreach ( $data as $index => $item ) {
			if ( isset( $schema['items'] ) ) {
				$result = self::check( $item, $schema['items'], $path . '[' . $index . ']' );
				if ( true !== $result ) {
					return $result;
				}
			}
		}
		return true;
	}

	/**
	 * An object schema with every property required and no extras.
	 *
	 * @param string $title      Title.
	 * @param array  $properties Properties.
	 * @return array
	 */
	private static function object_of( string $title, array $properties ): array {
		return array(
			'title'                => $title,
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array_keys( $properties ),
			'properties'           => $properties,
		);
	}

	/**
	 * A non-empty string.
	 *
	 * @return array
	 */
	private static function string(): array {
		return array(
			'type'      => 'string',
			'minLength' => 1,
		);
	}

	/**
	 * A non-empty list of non-empty strings.
	 *
	 * @return array
	 */
	private static function strings(): array {
		return array(
			'type'     => 'array',
			'minItems' => 1,
			'items'    => self::string(),
		);
	}

	/**
	 * A category enum.
	 *
	 * @param string[] $allowed Slugs.
	 * @return array
	 */
	private static function category( array $allowed ): array {
		return array(
			'type' => 'string',
			'enum' => array_values( $allowed ),
		);
	}
}
