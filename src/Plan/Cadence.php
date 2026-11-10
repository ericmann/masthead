<?php
/**
 * Series cadence.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Plan;

use Masthead\Support\Dates;
use Masthead\Support\Json;

/**
 * A series cadence: { every_weeks: int >= 1, weekday: 0-6|null, time: "HH:MM" }.
 */
final class Cadence {

	/**
	 * Validates a raw cadence.
	 *
	 * @param mixed $raw Raw value.
	 * @return array{every_weeks:int,weekday:?int,time:string}|null
	 */
	public static function sanitize( $raw ): ?array {
		if ( ! is_array( $raw ) ) {
			return null;
		}
		$weeks = $raw['every_weeks'] ?? null;
		if ( is_string( $weeks ) && ctype_digit( $weeks ) ) {
			$weeks = (int) $weeks;
		}
		if ( ! is_int( $weeks ) || $weeks < 1 ) {
			return null;
		}
		$weekday = $raw['weekday'] ?? null;
		if ( is_string( $weekday ) && ctype_digit( $weekday ) ) {
			$weekday = (int) $weekday;
		}
		if ( null !== $weekday && ( ! is_int( $weekday ) || $weekday < 0 || $weekday >= Dates::DAYS_IN_WEEK ) ) {
			return null;
		}
		$time = $raw['time'] ?? null;
		if ( ! is_string( $time ) || ! Dates::is_time( $time ) ) {
			return null;
		}
		return array(
			'every_weeks' => $weeks,
			'weekday'     => $weekday,
			'time'        => $time,
		);
	}

	/**
	 * Decodes a stored cadence.
	 *
	 * @param string $json JSON text.
	 * @return array|null
	 */
	public static function from_json( string $json ): ?array {
		return self::sanitize( Json::decode_object( $json ) );
	}

	/**
	 * Encodes a cadence.
	 *
	 * @param array $cadence Cadence.
	 * @return string
	 */
	public static function to_json( array $cadence ): string {
		return Json::encode( $cadence );
	}

	/**
	 * Term-meta sanitise callback.
	 *
	 * @param mixed $value Raw value, JSON text or array.
	 * @return string JSON, or an empty string when invalid.
	 */
	public static function sanitize_json( $value ): string {
		$cadence = is_string( $value ) ? self::from_json( $value ) : self::sanitize( $value );
		return null === $cadence ? '' : self::to_json( $cadence );
	}

	/**
	 * The date the next part is expected on.
	 *
	 * @param string $previous_ymd Previous part's date.
	 * @param array  $cadence      Cadence.
	 * @return string Y-m-d.
	 */
	public static function expected_date( string $previous_ymd, array $cadence ): string {
		$base = Dates::add_days( $previous_ymd, (int) $cadence['every_weeks'] * Dates::DAYS_IN_WEEK );
		if ( '' === $base || null === ( $cadence['weekday'] ?? null ) ) {
			return $base;
		}
		$shift = ( (int) $cadence['weekday'] - Dates::weekday( $base ) + Dates::DAYS_IN_WEEK ) % Dates::DAYS_IN_WEEK;
		// More than half a week ahead means the nearer date is behind.
		if ( $shift + $shift > Dates::DAYS_IN_WEEK ) {
			$shift -= Dates::DAYS_IN_WEEK;
		}
		return Dates::add_days( $base, $shift );
	}

	/**
	 * Whether the actual date breaks the cadence by more than the window.
	 *
	 * @param string $previous_ymd Previous part's date.
	 * @param string $actual_ymd   This part's date.
	 * @param array  $cadence      Cadence.
	 * @param int    $window       Allowed days of drift.
	 * @return bool
	 */
	public static function is_break( string $previous_ymd, string $actual_ymd, array $cadence, int $window ): bool {
		$expected = self::expected_date( $previous_ymd, $cadence );
		return abs( Dates::diff_days( $expected, $actual_ymd ) ) > $window;
	}

	/**
	 * Dates for a run of parts starting at a date.
	 *
	 * @param string $start_ymd First part's date.
	 * @param array  $cadence   Cadence.
	 * @param int    $count     Number of dates.
	 * @return string[]
	 */
	public static function schedule( string $start_ymd, array $cadence, int $count ): array {
		$dates = array();
		$date  = $start_ymd;
		for ( $index = 0; $index < $count; $index++ ) {
			$dates[] = $date;
			$date    = self::expected_date( $date, $cadence );
		}
		return $dates;
	}
}
