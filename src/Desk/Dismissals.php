<?php
/**
 * Per-user dismissed suggestions.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Desk;

use Masthead\Support\Clock;

/**
 * A dismissal is one person's choice, so it lives in user meta: a map of date to keys.
 */
class Dismissals {

	/**
	 * User meta name.
	 */
	public const META = 'masthead_dismissed';

	/**
	 * Constructor.
	 *
	 * @param Clock $clock Clock.
	 */
	public function __construct( private Clock $clock ) {}

	/**
	 * Dismisses a suggestion for a date. Idempotent; also drops dates before today.
	 *
	 * @param int    $user_id User ID.
	 * @param string $date    Date, Y-m-d.
	 * @param string $key     Suggestion key.
	 * @return void
	 */
	public function dismiss( int $user_id, string $date, string $key ): void {
		$today = $this->clock->today();
		$map   = array();
		foreach ( $this->read( $user_id ) as $stored_date => $keys ) {
			if ( $stored_date >= $today ) {
				$map[ $stored_date ] = $keys;
			}
		}
		$keys = $map[ $date ] ?? array();
		if ( ! in_array( $key, $keys, true ) ) {
			$keys[] = $key;
		}
		$map[ $date ] = $keys;
		ksort( $map );
		update_user_meta( $user_id, self::META, $map );
	}

	/**
	 * Dismissed keys for a date.
	 *
	 * @param int    $user_id User ID.
	 * @param string $date    Date, Y-m-d.
	 * @return string[]
	 */
	public function for_date( int $user_id, string $date ): array {
		return $this->read( $user_id )[ $date ] ?? array();
	}

	/**
	 * Stored map, cleaned of anything malformed.
	 *
	 * @param int $user_id User ID.
	 * @return array<string,string[]>
	 */
	private function read( int $user_id ): array {
		$stored = get_user_meta( $user_id, self::META, true );
		if ( ! is_array( $stored ) ) {
			return array();
		}
		$map = array();
		foreach ( $stored as $date => $keys ) {
			if ( is_string( $date ) && is_array( $keys ) ) {
				$map[ $date ] = array_values( array_filter( $keys, 'is_string' ) );
			}
		}
		return $map;
	}
}
