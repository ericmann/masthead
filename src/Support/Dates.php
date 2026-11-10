<?php
/**
 * Site-timezone date helpers. Never asks for the current time; that is Clock.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Support;

use DateTimeImmutable;

/**
 * Pure date helpers over calendar dates in the site timezone.
 */
final class Dates {

	/**
	 * Days in a week. Structural, not a tunable.
	 */
	public const DAYS_IN_WEEK = 7;

	/**
	 * Parses a strict format in the site timezone; null when invalid or overflowing.
	 *
	 * @param string $format PHP format, with the leading !.
	 * @param string $value  Text.
	 * @return DateTimeImmutable|null
	 */
	private static function parse( string $format, string $value ): ?DateTimeImmutable {
		$parsed = DateTimeImmutable::createFromFormat( $format, $value, wp_timezone() );
		if ( false === $parsed ) {
			return null;
		}
		$errors = DateTimeImmutable::getLastErrors();
		if ( is_array( $errors ) && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) {
			return null;
		}
		return $parsed;
	}

	/**
	 * Parses Y-m-d.
	 *
	 * @param string $ymd Date.
	 * @return DateTimeImmutable|null
	 */
	public static function parse_date( string $ymd ): ?DateTimeImmutable {
		$parsed = self::parse( '!Y-m-d', $ymd );
		return ( null !== $parsed && $parsed->format( 'Y-m-d' ) === $ymd ) ? $parsed : null;
	}

	/**
	 * Parses Y-m-d H:i:s as site time.
	 *
	 * @param string $mysql Local datetime.
	 * @return DateTimeImmutable|null
	 */
	public static function parse_local( string $mysql ): ?DateTimeImmutable {
		$parsed = self::parse( '!Y-m-d H:i:s', $mysql );
		return ( null !== $parsed && $parsed->format( 'Y-m-d H:i:s' ) === $mysql ) ? $parsed : null;
	}

	/**
	 * Parses an ISO 8601 datetime with offset and converts it to the site zone.
	 *
	 * @param string $iso ISO 8601 datetime.
	 * @return DateTimeImmutable|null
	 */
	public static function parse_iso( string $iso ): ?DateTimeImmutable {
		$parsed = self::parse( '!Y-m-d\TH:i:sP', $iso );
		return null === $parsed ? null : $parsed->setTimezone( wp_timezone() );
	}

	/**
	 * Whether the text is a real Y-m-d date.
	 *
	 * @param string $value Text.
	 * @return bool
	 */
	public static function is_date( string $value ): bool {
		return null !== self::parse_date( $value );
	}

	/**
	 * Whether the text is a 24-hour HH:MM time.
	 *
	 * @param string $value Text.
	 * @return bool
	 */
	public static function is_time( string $value ): bool {
		$parsed = self::parse( '!H:i', $value );
		return null !== $parsed && $parsed->format( 'H:i' ) === $value;
	}

	/**
	 * Combines a date and HH:MM into a local datetime.
	 *
	 * @param string $ymd  Date.
	 * @param string $hhmm Time.
	 * @return string Y-m-d H:i:00.
	 */
	public static function combine( string $ymd, string $hhmm ): string {
		return $ymd . ' ' . $hhmm . ':00';
	}

	/**
	 * Date part.
	 *
	 * @param DateTimeImmutable $dt Datetime.
	 * @return string
	 */
	public static function to_ymd( DateTimeImmutable $dt ): string {
		return $dt->format( 'Y-m-d' );
	}

	/**
	 * Local datetime string.
	 *
	 * @param DateTimeImmutable $dt Datetime.
	 * @return string
	 */
	public static function to_mysql( DateTimeImmutable $dt ): string {
		return $dt->format( 'Y-m-d H:i:s' );
	}

	/**
	 * ISO 8601 datetime with the site offset.
	 *
	 * @param DateTimeImmutable $dt Datetime.
	 * @return string
	 */
	public static function to_iso( DateTimeImmutable $dt ): string {
		return $dt->setTimezone( wp_timezone() )->format( DATE_ATOM );
	}

	/**
	 * Converts a local datetime to GMT. Invalid input gives the zero date.
	 *
	 * @param string $mysql Local datetime.
	 * @return string
	 */
	public static function local_to_gmt( string $mysql ): string {
		$parsed = self::parse_local( $mysql );
		if ( null === $parsed ) {
			return '0000-00-00 00:00:00';
		}
		$utc = timezone_open( 'UTC' );
		return $parsed->setTimezone( $utc )->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Weekday of a date, 0 = Sunday. Invalid input gives 0.
	 *
	 * @param string $ymd Date.
	 * @return int
	 */
	public static function weekday( string $ymd ): int {
		$parsed = self::parse_date( $ymd );
		return null === $parsed ? 0 : (int) $parsed->format( 'w' );
	}

	/**
	 * Adds calendar days. Invalid input gives an empty string.
	 *
	 * @param string $ymd  Date.
	 * @param int    $days Signed day count.
	 * @return string
	 */
	public static function add_days( string $ymd, int $days ): string {
		$parsed = self::parse_date( $ymd );
		if ( null === $parsed ) {
			return '';
		}
		return $parsed->modify( sprintf( '%+d days', $days ) )->format( 'Y-m-d' );
	}

	/**
	 * Calendar days from $from to $to (signed). Invalid input gives 0.
	 *
	 * @param string $from Date.
	 * @param string $to   Date.
	 * @return int
	 */
	public static function diff_days( string $from, string $to ): int {
		$a = self::parse_date( $from );
		$b = self::parse_date( $to );
		if ( null === $a || null === $b ) {
			return 0;
		}
		return (int) $a->diff( $b )->format( '%r%a' );
	}

	/**
	 * Inclusive list of dates; empty when $to is before $from.
	 *
	 * @param string $from Date.
	 * @param string $to   Date.
	 * @return string[]
	 */
	public static function range( string $from, string $to ): array {
		$span = self::diff_days( $from, $to );
		if ( $span < 0 || ! self::is_date( $from ) || ! self::is_date( $to ) ) {
			return array();
		}
		$dates = array();
		for ( $offset = 0; $offset <= $span; $offset++ ) {
			$dates[] = self::add_days( $from, $offset );
		}
		return $dates;
	}

	/**
	 * HH:MM of a local datetime. Invalid input gives an empty string.
	 *
	 * @param string $mysql Local datetime.
	 * @return string
	 */
	public static function time_of( string $mysql ): string {
		$parsed = self::parse_local( $mysql );
		return null === $parsed ? '' : $parsed->format( 'H:i' );
	}
}
