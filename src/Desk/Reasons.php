<?php
/**
 * "Why" lines.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Desk;

use Masthead\Support\Clock;
use Masthead\Support\Dates;

/**
 * Every non-AI "why" line is a template filled from WordPress or Jetpack data. No traffic number appears in any of them.
 */
class Reasons {

	/**
	 * Constructor.
	 *
	 * @param Clock $clock Clock, for localised weekday and month names.
	 */
	public function __construct( private Clock $clock ) {}

	/**
	 * The why line for a candidate.
	 *
	 * @param array  $candidate Candidate::to_array().
	 * @param string $date      Date of the slot, Y-m-d.
	 * @param array  $lane      Lane for the date.
	 * @return string
	 */
	public function for_candidate( array $candidate, string $date, array $lane ): string {
		$data    = $candidate['data'] ?? array();
		$weekday = $this->weekday( $date );
		switch ( $candidate['kind'] ?? '' ) {
			case 'draft':
				return sprintf(
					/* translators: 1: weekday name, 2: lane label, 3: date the draft was last edited, such as "Oct 29". */
					__( "%1\$s is your %2\$s slot. This draft isn't on the calendar; last edited %3\$s.", 'masthead' ),
					$weekday,
					(string) ( $lane['label'] ?? '' ),
					$this->short_iso( $data['modified'] ?? null )
				);
			case 'series':
				return sprintf(
					/* translators: 1: weekday name, 2: expected date, 3: number of weeks, 4: part number. */
					__( "%1\$s is due around %2\$s (every %3\$d weeks). Part %4\$d isn't on the calendar.", 'masthead' ),
					$weekday,
					$this->short_ymd( $data['expected'] ?? null ),
					(int) ( $data['every_weeks'] ?? 0 ),
					(int) ( $data['part'] ?? 0 )
				);
			case 'reshare':
				return sprintf(
					/* translators: 1: weekday name, 2: lane label, 3: date the post was published. */
					__( '%1$s is your %2$s slot and nothing new is queued. Published %3$s; it ranks among your most viewed posts in Jetpack Stats.', 'masthead' ),
					$weekday,
					(string) ( $lane['label'] ?? '' ),
					$this->short_ymd( $data['published'] ?? null, true )
				);
			case 'gap':
				$name = (string) ( $data['category_name'] ?? '' );
				if ( empty( $data['last_published'] ) ) {
					return sprintf(
						/* translators: %s: category name. */
						__( 'Nothing has been published under %s yet.', 'masthead' ),
						$name
					);
				}
				return sprintf(
					/* translators: 1: category name, 2: date of the last post, such as "Sep 1". */
					__( 'Nothing has been published under %1$s since %2$s.', 'masthead' ),
					$name,
					$this->short_ymd( $data['last_published'] )
				);
		}
		return '';
	}

	/**
	 * Localised weekday name of a date.
	 *
	 * @param string $date Date, Y-m-d.
	 * @return string
	 */
	private function weekday( string $date ): string {
		$parsed = Dates::parse_date( $date );
		return null === $parsed ? '' : $this->clock->format( $parsed, 'l' );
	}

	/**
	 * A Y-m-d date as "M j", or "M j, Y".
	 *
	 * @param mixed $ymd  Date.
	 * @param bool  $year Whether to add the year.
	 * @return string
	 */
	private function short_ymd( $ymd, bool $year = false ): string {
		$parsed = is_string( $ymd ) ? Dates::parse_date( $ymd ) : null;
		return null === $parsed ? '' : $this->clock->format( $parsed, $year ? 'M j, Y' : 'M j' );
	}

	/**
	 * An ISO 8601 datetime as "M j".
	 *
	 * @param mixed $iso Datetime.
	 * @return string
	 */
	private function short_iso( $iso ): string {
		$parsed = is_string( $iso ) ? Dates::parse_iso( $iso ) : null;
		return null === $parsed ? '' : $this->clock->format( $parsed, 'M j' );
	}
}
