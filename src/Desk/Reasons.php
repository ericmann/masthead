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
					/* translators: 1: part number, 2: expected date, 3: number of weeks. */
					__( "Part %1\$d is due around %2\$s (every %3\$d weeks) and isn't on the calendar yet.", 'masthead' ),
					(int) ( $data['part'] ?? 0 ),
					$this->short_ymd( $data['expected'] ?? null ),
					(int) ( $data['every_weeks'] ?? 0 )
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
	 * The why line of a lane_mismatch finding.
	 *
	 * @param string $title Post title.
	 * @param string $date  Date of the post, Y-m-d.
	 * @param array  $lane  Lane for that date.
	 * @return string
	 */
	public function for_lane_mismatch( string $title, string $date, array $lane ): string {
		return sprintf(
			/* translators: 1: post title, 2: weekday name, 3: lane label. */
			__( '%1$s is on a %2$s, which your rhythm gives to %3$s.', 'masthead' ),
			$title,
			$this->weekday( $date ),
			(string) ( $lane['label'] ?? '' )
		);
	}

	/**
	 * The why line of a cadence_break finding.
	 *
	 * @param int    $part        Part number.
	 * @param string $date        Date of the part, Y-m-d.
	 * @param int    $every_weeks Cadence in weeks.
	 * @param string $expected    Expected date, Y-m-d.
	 * @return string
	 */
	public function for_cadence_break( int $part, string $date, int $every_weeks, string $expected ): string {
		return sprintf(
			/* translators: 1: part number, 2: its date, 3: weeks between parts, 4: the expected date. */
			__( 'Part %1$d is on %2$s; the series cadence (every %3$d weeks) expects %4$s.', 'masthead' ),
			$part,
			$this->short_ymd( $date ),
			$every_weeks,
			$this->short_ymd( $expected )
		);
	}

	/**
	 * The why line of a collision finding.
	 *
	 * @param string   $title  Post title.
	 * @param string   $date   Shared date, Y-m-d.
	 * @param string[] $others Titles of the other posts that day.
	 * @return string
	 */
	public function for_collision( string $title, string $date, array $others ): string {
		return sprintf(
			/* translators: 1: post title, 2: date, 3: titles of the other posts. */
			__( '%1$s shares %2$s with %3$s.', 'masthead' ),
			$title,
			$this->short_ymd( $date ),
			implode( ', ', $others )
		);
	}

	/**
	 * The why line of a better_candidate finding. It states no number.
	 *
	 * @param string $candidate Title of the better-fitting post or draft.
	 * @param string $weekday   Weekday name of the date.
	 * @param string $lane      Lane label of the date.
	 * @param string $scheduled Title of the post currently on the date.
	 * @return string
	 */
	public function for_better_candidate( string $candidate, string $weekday, string $lane, string $scheduled ): string {
		$slot = sprintf(
			/* translators: 1: weekday name, 2: lane label. */
			__( "%1\$s's %2\$s lane", 'masthead' ),
			$weekday,
			$lane
		);
		return sprintf(
			/* translators: 1: candidate title, 2: the weekday and lane, 3: scheduled post title. */
			__( '%1$s fits %2$s better than %3$s.', 'masthead' ),
			$candidate,
			$slot,
			$scheduled
		);
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
