<?php
/**
 * Share source with no connection.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Social;

/**
 * Used whenever Jetpack Social is not usable.
 */
final class NullShare implements ShareSource {

	/**
	 * Never available.
	 *
	 * @return bool
	 */
	public function available(): bool {
		return false;
	}

	/**
	 * No connections.
	 *
	 * @return array
	 */
	public function connections(): array {
		return array();
	}

	/**
	 * Unavailable.
	 *
	 * @param int    $post_id        Post ID.
	 * @param string $message        Message.
	 * @param array  $connection_ids Connection IDs.
	 * @return array{status:string,results:array}
	 */
	public function share_now( int $post_id, string $message, array $connection_ids ): array {
		return $this->unavailable();
	}

	/**
	 * Unavailable.
	 *
	 * @param int    $post_id        Post ID.
	 * @param string $message        Message.
	 * @param array  $connection_ids Connection IDs.
	 * @param string $iso_datetime   ISO 8601 datetime.
	 * @return array{status:string,results:array}
	 */
	public function share_at( int $post_id, string $message, array $connection_ids, string $iso_datetime ): array {
		return $this->unavailable();
	}

	/**
	 * The unavailable result.
	 *
	 * @return array{status:string,results:array}
	 */
	private function unavailable(): array {
		return array(
			'status'  => 'unavailable',
			'results' => array(
				array(
					'connection_id' => 0,
					'ok'            => false,
					'message'       => __( 'Jetpack Social is not connected on this site.', 'masthead' ),
				),
			),
		);
	}
}
