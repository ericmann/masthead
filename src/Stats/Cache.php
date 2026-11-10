<?php
/**
 * Stats cache.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Stats;

use Masthead\Config;
use Masthead\Support\Clock;
use Masthead\Support\Dates;
use Masthead\Support\Json;

/**
 * Transient cache for stats responses. Never stores null.
 */
final class Cache {

	/**
	 * Transient name prefix.
	 */
	private const PREFIX = 'masthead_stats_';

	/**
	 * Constructor.
	 *
	 * @param Clock $clock Clock.
	 */
	public function __construct( private Clock $clock ) {}

	/**
	 * Returns the cached value or produces and stores it for stats.cache_ttl seconds.
	 *
	 * @param string   $name     Logical name.
	 * @param array    $args     Arguments that distinguish the entry.
	 * @param callable $producer Produces the value; null is returned but never cached.
	 * @return mixed
	 */
	public function remember( string $name, array $args, callable $producer ) {
		$transient = $this->transient( $name, $args );
		$stored    = get_transient( $transient );
		if ( is_array( $stored ) && array_key_exists( 'value', $stored ) ) {
			return $stored['value'];
		}
		$value = $producer();
		if ( null === $value ) {
			return null;
		}
		set_transient(
			$transient,
			array(
				'value'      => $value,
				'fetched_at' => Dates::to_iso( $this->clock->now() ),
			),
			(int) Config::get( 'stats.cache_ttl' )
		);
		return $value;
	}

	/**
	 * When an entry was fetched.
	 *
	 * @param string $name Logical name.
	 * @param array  $args Arguments.
	 * @return string|null ISO 8601, or null when nothing is cached.
	 */
	public function fetched_at( string $name, array $args ): ?string {
		$stored = get_transient( $this->transient( $name, $args ) );
		return is_array( $stored ) && is_string( $stored['fetched_at'] ?? null ) ? $stored['fetched_at'] : null;
	}

	/**
	 * Transient name for an entry.
	 *
	 * @param string $name Logical name.
	 * @param array  $args Arguments.
	 * @return string
	 */
	private function transient( string $name, array $args ): string {
		return self::PREFIX . md5( $name . '|' . Json::encode( $args ) );
	}
}
