<?php
/**
 * Candidate source: categories that have gone quiet.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Desk\Candidates;

use Masthead\Config;
use Masthead\Support\Categories;
use Masthead\Support\Clock;
use Masthead\Support\Dates;

/**
 * Categories with no published post inside desk.gap_days.
 */
final class CategoryGap {

	/**
	 * Constructor.
	 *
	 * @param Clock $clock Clock.
	 */
	public function __construct( private Clock $clock ) {}

	/**
	 * Candidates for a date: the gaps among the lane's categories, or among every category for an open lane.
	 *
	 * @param string $date Date, Y-m-d.
	 * @param array  $lane Lane for the date.
	 * @return Candidate[]
	 */
	public function for_date( string $date, array $lane ): array {
		$accepted = $lane['categories'] ?? array();
		return array_values(
			array_filter(
				$this->gaps(),
				static fn( Candidate $candidate ) => array() === $accepted || in_array( $candidate->to_array()['category'] ?? '', $accepted, true )
			)
		);
	}

	/**
	 * Every gap, whatever the lane: never-published first, then the longest quiet, then by slug.
	 *
	 * @return Candidate[]
	 */
	public function gaps(): array {
		$gap_days = (int) Config::get( 'desk.gap_days' );
		$today    = $this->clock->today();
		$rows     = array();
		foreach ( Categories::all() as $category ) {
			$last = $this->last_published( $category['term_id'], $today );
			$days = null === $last ? null : Dates::diff_days( $last, $today );
			if ( null !== $days && $days <= $gap_days ) {
				continue;
			}
			$rows[] = array(
				'category' => $category,
				'last'     => $last,
				'days'     => $days,
			);
		}
		usort(
			$rows,
			static fn( $a, $b ) => array( null === $a['days'] ? 0 : 1, -( $a['days'] ?? 0 ), $a['category']['slug'] )
				<=> array( null === $b['days'] ? 0 : 1, -( $b['days'] ?? 0 ), $b['category']['slug'] )
		);

		$candidates = array();
		foreach ( $rows as $row ) {
			$category     = $row['category'];
			$candidates[] = Candidate::make(
				'gap',
				'gap:' . $category['term_id'],
				sprintf(
					/* translators: %s: category name. */
					__( 'Something under %s', 'masthead' ),
					$category['name']
				),
				null === $row['days'] ? 1.0 : $row['days'] / ( $row['days'] + $gap_days ),
				array(
					'sort_id'  => 0,
					'tiebreak' => $category['slug'],
					'category' => $category['slug'],
					'data'     => array(
						'last_published' => $row['last'],
						'category_name'  => $category['name'],
					),
				)
			);
		}
		return $candidates;
	}

	/**
	 * Date of the latest published post in a category on or before today.
	 *
	 * @param int    $term_id Category term ID.
	 * @param string $today   Today, Y-m-d.
	 * @return string|null
	 */
	private function last_published( int $term_id, string $today ): ?string {
		$posts = get_posts(
			array(
				'post_type'        => Config::get( 'post_types' ),
				'post_status'      => 'publish',
				'cat'              => $term_id,
				'numberposts'      => 1,
				'no_found_rows'    => true,
				'orderby'          => 'date',
				'order'            => 'DESC',
				'suppress_filters' => false,
				'date_query'       => array(
					array(
						'column'    => 'post_date',
						'before'    => $today . ' 23:59:59',
						'inclusive' => true,
					),
				),
			)
		);
		if ( ! is_array( $posts ) || array() === $posts ) {
			return null;
		}
		$parsed = Dates::parse_local( (string) $posts[0]->post_date );
		return null === $parsed ? null : Dates::to_ymd( $parsed );
	}
}
