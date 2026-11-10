<?php
/**
 * Ability masthead/create-planned-draft.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Abilities;

use Masthead\Plan\SeriesSource;
use Masthead\Plan\Slots;
use Masthead\Support\Dates;
use WP_Error;

/**
 * Creates a planned, dated draft.
 */
final class CreatePlannedDraft implements Ability {

	/**
	 * HTTP status for a bad request. Protocol value, not a tunable.
	 */
	private const BAD_REQUEST = 400;

	/**
	 * Constructor.
	 *
	 * @param Slots        $slots  Slots.
	 * @param SeriesSource $series Series adapter.
	 */
	public function __construct( private Slots $slots, private SeriesSource $series ) {}

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'masthead/create-planned-draft';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Create planned draft', 'masthead' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'Creates a draft with a fixed date and time, in a category, optionally as a series part with an outline.', 'masthead' );
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
				'date'     => array( 'type' => 'string' ),
				'time'     => array( 'type' => 'string' ),
				'title'    => array( 'type' => 'string' ),
				'category' => array( 'type' => 'string' ),
				'series'   => array(
					'type'                 => 'object',
					'properties'           => array(
						'term_id' => array( 'type' => 'integer' ),
						'part'    => array( 'type' => 'integer' ),
					),
					'required'             => array( 'term_id', 'part' ),
					'additionalProperties' => false,
				),
				'outline'  => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
			),
			'required'             => array( 'date', 'title', 'category' ),
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
				'post_id'  => array( 'type' => 'integer' ),
				'edit_url' => array( 'type' => 'string' ),
				'date'     => array( 'type' => 'string' ),
			),
			'required'   => array( 'post_id', 'edit_url', 'date' ),
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
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Runs the ability.
	 *
	 * @param mixed $input Input.
	 * @return array|WP_Error
	 */
	public function execute( $input ) {
		$input = is_array( $input ) ? $input : array();
		$title = sanitize_text_field( (string) ( $input['title'] ?? '' ) );
		if ( '' === $title ) {
			return new WP_Error( 'masthead_missing_title', __( 'A planned draft needs a title.', 'masthead' ), array( 'status' => self::BAD_REQUEST ) );
		}
		$input['title'] = $title;

		$series = $input['series'] ?? null;
		if ( is_array( $series ) ) {
			$term = get_term( (int) ( $series['term_id'] ?? 0 ), $this->series->taxonomy() );
			if ( ! is_object( $term ) || is_wp_error( $term ) ) {
				return new WP_Error( 'masthead_unknown_series', __( 'The series does not exist.', 'masthead' ), array( 'status' => self::BAD_REQUEST ) );
			}
		}

		$post_id = $this->slots->create( $input );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		$post   = get_post( $post_id );
		$parsed = is_object( $post ) ? Dates::parse_local( (string) $post->post_date ) : null;
		return array(
			'post_id'  => $post_id,
			'edit_url' => (string) get_edit_post_link( $post_id, 'raw' ),
			'date'     => null === $parsed ? '' : Dates::to_iso( $parsed ),
		);
	}
}
