<?php
/**
 * Stats source contract.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Stats;

/**
 * Every traffic number comes from a StatsSource. Unavailable means null, never an estimate.
 */
interface StatsSource {

	/**
	 * Whether numbers can be read at all.
	 *
	 * @return bool
	 */
	public function available(): bool;

	/**
	 * Views per category over a number of days.
	 *
	 * @param int $days Window in days.
	 * @return array<string,int>|null Category slug to views.
	 */
	public function views_by_category( int $days ): ?array;

	/**
	 * Average daily views per weekday over a number of days.
	 *
	 * @param int $days Window in days.
	 * @return array<int,float>|null Weekday 0-6 to average daily views.
	 */
	public function views_by_weekday( int $days ): ?array;

	/**
	 * Most viewed posts over a number of days.
	 *
	 * @param int $days  Window in days.
	 * @param int $limit Maximum posts.
	 * @return array<int,int>|null Post ID to views, highest first.
	 */
	public function top_posts( int $days, int $limit ): ?array;

	/**
	 * Views of one post over a number of days.
	 *
	 * @param int $post_id Post ID.
	 * @param int $days    Window in days.
	 * @return int|null
	 */
	public function post_views( int $post_id, int $days ): ?int;

	/**
	 * When the numbers were fetched, ISO 8601.
	 *
	 * @return string|null
	 */
	public function fetched_at(): ?string;
}
