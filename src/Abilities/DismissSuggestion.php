<?php
/**
 * Ability masthead/dismiss-suggestion.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Abilities;

use Masthead\Desk\Dismissals;
use Masthead\Support\Dates;
use WP_Error;

/**
 * Dismisses a suggestion for one user and one date.
 */
final class DismissSuggestion implements Ability {

	/**
	 * HTTP status for a bad request. Protocol value, not a tunable.
	 */
	private const BAD_REQUEST = 400;

	/**
	 * Constructor.
	 *
	 * @param Dismissals $dismissals Dismissals.
	 */
	public function __construct( private Dismissals $dismissals ) {}

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'masthead/dismiss-suggestion';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Dismiss suggestion', 'masthead' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'Hides a Desk suggestion for the current user on one date. Repeating the call changes nothing.', 'masthead' );
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
				'date' => array( 'type' => 'string' ),
				'key'  => array( 'type' => 'string' ),
			),
			'required'             => array( 'date', 'key' ),
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
			'properties' => array( 'ok' => array( 'type' => 'boolean' ) ),
			'required'   => array( 'ok' ),
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
	 * @param mixed $input { date, key }.
	 * @return array|WP_Error
	 */
	public function execute( $input ) {
		$input = is_array( $input ) ? $input : array();
		$date  = is_string( $input['date'] ?? null ) ? $input['date'] : '';
		$key   = is_string( $input['key'] ?? null ) ? trim( $input['key'] ) : '';
		if ( ! Dates::is_date( $date ) || '' === $key ) {
			return new WP_Error( 'masthead_invalid_input', __( 'A dismissal needs a real date and a suggestion key.', 'masthead' ), array( 'status' => self::BAD_REQUEST ) );
		}
		$this->dismissals->dismiss( get_current_user_id(), $date, $key );
		return array( 'ok' => true );
	}
}
