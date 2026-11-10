<?php
/**
 * Raw stats payloads to interface shapes.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Stats;

use Masthead\Support\Dates;

/**
 * Pure mapping of the payload shapes documented in docs/spikes/P3-01-jetpack-stats.md. Malformed input is null, never a guess.
 */
final class Payloads {

	/**
	 * Average daily views per weekday from a visits payload.
	 *
	 * @param array $raw Visits payload: { fields: [ 'period', 'views', ... ], data: [ [ date, views, ... ], ... ] }.
	 * @return array<int,float>|null Weekday to average. A weekday with no rows in the window is absent.
	 */
	public static function by_weekday( array $raw ): ?array {
		$fields = $raw['fields'] ?? null;
		$rows   = $raw['data'] ?? null;
		if ( ! is_array( $fields ) || ! is_array( $rows ) || array() === $rows ) {
			return null;
		}
		$period = array_search( 'period', $fields, true );
		$views  = array_search( 'views', $fields, true );
		if ( false === $period || false === $views ) {
			return null;
		}
		$sums   = array();
		$counts = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || ! isset( $row[ $period ], $row[ $views ] ) || ! is_string( $row[ $period ] ) || ! is_int( $row[ $views ] ) || $row[ $views ] < 0 || ! Dates::is_date( $row[ $period ] ) ) {
				return null;
			}
			$weekday            = Dates::weekday( $row[ $period ] );
			$sums[ $weekday ]   = ( $sums[ $weekday ] ?? 0 ) + $row[ $views ];
			$counts[ $weekday ] = ( $counts[ $weekday ] ?? 0 ) + 1;
		}
		ksort( $sums );
		$averages = array();
		foreach ( $sums as $weekday => $sum ) {
			$averages[ $weekday ] = (float) ( $sum / $counts[ $weekday ] );
		}
		return $averages;
	}

	/**
	 * Most viewed posts from a top-posts payload, summed over every day it holds.
	 *
	 * @param array $raw   Top-posts payload: { days: { date: { postviews: [ { id, views } ] } } } or { summary: { postviews: [...] } }.
	 * @param int   $limit Maximum posts.
	 * @return array<int,int>|null Post ID to views, highest first, ties by post ID.
	 */
	public static function top_posts( array $raw, int $limit ): ?array {
		$blocks = array();
		if ( isset( $raw['days'] ) && is_array( $raw['days'] ) ) {
			$blocks = array_values( $raw['days'] );
		} elseif ( isset( $raw['summary'] ) && is_array( $raw['summary'] ) ) {
			$blocks = array( $raw['summary'] );
		} else {
			return null;
		}
		$totals = array();
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) || ! isset( $block['postviews'] ) || ! is_array( $block['postviews'] ) ) {
				return null;
			}
			foreach ( $block['postviews'] as $entry ) {
				if ( ! is_array( $entry ) || ! isset( $entry['id'], $entry['views'] ) || ! is_int( $entry['id'] ) || ! is_int( $entry['views'] ) || $entry['views'] < 0 ) {
					return null;
				}
				$totals[ $entry['id'] ] = ( $totals[ $entry['id'] ] ?? 0 ) + $entry['views'];
			}
		}
		uksort(
			$totals,
			static fn( $a, $b ) => array( $totals[ $b ], $a ) <=> array( $totals[ $a ], $b )
		);
		return array_slice( $totals, 0, max( 0, $limit ), true );
	}

	/**
	 * Views of one post.
	 *
	 * @param array $raw Post payload with an integer views key.
	 * @return int|null
	 */
	public static function post_views( array $raw ): ?int {
		$views = $raw['views'] ?? null;
		return is_int( $views ) && $views >= 0 ? $views : null;
	}

	/**
	 * Views per primary category, summed from top posts. A post without a primary category adds nothing.
	 *
	 * @param array<int,int>        $top_posts  Post ID to views.
	 * @param callable(int):?string $primary_of Returns a post's primary category slug.
	 * @return array<string,int>
	 */
	public static function by_category_from_top_posts( array $top_posts, callable $primary_of ): array {
		$by_category = array();
		foreach ( $top_posts as $post_id => $views ) {
			$slug = $primary_of( (int) $post_id );
			if ( null === $slug || '' === $slug ) {
				continue;
			}
			$by_category[ $slug ] = ( $by_category[ $slug ] ?? 0 ) + $views;
		}
		arsort( $by_category );
		return $by_category;
	}
}
