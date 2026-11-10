<?php
/**
 * Candidate source: drafts that fit the day's lane.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Desk\Candidates;

use Masthead\Plan\Lanes;
use Masthead\Plan\PlanReader;

/**
 * Unscheduled drafts whose categories intersect the lane's.
 */
class DraftMatch {

	/**
	 * Constructor.
	 *
	 * @param PlanReader $reader Plan reader.
	 */
	public function __construct( private PlanReader $reader ) {}

	/**
	 * Candidates for a date.
	 *
	 * @param string $date Date, Y-m-d.
	 * @param array  $lane Lane for the date.
	 * @return Candidate[]
	 */
	public function for_date( string $date, array $lane ): array {
		$accepted   = $lane['categories'] ?? array();
		$candidates = array();
		foreach ( $this->reader->unscheduled() as $draft ) {
			$categories = $draft['categories'] ?? array();
			if ( array() !== $accepted && array() === array_intersect( $categories, $accepted ) ) {
				continue;
			}
			$candidates[] = Candidate::make(
				'draft',
				'draft:' . $draft['id'],
				(string) $draft['title'],
				Lanes::fit( $draft['primary_category'] ?? null, $categories, $lane ),
				array(
					'sort_id'  => $draft['id'],
					'tiebreak' => (string) $draft['title'],
					'category' => $draft['primary_category'] ?? null,
					'post_id'  => $draft['id'],
					'data'     => array(
						'modified' => $draft['modified'] ?? null,
						'edit_url' => $draft['edit_url'] ?? '',
					),
				)
			);
		}
		return $candidates;
	}
}
