<?php
/**
 * The one clock.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Support;

use DateTimeImmutable;

/**
 * The only class allowed to read the wall clock.
 */
final class Clock {

	/**
	 * Current time in the site zone, pinnable through the masthead_now filter.
	 *
	 * @return DateTimeImmutable
	 */
	public function now(): DateTimeImmutable {
		$zone = wp_timezone();
		$real = new DateTimeImmutable( 'now', $zone );
		/**
		 * Filters the current time. Tests pin it.
		 *
		 * @param DateTimeImmutable $real Real time.
		 */
		$filtered = apply_filters( 'masthead_now', $real );

		if ( $filtered instanceof DateTimeImmutable ) {
			return $filtered->setTimezone( $zone );
		}
		if ( is_string( $filtered ) ) {
			$parsed = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $filtered, $zone );
			if ( false !== $parsed ) {
				return $parsed;
			}
		}
		return $real;
	}

	/**
	 * Today's date in the site zone.
	 *
	 * @return string Y-m-d.
	 */
	public function today(): string {
		return $this->now()->format( 'Y-m-d' );
	}

	/**
	 * Formats a date with localised names.
	 *
	 * @param DateTimeImmutable $dt     Date.
	 * @param string            $format PHP date format.
	 * @return string
	 */
	public function format( DateTimeImmutable $dt, string $format ): string {
		return (string) wp_date( $format, $dt->getTimestamp(), wp_timezone() );
	}
}
