<?php
/**
 * Ability masthead/get-plan.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Abilities;

use Masthead\Config;
use Masthead\Plan\PlanReader;
use Masthead\Support\Dates;
use WP_Error;

/**
 * Returns the plan for a date range.
 */
final class GetPlan implements Ability {

	/**
	 * HTTP status for a bad request. Protocol value, not a tunable.
	 */
	private const BAD_REQUEST = 400;

	/**
	 * Constructor.
	 *
	 * @param PlanReader $reader Plan reader.
	 */
	public function __construct( private PlanReader $reader ) {}

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'masthead/get-plan';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Get plan', 'masthead' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'Returns every day in a date range with its lane and posts, plus the unscheduled drafts.', 'masthead' );
	}

	/**
	 * Input schema.
	 *
	 * @return array<string,mixed>
	 */
	public function input_schema(): array {
		$date = array(
			'type'    => 'string',
			'pattern' => '^\d{4}-\d{2}-\d{2}$',
		);
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'from' => $date,
				'to'   => $date,
			),
			'required'             => array( 'from', 'to' ),
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
				'days'        => array(
					'type'  => 'array',
					'items' => array( 'type' => 'object' ),
				),
				'unscheduled' => array(
					'type'  => 'array',
					'items' => array( 'type' => 'object' ),
				),
				'truncated'   => array( 'type' => 'boolean' ),
			),
			'required'   => array( 'days', 'unscheduled', 'truncated' ),
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
	 * @param mixed $input { from, to }.
	 * @return array|WP_Error
	 */
	public function execute( $input ) {
		$from = is_array( $input ) && is_string( $input['from'] ?? null ) ? $input['from'] : '';
		$to   = is_array( $input ) && is_string( $input['to'] ?? null ) ? $input['to'] : '';
		if ( ! Dates::is_date( $from ) || ! Dates::is_date( $to ) ) {
			return new WP_Error( 'masthead_invalid_date', __( 'Dates must be real YYYY-MM-DD dates.', 'masthead' ), array( 'status' => self::BAD_REQUEST ) );
		}
		$span = Dates::diff_days( $from, $to );
		if ( $span < 0 ) {
			return new WP_Error( 'masthead_invalid_date', __( 'The end date is before the start date.', 'masthead' ), array( 'status' => self::BAD_REQUEST ) );
		}
		// The range is inclusive, so it holds one day more than the difference.
		if ( $span >= (int) Config::get( 'calendar.max_range_days' ) ) {
			return new WP_Error( 'masthead_range_too_long', __( 'That range is longer than the calendar allows.', 'masthead' ), array( 'status' => self::BAD_REQUEST ) );
		}
		return $this->reader->range( $from, $to );
	}
}
