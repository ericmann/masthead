<?php
/**
 * Ability contract.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Abilities;

/**
 * One WordPress Ability under masthead/*. The admin UI, WP-CLI and MCP all call it.
 */
interface Ability {

	/**
	 * Full ability name, such as masthead/get-plan.
	 *
	 * @return string
	 */
	public function name(): string;

	/**
	 * Human label.
	 *
	 * @return string
	 */
	public function label(): string;

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description(): string;

	/**
	 * JSON schema of the input.
	 *
	 * @return array<string,mixed>
	 */
	public function input_schema(): array;

	/**
	 * JSON schema of the output.
	 *
	 * @return array<string,mixed>
	 */
	public function output_schema(): array;

	/**
	 * Annotations: readonly, destructive, idempotent.
	 *
	 * @return array{readonly:bool,destructive:bool,idempotent:bool}
	 */
	public function annotations(): array;

	/**
	 * Permission callback.
	 *
	 * @param mixed $input Input.
	 * @return bool|\WP_Error
	 */
	public function permission( $input );

	/**
	 * Runs the ability.
	 *
	 * @param mixed $input Input.
	 * @return mixed
	 */
	public function execute( $input );
}
