<?php
/**
 * AI-drafted share copy.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Desk;

use Masthead\Ai\AiClient;
use Masthead\Ai\Prompts;
use Masthead\Ai\Schemas;
use Masthead\Config;

/**
 * Drafts a share message for a published post from its title and excerpt. Never shares.
 */
class ShareCopy {

	private const NOT_FOUND = 404;
	private const CONFLICT  = 409;

	/**
	 * Constructor.
	 *
	 * @param AiClient $ai The budgeted client.
	 */
	public function __construct( private AiClient $ai ) {}

	/**
	 * Drafts a message.
	 *
	 * @param int $post_id Post ID.
	 * @return string|\WP_Error
	 */
	public function draft( int $post_id ): string|\WP_Error {
		$post = get_post( $post_id );
		if ( ! is_object( $post ) || ! in_array( $post->post_type, (array) Config::get( 'post_types' ), true ) ) {
			return new \WP_Error( 'masthead_not_found', __( 'That post does not exist.', 'masthead' ), array( 'status' => self::NOT_FOUND ) );
		}
		if ( 'publish' !== $post->post_status ) {
			return new \WP_Error( 'masthead_not_published', __( 'Share copy can only be drafted for a published post.', 'masthead' ), array( 'status' => self::CONFLICT ) );
		}

		$excerpt = wp_trim_words( wp_strip_all_tags( (string) $post->post_excerpt ) );
		$prompt  = Prompts::share_copy( (string) $post->post_title, $excerpt );
		$schema  = Schemas::share_copy();

		$reply = $this->ai->json( $prompt['system'], $prompt['user'], $schema, 'fast' );
		if ( $reply instanceof \WP_Error ) {
			return $reply;
		}
		if ( true !== Schemas::validate( $reply, $schema ) ) {
			return new \WP_Error( 'masthead_ai_invalid', __( 'The AI reply did not match the expected shape.', 'masthead' ) );
		}
		return $reply['message'];
	}
}
