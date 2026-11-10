<?php
/**
 * Rescheduling.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Plan;

use Masthead\Config;
use Masthead\Support\Clock;
use Masthead\Support\Dates;
use WP_Error;

/**
 * Moves a future post or a draft to another date without changing its status.
 * This file holds the only wp_update_post call in the plugin.
 */
final class Reschedule {

	/**
	 * HTTP statuses. Protocol values, not tunables.
	 */
	private const BAD_REQUEST = 400;
	private const NOT_FOUND   = 404;
	private const CONFLICT    = 409;

	/**
	 * Constructor.
	 *
	 * @param Clock $clock Clock.
	 */
	public function __construct( private Clock $clock ) {}

	/**
	 * Moves a post.
	 *
	 * @param int         $post_id Post ID.
	 * @param string      $date    Target date, Y-m-d.
	 * @param string|null $time    Target time, HH:MM, or null to keep the current time.
	 * @return array{post_id:int,date:string,status:string}|WP_Error
	 */
	public function move( int $post_id, string $date, ?string $time ) {
		$post = get_post( $post_id );
		if ( ! is_object( $post ) || ! in_array( $post->post_type, (array) Config::get( 'post_types' ), true ) ) {
			return $this->error( 'masthead_not_found', __( 'That post does not exist.', 'masthead' ), self::NOT_FOUND );
		}
		$status = (string) $post->post_status;
		if ( 'publish' === $status ) {
			return $this->error( 'masthead_published_locked', __( 'Published posts cannot be rescheduled.', 'masthead' ), self::CONFLICT );
		}
		if ( 'future' !== $status && 'draft' !== $status ) {
			return $this->error( 'masthead_unsupported_status', __( 'Only scheduled posts and drafts can be rescheduled.', 'masthead' ), self::CONFLICT );
		}
		if ( ! Dates::is_date( $date ) || ( null !== $time && ! Dates::is_time( $time ) ) ) {
			return $this->error( 'masthead_invalid_date', __( 'The date must be YYYY-MM-DD and the time HH:MM.', 'masthead' ), self::BAD_REQUEST );
		}

		$dated = Slots::is_dated( $post );
		$old   = $dated ? (string) $post->post_date : null;
		if ( null === $time ) {
			$time = $dated ? Dates::time_of( (string) $post->post_date ) : Lanes::for_date( $date )['time'];
		}
		$local  = Dates::combine( $date, $time );
		$target = Dates::parse_local( $local );

		if ( 'future' === $status && ( null === $target || $target <= $this->clock->now() ) ) {
			return $this->error( 'masthead_past_date', __( 'A scheduled post needs a date in the future.', 'masthead' ), self::BAD_REQUEST );
		}
		if ( $old === $local ) {
			return $this->result( $post_id, $local, $status );
		}

		$pin = static function ( $data ) use ( $status ) {
			$data['post_status'] = $status;
			return $data;
		};
		add_filter( 'wp_insert_post_data', $pin, PHP_INT_MAX );
		try {
			$updated = wp_update_post(
				array(
					'ID'            => $post_id,
					'post_date'     => $local,
					'post_date_gmt' => Dates::local_to_gmt( $local ),
					'edit_date'     => true,
				),
				true
			);
		} finally {
			remove_filter( 'wp_insert_post_data', $pin, PHP_INT_MAX );
		}
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		/**
		 * Fires after a post is rescheduled.
		 *
		 * @param int         $post_id Post ID.
		 * @param string|null $old     Previous local datetime, null when the draft was undated.
		 * @param string      $new     New local datetime.
		 */
		do_action( 'masthead_rescheduled', $post_id, $old, $local );
		return $this->result( $post_id, $local, $status );
	}

	/**
	 * Builds the result.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $local   Local datetime.
	 * @param string $status  Status.
	 * @return array{post_id:int,date:string,status:string}
	 */
	private function result( int $post_id, string $local, string $status ): array {
		$parsed = Dates::parse_local( $local );
		return array(
			'post_id' => $post_id,
			'date'    => null === $parsed ? '' : Dates::to_iso( $parsed ),
			'status'  => $status,
		);
	}

	/**
	 * Builds an error.
	 *
	 * @param string $code    Error code.
	 * @param string $message Message.
	 * @param int    $status  HTTP status.
	 * @return WP_Error
	 */
	private function error( string $code, string $message, int $status ): WP_Error {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}
}
