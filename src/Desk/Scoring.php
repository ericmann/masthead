<?php
/**
 * Swap-check scoring.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Desk;

use Masthead\Config;
use Masthead\Plan\Lanes;
use Masthead\Support\Dates;

/**
 * The score of a post on a date: a weighted mean of the lane, category and weekday terms that are present.
 * When statistics are unavailable the stats terms drop out and the lane term decides.
 */
final class Scoring {

	/**
	 * The three terms of a post on a date.
	 *
	 * @param array      $post               Post with categories and primary_category.
	 * @param string     $date               Date, Y-m-d.
	 * @param array      $lane               Lane for the date.
	 * @param bool       $lane_exception     Whether the post's series is exempt from lane mismatches.
	 * @param array|null $views_by_category  Category slug to views, or null.
	 * @param array|null $views_by_weekday   Weekday to average daily views, or null.
	 * @return array{lane:float,category:?float,weekday:?float}
	 */
	public function terms( array $post, string $date, array $lane, bool $lane_exception, ?array $views_by_category, ?array $views_by_weekday ): array {
		$primary = $post['primary_category'] ?? null;
		$fit     = $lane_exception ? 1.0 : Lanes::fit( $primary, (array) ( $post['categories'] ?? array() ), $lane );

		$category = null;
		if ( null !== $views_by_category && null !== $primary && array() !== $views_by_category ) {
			$most = max( $views_by_category );
			if ( $most > 0 ) {
				$category = (float) ( $views_by_category[ $primary ] ?? 0 ) / $most;
			}
		}

		$weekday = null;
		if ( null !== $views_by_weekday && array() !== $views_by_weekday ) {
			$most = max( $views_by_weekday );
			if ( $most > 0 ) {
				$weekday = (float) ( $views_by_weekday[ Dates::weekday( $date ) ] ?? 0 ) / $most;
			}
		}

		return array(
			'lane'     => $fit,
			'category' => $category,
			'weekday'  => $weekday,
		);
	}

	/**
	 * Weighted mean of the non-null terms, using swap.weights.
	 *
	 * @param array{lane:float,category:?float,weekday:?float} $terms Terms.
	 * @return float From 0 to 1.
	 */
	public function score( array $terms ): float {
		$weights = (array) Config::get( 'swap.weights' );
		$sum     = 0.0;
		$total   = 0.0;
		foreach ( array( 'lane', 'category', 'weekday' ) as $name ) {
			if ( null === ( $terms[ $name ] ?? null ) ) {
				continue;
			}
			$weight  = max( 0.0, (float) ( $weights[ $name ] ?? 0.0 ) );
			$sum    += $weight * (float) $terms[ $name ];
			$total  += $weight;
		}
		return $total > 0.0 ? min( 1.0, max( 0.0, $sum / $total ) ) : 0.0;
	}
}
