<?php
/**
 * Ability masthead/draft-share-copy.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Abilities;

use Masthead\Desk\ShareCopy;
use WP_Error;

/**
 * An AI-drafted share message for a published post. Shares nothing.
 */
final class DraftShareCopy implements Ability {

	/**
	 * HTTP status for a bad request. Protocol value, not a tunable.
	 */
	private const BAD_REQUEST = 400;

	/**
	 * Constructor.
	 *
	 * @param ShareCopy $copy Share copy service.
	 */
	public function __construct( private ShareCopy $copy ) {}

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'masthead/draft-share-copy';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Draft share copy', 'masthead' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'Asks the AI for a short social message for a published post, from its title and excerpt. Needs an AI provider. Nothing is shared or saved.', 'masthead' );
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
				'post_id' => array( 'type' => 'integer' ),
			),
			'required'             => array( 'post_id' ),
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
				'message' => array( 'type' => 'string' ),
			),
			'required'   => array( 'message' ),
		);
	}

	/**
	 * Annotations. Read-only: it saves and shares nothing, even though it calls the AI.
	 *
	 * @return array{readonly:bool,destructive:bool,idempotent:bool}
	 */
	public function annotations(): array {
		return array(
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => false,
		);
	}

	/**
	 * Permission: edit_posts and prompt_ai.
	 *
	 * @param mixed $input Input.
	 * @return bool
	 */
	public function permission( $input ): bool {
		return current_user_can( 'edit_posts' ) && current_user_can( 'prompt_ai' );
	}

	/**
	 * Runs the ability.
	 *
	 * @param mixed $input { post_id }.
	 * @return array|WP_Error
	 */
	public function execute( $input ) {
		$post_id = is_array( $input ) && isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		if ( $post_id <= 0 ) {
			return new WP_Error( 'masthead_invalid_post', __( 'A post ID is required.', 'masthead' ), array( 'status' => self::BAD_REQUEST ) );
		}
		$message = $this->copy->draft( $post_id );
		return $message instanceof WP_Error ? $message : array( 'message' => $message );
	}
}
