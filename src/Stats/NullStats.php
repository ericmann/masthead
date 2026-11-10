<?php
/**
 * Stats source with no numbers.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Stats;

/**
 * Used whenever no real source exists. Every number is null.
 */
final class NullStats implements StatsSource {

	/**
	 * Never available.
	 *
	 * @return bool
	 */
	public function available(): bool {
		return false;
	}

	/**
	 * Always null.
	 *
	 * @param int $days Window in days.
	 * @return null
	 */
	public function views_by_category( int $days ): ?array {
		return null;
	}

	/**
	 * Always null.
	 *
	 * @param int $days Window in days.
	 * @return null
	 */
	public function views_by_weekday( int $days ): ?array {
		return null;
	}

	/**
	 * Always null.
	 *
	 * @param int $days  Window in days.
	 * @param int $limit Maximum posts.
	 * @return null
	 */
	public function top_posts( int $days, int $limit ): ?array {
		return null;
	}

	/**
	 * Always null.
	 *
	 * @param int $post_id Post ID.
	 * @param int $days    Window in days.
	 * @return null
	 */
	public function post_views( int $post_id, int $days ): ?int {
		return null;
	}

	/**
	 * Always null.
	 *
	 * @return null
	 */
	public function fetched_at(): ?string {
		return null;
	}
}
