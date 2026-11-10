<?php
/**
 * Ability masthead/propose-series.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Abilities;

use Masthead\Desk\SeriesPlanner;
use WP_Error;

/**
 * A proposed series with dated parts. Creates nothing.
 */
final class ProposeSeries implements Ability {

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
		return 'masthead/propose-series';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Propose a series', 'masthead' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'Asks the AI to propose a multi-part series from a request. Titles and outlines come from the AI; Masthead chooses the dates. Needs an AI provider. Nothing is created.', 'masthead' );
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
				'prompt' => array( 'type' => 'string' ),
				'start'  => array( 'type' => 'string' ),
			),
			'required'             => array( 'prompt' ),
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
				'name'     => array( 'type' => 'string' ),
				'category' => array( 'type' => 'string' ),
				'cadence'  => array( 'type' => 'object' ),
				'lane'     => array( 'type' => 'object' ),
				'parts'    => array(
					'type'  => 'array',
					'items' => array( 'type' => 'object' ),
				),
			),
			'required'   => array( 'name', 'category', 'cadence', 'lane', 'parts' ),
		);
	}

	/**
	 * Annotations. Read-only: it creates nothing, even though it calls the AI.
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
	 * @param mixed $input { prompt, start? }.
	 * @return array|WP_Error
	 */
	public function execute( $input ) {
		$input  = is_array( $input ) ? $input : array();
		$prompt = is_string( $input['prompt'] ?? null ) ? $input['prompt'] : '';
		$start  = is_string( $input['start'] ?? null ) && '' !== $input['start'] ? $input['start'] : null;
		return $this->planner->propose( $prompt, $start );
	}
}
