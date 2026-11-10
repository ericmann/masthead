<?php
/**
 * Budget-enforcing AI decorator.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Desk;

use Masthead\Ai\AiClient;

/**
 * Reserves one request from the budget before every generation. Ai/ cannot import Desk/, so this lives here.
 */
final class BudgetedAi implements AiClient {

	private const TOO_MANY_REQUESTS = 429;

	/**
	 * Constructor.
	 *
	 * @param AiClient $inner  Wrapped client.
	 * @param Budget   $budget Budget.
	 */
	public function __construct( private AiClient $inner, private Budget $budget ) {}

	/**
	 * Delegates; never reserves.
	 *
	 * @return bool
	 */
	public function supported(): bool {
		return $this->inner->supported();
	}

	/**
	 * Generates JSON after reserving a request.
	 *
	 * @param string $system System instruction.
	 * @param string $user   User prompt.
	 * @param array  $schema JSON schema.
	 * @param string $tier   Tier.
	 * @return array|\WP_Error
	 */
	public function json( string $system, string $user, array $schema, string $tier ): array|\WP_Error {
		return $this->budget->reserve() ? $this->inner->json( $system, $user, $schema, $tier ) : $this->refusal();
	}

	/**
	 * Generates text after reserving a request.
	 *
	 * @param string $system System instruction.
	 * @param string $user   User prompt.
	 * @param string $tier   Tier.
	 * @return string|\WP_Error
	 */
	public function text( string $system, string $user, string $tier ): string|\WP_Error {
		return $this->budget->reserve() ? $this->inner->text( $system, $user, $tier ) : $this->refusal();
	}

	/**
	 * The refusal error.
	 *
	 * @return \WP_Error
	 */
	private function refusal(): \WP_Error {
		return new \WP_Error(
			'masthead_ai_budget',
			__( 'The monthly AI request limit has been reached.', 'masthead' ),
			array( 'status' => self::TOO_MANY_REQUESTS )
		);
	}
}
