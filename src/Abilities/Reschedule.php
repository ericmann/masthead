<?php
/**
 * Ability masthead/reschedule.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Abilities;

use Masthead\Plan\Reschedule as PlanReschedule;
use WP_Error;

/**
 * Moves a scheduled post or draft to another date.
 */
final class Reschedule implements Ability {

	/**
	 * Constructor.
	 *
	 * @param PlanReschedule $reschedule Reschedule service.
	 */
	public function __construct( private PlanReschedule $reschedule ) {}

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'masthead/reschedule';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Reschedule', 'masthead' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'Moves a scheduled post or a draft to another date and time. Never changes the status; refuses published posts.', 'masthead' );
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
				'date'    => array( 'type' => 'string' ),
				'time'    => array( 'type' => 'string' ),
			),
			'required'             => array( 'post_id', 'date' ),
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
				'post_id' => array( 'type' => 'integer' ),
				'date'    => array( 'type' => 'string' ),
				'status'  => array( 'type' => 'string' ),
			),
			'required'   => array( 'post_id', 'date', 'status' ),
		);
	}

	/**
	 * Annotations. Idempotent but not destructive, so the REST route is POST.
	 *
	 * @return array{readonly:bool,destructive:bool,idempotent:bool}
	 */
	public function annotations(): array {
		return array(
			'readonly'    => false,
			'destructive' => false,
			'idempotent'  => true,
		);
	}

	/**
	 * Permission: edit_post on that post.
	 *
	 * @param mixed $input Input.
	 * @return bool
	 */
	public function permission( $input ): bool {
		$post_id = is_array( $input ) ? (int) ( $input['post_id'] ?? 0 ) : 0;
		return $post_id > 0 && current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Runs the ability.
	 *
	 * @param mixed $input { post_id, date, time? }.
	 * @return array|WP_Error
	 */
	public function execute( $input ) {
		$input = is_array( $input ) ? $input : array();
		$time  = isset( $input['time'] ) && is_string( $input['time'] ) && '' !== $input['time'] ? $input['time'] : null;
		return $this->reschedule->move( (int) ( $input['post_id'] ?? 0 ), (string) ( $input['date'] ?? '' ), $time );
	}
}
