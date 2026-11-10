<?php
/**
 * Monthly AI request budget.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Desk;

use Masthead\Config;
use Masthead\Support\Clock;

/**
 * Counts AI requests per calendar month in the site zone.
 */
class Budget {

	/**
	 * Option name.
	 */
	public const OPTION = 'masthead_ai_usage';

	/**
	 * Constructor.
	 *
	 * @param Clock $clock Clock.
	 */
	public function __construct( private Clock $clock ) {}

	/**
	 * Takes one request from the budget.
	 *
	 * @return bool False, without writing, when the cap is zero or reached.
	 */
	public function reserve(): bool {
		$cap = $this->cap();
		if ( $cap <= 0 ) {
			return false;
		}
		$usage = $this->read();
		if ( $usage['requests'] >= $cap ) {
			return false;
		}
		++$usage['requests'];
		update_option( self::OPTION, $usage, false );
		return true;
	}

	/**
	 * Requests left this month.
	 *
	 * @return int Never below zero.
	 */
	public function remaining(): int {
		return max( 0, $this->cap() - $this->read()['requests'] );
	}

	/**
	 * Remembers the last model id used for a tier.
	 *
	 * @param string $tier  Tier.
	 * @param string $model Model id.
	 * @return void
	 */
	public function note_model( string $tier, string $model ): void {
		$usage = $this->read();
		if ( ( $usage['models'][ $tier ] ?? null ) === $model ) {
			return;
		}
		$usage['models'][ $tier ] = $model;
		update_option( self::OPTION, $usage, false );
	}

	/**
	 * Last model id per tier.
	 *
	 * @return array<string,string>
	 */
	public function models(): array {
		return $this->read()['models'];
	}

	/**
	 * Configured monthly cap.
	 *
	 * @return int
	 */
	private function cap(): int {
		return (int) Config::get( 'ai.monthly_request_cap' );
	}

	/**
	 * Stored usage for the current month.
	 *
	 * @return array{month: string, requests: int, models: array<string,string>}
	 */
	private function read(): array {
		$month  = $this->clock->format( $this->clock->now(), 'Y-m' );
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$models = array();
		foreach ( (array) ( $stored['models'] ?? array() ) as $tier => $model ) {
			if ( is_string( $tier ) && is_string( $model ) ) {
				$models[ $tier ] = $model;
			}
		}
		$same = isset( $stored['month'] ) && $month === $stored['month'];
		return array(
			'month'    => $month,
			'requests' => $same ? max( 0, (int) ( $stored['requests'] ?? 0 ) ) : 0,
			'models'   => $models,
		);
	}
}
