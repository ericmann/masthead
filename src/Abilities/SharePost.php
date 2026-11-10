<?php
/**
 * Ability masthead/share-post.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Abilities;

use Masthead\Config;
use Masthead\Social\ShareSource;
use Masthead\Support\Clock;
use Masthead\Support\Dates;
use WP_Error;

/**
 * Shares a published post now or at a time, through Jetpack Social only.
 */
final class SharePost implements Ability {

	/**
	 * HTTP statuses. Protocol values, not tunables.
	 */
	private const BAD_REQUEST = 400;
	private const NOT_FOUND   = 404;
	private const CONFLICT    = 409;
	private const BAD_GATEWAY = 502;

	/**
	 * Constructor.
	 *
	 * @param ShareSource $share Share source.
	 * @param Clock       $clock Clock.
	 */
	public function __construct( private ShareSource $share, private Clock $clock ) {}

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'masthead/share-post';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Share post', 'masthead' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'Shares a published post to chosen Jetpack Social connections now or at a scheduled time. Refuses posts that are not published.', 'masthead' );
	}

	/**
	 * Input schema.
	 *
	 * @return array<string,mixed>
	 */
	public function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'post_id'        => array( 'type' => 'integer' ),
				'message'        => array( 'type' => 'string' ),
				'connection_ids' => array(
					'type'  => 'array',
					'items' => array( 'type' => 'integer' ),
				),
				'at'             => array( 'type' => 'string' ),
			),
			'required'             => array( 'post_id', 'message', 'connection_ids' ),
			'additionalProperties' => false,
		);
	}

	/**
	 * Output schema.
	 *
	 * @return array<string,mixed>
	 */
	public function output_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'status'  => array( 'type' => 'string' ),
				'results' => array(
					'type'  => 'array',
					'items' => array( 'type' => 'object' ),
				),
			),
			'required'   => array( 'status', 'results' ),
		);
	}

	/**
	 * Annotations.
	 *
	 * @return array{readonly:bool,destructive:bool,idempotent:bool}
	 */
	public function annotations(): array {
		return array(
			'readonly'    => false,
			'destructive' => false,
			'idempotent'  => false,
		);
	}

	/**
	 * Permission.
	 *
	 * @param mixed $input Input.
	 * @return bool
	 */
	public function permission( $input ): bool {
		return current_user_can( 'publish_posts' );
	}

	/**
	 * Runs the ability.
	 *
	 * @param mixed $input { post_id, message, connection_ids, at? }.
	 * @return array|WP_Error
	 */
	public function execute( $input ) {
		$input   = is_array( $input ) ? $input : array();
		$post_id = (int) ( $input['post_id'] ?? 0 );
		$post    = get_post( $post_id );
		if ( ! is_object( $post ) ) {
			return $this->error( 'masthead_not_found', __( 'That post does not exist.', 'masthead' ), self::NOT_FOUND );
		}
		if ( 'publish' !== $post->post_status ) {
			return $this->error( 'masthead_not_published', __( 'Only published posts can be shared.', 'masthead' ), self::CONFLICT );
		}
		$message = trim( sanitize_textarea_field( (string) ( $input['message'] ?? '' ) ) );
		$chosen  = array_values( array_unique( array_map( 'intval', (array) ( $input['connection_ids'] ?? array() ) ) ) );
		if ( '' === $message || array() === $chosen ) {
			return $this->error( 'masthead_invalid_input', __( 'A share needs a message and at least one connection.', 'masthead' ), self::BAD_REQUEST );
		}
		$known = array_column( $this->share->connections(), 'id' );
		if ( array() !== array_diff( $chosen, $known ) ) {
			return $this->error( 'masthead_unknown_connection', __( 'One of the connections is not available.', 'masthead' ), self::BAD_REQUEST );
		}

		$at = $input['at'] ?? null;
		if ( is_string( $at ) && '' !== $at ) {
			$when = Dates::parse_iso( $at );
			if ( null === $when || $when <= $this->clock->now() ) {
				return $this->error( 'masthead_invalid_date', __( 'The share time must be a date and time with an offset, in the future.', 'masthead' ), self::BAD_REQUEST );
			}
			$result = $this->share->share_at( $post_id, $message, $chosen, $at );
		} else {
			$result = $this->share->share_now( $post_id, $message, $chosen );
		}

		$status = (string) ( $result['status'] ?? 'error' );
		if ( 'shared' !== $status && 'queued' !== $status ) {
			$reason = (string) ( $result['results'][0]['message'] ?? '' );
			return 'unavailable' === $status
				? $this->error( 'masthead_share_unavailable', $reason, self::CONFLICT )
				: $this->error( 'masthead_share_failed', $reason, self::BAD_GATEWAY );
		}

		if ( 'shared' === $status ) {
			update_post_meta( $post_id, 'masthead_last_shared', Dates::to_iso( $this->clock->now() ) );
		}
		/**
		 * Fires after a share was made or queued.
		 *
		 * @param int   $post_id Post ID.
		 * @param array $result  Share result.
		 */
		do_action( 'masthead_shared', $post_id, $result );
		return $result;
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
