<?php
/**
 * Ability masthead/suggest-for-date.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Abilities;

use Masthead\Desk\Suggester;
use Masthead\Support\Dates;
use WP_Error;

/**
 * Suggestions for an empty day.
 */
final class SuggestForDate implements Ability {

	/**
	 * HTTP status for a bad request. Protocol value, not a tunable.
	 */
	private const BAD_REQUEST = 400;

	/**
	 * Constructor.
	 *
	 * @param Suggester $suggester Suggester.
	 */
	public function __construct( private Suggester $suggester ) {}

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'masthead/suggest-for-date';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Suggest for date', 'masthead' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'Returns the Desk suggestions for a date: drafts that fit, the next series part, posts worth sharing again and quiet categories, each with its reason.', 'masthead' );
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
				'date'          => array( 'type' => 'string' ),
				'include_ideas' => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'kinds'         => array(
					'type'  => 'array',
					'items' => array(
						'type' => 'string',
						'enum' => array( 'draft', 'series', 'reshare', 'gap', 'idea' ),
					),
				),
			),
			'required'             => array( 'date' ),
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
				'date'        => array( 'type' => 'string' ),
				'lane'        => array( 'type' => 'object' ),
				'suggestions' => array(
					'type'  => 'array',
					'items' => array( 'type' => 'object' ),
				),
				'ai'          => array( 'type' => 'object' ),
			),
			'required'   => array( 'date', 'lane', 'suggestions', 'ai' ),
		);
	}

	/**
	 * Annotations.
	 *
	 * @return array{readonly:bool,destructive:bool,idempotent:bool}
	 */
	public function annotations(): array {
		return array(
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
		);
	}

	/**
	 * Permission: edit_posts, plus prompt_ai when ideas are requested.
	 *
	 * @param mixed $input Input.
	 * @return bool
	 */
	public function permission( $input ): bool {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return false;
		}
		return empty( is_array( $input ) ? ( $input['include_ideas'] ?? false ) : false ) || current_user_can( 'prompt_ai' );
	}

	/**
	 * Runs the ability.
	 *
	 * @param mixed $input { date, include_ideas?, kinds? }.
	 * @return array|WP_Error
	 */
	public function execute( $input ) {
		$input = is_array( $input ) ? $input : array();
		$date  = is_string( $input['date'] ?? null ) ? $input['date'] : '';
		if ( ! Dates::is_date( $date ) ) {
			return new WP_Error( 'masthead_invalid_date', __( 'The date must be a real YYYY-MM-DD date.', 'masthead' ), array( 'status' => self::BAD_REQUEST ) );
		}
		$kinds = array_values( array_filter( (array) ( $input['kinds'] ?? array() ), 'is_string' ) );
		return $this->suggester->suggest( $date, get_current_user_id(), $kinds, ! empty( $input['include_ideas'] ) );
	}
}
