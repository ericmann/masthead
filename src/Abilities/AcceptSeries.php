<?php
/**
 * Ability masthead/accept-series.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Abilities;

use Masthead\Desk\SeriesPlanner;
use WP_Error;

/**
 * Creates a proposed series: the term, its cadence and one dated draft per part. Uses no AI.
 */
final class AcceptSeries implements Ability {

	/**
	 * Constructor.
	 *
	 * @param SeriesPlanner $planner Planner.
	 */
	public function __construct( private SeriesPlanner $planner ) {}

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'masthead/accept-series';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Accept a series', 'masthead' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'Creates a series with its cadence and one dated, planned draft per part. All parts are created or none are.', 'masthead' );
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
				'name'     => array( 'type' => 'string' ),
				'category' => array( 'type' => 'string' ),
				'cadence'  => array(
					'type'       => 'object',
					'properties' => array(
						'every_weeks' => array( 'type' => 'integer' ),
						'weekday'     => array( 'type' => array( 'integer', 'null' ) ),
						'time'        => array( 'type' => 'string' ),
					),
				),
				'parts'    => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'date'    => array( 'type' => 'string' ),
							'title'   => array( 'type' => 'string' ),
							'outline' => array(
								'type'  => 'array',
								'items' => array( 'type' => 'string' ),
							),
						),
						'required'   => array( 'date', 'title' ),
					),
				),
			),
			'required'             => array( 'name', 'parts' ),
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
				'term_id'  => array( 'type' => 'integer' ),
				'post_ids' => array(
					'type'  => 'array',
					'items' => array( 'type' => 'integer' ),
				),
			),
			'required'   => array( 'term_id', 'post_ids' ),
		);
	}

	/**
	 * Annotations: writes, never destroys, not idempotent.
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
	 * @param mixed $input { name, parts, cadence?, category? }.
	 * @return array|WP_Error
	 */
	public function execute( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$name     = is_string( $input['name'] ?? null ) ? $input['name'] : '';
		$parts    = is_array( $input['parts'] ?? null ) ? $input['parts'] : array();
		$cadence  = is_array( $input['cadence'] ?? null ) ? $input['cadence'] : null;
		$category = is_string( $input['category'] ?? null ) ? $input['category'] : null;
		return $this->planner->accept( $name, $parts, $cadence, $category );
	}
}
