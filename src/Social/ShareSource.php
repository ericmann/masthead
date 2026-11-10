<?php
/**
 * Share source contract.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Social;

/**
 * Sharing goes through a ShareSource or is unavailable. Nothing is emulated.
 *
 * Both share methods return { status, results }: status is shared, queued, unavailable or error,
 * and results is a list of { connection_id, ok, message }. For unavailable and error the first
 * result's message is the reason.
 */
interface ShareSource {

	/**
	 * Whether sharing can be attempted at all.
	 *
	 * @return bool
	 */
	public function available(): bool;

	/**
	 * Connected accounts.
	 *
	 * @return list<array{id:int,service:string,display_name:string}>
	 */
	public function connections(): array;

	/**
	 * Shares a published post now.
	 *
	 * @param int    $post_id        Post ID.
	 * @param string $message        Message.
	 * @param array  $connection_ids Connection IDs to share to.
	 * @return array{status:string,results:array}
	 */
	public function share_now( int $post_id, string $message, array $connection_ids ): array;

	/**
	 * Schedules a share of a published post.
	 *
	 * @param int    $post_id        Post ID.
	 * @param string $message        Message.
	 * @param array  $connection_ids Connection IDs to share to.
	 * @param string $iso_datetime   ISO 8601 datetime with offset.
	 * @return array{status:string,results:array}
	 */
	public function share_at( int $post_id, string $message, array $connection_ids, string $iso_datetime ): array;
}
