<?php
/**
 * Ability masthead/share-connections.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Abilities;

use Masthead\Social\ShareSource;

/**
 * Lists the accounts Jetpack Social can share to.
 */
final class ShareConnections implements Ability {

	/**
	 * Constructor.
	 *
	 * @param ShareSource $share Share source.
	 */
	public function __construct( private ShareSource $share ) {}

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'masthead/share-connections';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Share connections', 'masthead' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'Lists the connected accounts Jetpack Social can share to, or says why sharing is unavailable.', 'masthead' );
	}

	/**
	 * Input schema.
	 *
	 * @return array<string,mixed>
	 */
	public function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(),
			'additionalProperties' => false,
			// A read-only ability is called with GET; an empty input is not sent, so null must mean the empty object.
			'default'              => array(),
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
				'available'   => array( 'type' => 'boolean' ),
				'connections' => array(
					'type'  => 'array',
					'items' => array( 'type' => 'object' ),
				),
				'reason'      => array( 'type' => 'string' ),
			),
			'required'   => array( 'available', 'connections' ),
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
		return current_user_can( 'publish_posts' );
	}

	/**
	 * Runs the ability.
	 *
	 * @param mixed $input Empty.
	 * @return array<string,mixed>
	 */
	public function execute( $input ) {
		if ( ! $this->share->available() ) {
			return array(
				'available'   => false,
				'connections' => array(),
				'reason'      => __( 'Jetpack Social is not connected on this site, so sharing is unavailable.', 'masthead' ),
			);
		}
		return array(
			'available'   => true,
			'connections' => $this->share->connections(),
		);
	}
}
