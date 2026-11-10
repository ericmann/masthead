<?php
/**
 * Candidate source: the next part of a series.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Desk\Candidates;

use Masthead\Config;
use Masthead\Plan\Cadence;
use Masthead\Plan\SeriesSource;
use Masthead\Support\Categories;
use Masthead\Support\Dates;

/**
 * Suggests the next part of a series when its cadence lands on or near the date.
 */
final class SeriesNext {

	/**
	 * Constructor.
	 *
	 * @param SeriesSource $series Series adapter.
	 */
	public function __construct( private SeriesSource $series ) {}

	/**
	 * Candidates for a date.
	 *
	 * @param string $date Date, Y-m-d.
	 * @param array  $lane Lane of that day. Unused: a series part is due by its cadence, not its lane.
	 * @return Candidate[]
	 */
	public function for_date( string $date, array $lane ): array {
		$window     = (int) Config::get( 'desk.series_window_days' );
		$candidates = array();
		foreach ( $this->series->terms() as $term ) {
			$term_id = (int) $term->term_id;
			$cadence = $this->series->cadence( $term_id );
			if ( null === $cadence ) {
				continue;
			}
			$parts = $this->series->parts( $term_id );
			$dated = array_filter( $parts, static fn( $part ) => $part['dated'] && null !== $part['part'] );
			if ( array() === $dated ) {
				continue;
			}
			usort( $dated, static fn( $a, $b ) => $b['part'] <=> $a['part'] );
			$last = $dated[0];
			$next = $last['part'] + 1;

			$taken = array_filter( $dated, static fn( $part ) => $part['part'] === $next );
			if ( array() !== $taken ) {
				continue;
			}
			$total = $this->series->for_post( $last['post_id'] )['total'] ?? null;
			if ( null !== $total && $next > $total ) {
				continue;
			}
			$last_date = Dates::parse_local( (string) $last['date'] );
			if ( null === $last_date ) {
				continue;
			}
			$expected = Cadence::expected_date( Dates::to_ymd( $last_date ), $cadence );
			$distance = abs( Dates::diff_days( $expected, $date ) );
			if ( $distance > $window ) {
				continue;
			}

			$holder = null;
			foreach ( $parts as $part ) {
				if ( ! $part['dated'] && $part['part'] === $next ) {
					$holder = $part;
					break;
				}
			}
			$name  = (string) $term->name;
			$title = null === $holder
				? sprintf(
					/* translators: 1: series name, 2: part number. */
					__( '%1$s: Part %2$d', 'masthead' ),
					$name,
					$next
				)
				: get_the_title( $holder['post_id'] );

			$fields = array(
				'sort_id'  => $term_id,
				'tiebreak' => $name,
				'category' => Categories::for_post( $last['post_id'] )['primary'],
				'series'   => array(
					'term_id' => $term_id,
					'part'    => $next,
				),
				'data'     => array(
					'expected'    => $expected,
					'every_weeks' => $cadence['every_weeks'],
					'series_name' => $name,
					'part'        => $next,
				),
			);
			if ( null !== $holder ) {
				$fields['post_id'] = $holder['post_id'];
			}
			$candidates[] = Candidate::make( 'series', sprintf( 'series:%d:%d', $term_id, $next ), (string) $title, 1 - $distance / ( $window + 1 ), $fields );
		}
		return $candidates;
	}
}
