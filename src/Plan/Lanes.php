<?php
/**
 * Which lane a date belongs to.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Plan;

use Masthead\Support\Dates;

/**
 * Lane lookups over the weekly rhythm.
 */
final class Lanes {

	/**
	 * The lane for a date.
	 *
	 * @param string $ymd Date.
	 * @return array{slug:string,label:string,categories:string[],time:string,weekday:int}
	 */
	public static function for_date( string $ymd ): array {
		$weekday = Dates::weekday( $ymd );
		$day     = Rhythm::get()[ $weekday ];
		$lane    = array(
			'slug'       => $day['lane'],
			'label'      => $day['label'],
			'categories' => $day['categories'],
			'time'       => $day['time'],
			'weekday'    => $weekday,
		);
		/**
		 * Filters the lane for a date.
		 *
		 * @param array  $lane Lane.
		 * @param string $ymd  Date.
		 */
		$filtered = apply_filters( 'masthead_lane_for_date', $lane, $ymd );
		return is_array( $filtered ) ? $filtered : $lane;
	}

	/**
	 * The lane term of the swap score, without the series exception.
	 *
	 * @param string|null $primary    Primary category slug.
	 * @param string[]    $categories All category slugs of the post.
	 * @param array       $lane       Lane.
	 * @return float
	 */
	public static function fit( ?string $primary, array $categories, array $lane ): float {
		$accepted = $lane['categories'] ?? array();
		if ( array() === $accepted || ( null !== $primary && in_array( $primary, $accepted, true ) ) ) {
			return 1.0;
		}
		if ( array() !== array_intersect( $categories, $accepted ) ) {
			return 0.5;
		}
		return 0.0;
	}

	/**
	 * The nearest other date within the window whose lane explicitly lists the category.
	 *
	 * @param string $ymd      Date.
	 * @param string $category Category slug.
	 * @param int    $window   Days either side.
	 * @return string|null
	 */
	public static function nearest_date_for_category( string $ymd, string $category, int $window ): ?string {
		for ( $offset = 1; $offset <= $window; $offset++ ) {
			foreach ( array( -$offset, $offset ) as $delta ) {
				$candidate = Dates::add_days( $ymd, $delta );
				if ( '' !== $candidate && in_array( $category, self::for_date( $candidate )['categories'], true ) ) {
					return $candidate;
				}
			}
		}
		return null;
	}
}
