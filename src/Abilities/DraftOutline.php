<?php
/**
 * Ability masthead/draft-outline.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Abilities;

use Masthead\Desk\Ideas;
use Masthead\Support\Dates;
use WP_Error;

/**
 * An AI-written outline for a post title. Never a body.
 */
final class DraftOutline implements Ability {

	/**
	 * HTTP status for a bad request. Protocol value, not a tunable.
	 */
	private const BAD_REQUEST = 400;

	/**
	 * Constructor.
	 *
	 * @param Ideas $ideas Ideas service.
	 */
	public function __construct( private Ideas $ideas ) {}

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'masthead/draft-outline';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Draft an outline', 'masthead' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'Asks the AI for a short outline (section headings only) for a post title. Needs an AI provider. Nothing is saved.', 'masthead' );
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
				'title'    => array( 'type' => 'string' ),
				'category' => array( 'type' => 'string' ),
				'date'     => array( 'type' => 'string' ),
			),
			'required'             => array( 'title' ),
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
				'outline' => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
			),
			'required'   => array( 'outline' ),
		);
	}

	/**
	 * Annotations. Read-only: it saves nothing, even though it calls the AI.
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
	 * @param mixed $input { title, category?, date? }.
	 * @return array|WP_Error
	 */
	public function execute( $input ) {
		$input = is_array( $input ) ? $input : array();
		$title = trim( is_string( $input['title'] ?? null ) ? $input['title'] : '' );
		if ( '' === $title ) {
			return new WP_Error( 'masthead_invalid_title', __( 'A title is required.', 'masthead' ), array( 'status' => self::BAD_REQUEST ) );
		}
		$date = is_string( $input['date'] ?? null ) && '' !== $input['date'] ? $input['date'] : null;
		if ( null !== $date && ! Dates::is_date( $date ) ) {
			return new WP_Error( 'masthead_invalid_date', __( 'The date must be a real YYYY-MM-DD date.', 'masthead' ), array( 'status' => self::BAD_REQUEST ) );
		}
		$category = is_string( $input['category'] ?? null ) && '' !== $input['category'] ? $input['category'] : null;

		$outline = $this->ideas->outline( $title, $category, $date );
		return $outline instanceof WP_Error ? $outline : array( 'outline' => $outline );
	}
}
