<?php
/**
 * Configuration: the only home of tunable defaults.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead;

use InvalidArgumentException;

/**
 * Resolves configuration keys.
 */
final class Config {

	/**
	 * Option holding user overrides.
	 */
	public const OPTION = 'masthead_settings';

	/**
	 * Default values keyed by dotted key.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			'post_types'                       => array( 'post' ),
			'calendar.week_starts_on'          => null,
			'calendar.drag_published'          => false,
			'calendar.max_posts_per_range'     => 500,
			'calendar.max_range_days'          => 92,
			'desk.horizon_days'                => 21,
			'desk.ghost_days'                  => 14,
			'desk.max_suggestions'             => 3,
			'desk.ideas_per_request'           => 3,
			'desk.gap_days'                    => 45,
			'desk.reshare_min_age_days'        => 90,
			'desk.reshare_cooldown_days'       => 180,
			'desk.series_window_days'          => 3,
			'desk.candidate_pool'              => 50,
			'swap.lookahead_days'              => 60,
			'swap.min_score_delta'             => 0.25,
			'swap.candidate_window_days'       => 3,
			'swap.weights'                     => array(
				'lane'     => 0.5,
				'category' => 0.3,
				'weekday'  => 0.2,
			),
			'swap.collision_exempt_categories' => array( 'journal' ),
			'stats.window_days'                => 30,
			'stats.cache_ttl'                  => 21600,
			'stats.top_posts_limit'            => 10,
			'ai.monthly_request_cap'           => 300,
			'ai.models.fast'                   => array( 'claude-haiku-5-5' ),
			'ai.models.strong'                 => array( 'claude-sonnet-5-5' ),
			'ai.temperature'                   => 0.4,
			'ai.max_tokens'                    => 1200,
			'ai.context_days'                  => 90,
			'ai.context_max_posts'             => 60,
			'ai.include_draft_excerpts'        => true,
			'ai.series_max_parts'              => 12,
			'abilities.expose_mcp'             => true,
			'abilities.expose_share'           => false,
			'cron.nightly_hour'                => 3,
		);
	}

	/**
	 * Keys a user may override through the masthead_settings option.
	 *
	 * @return string[]
	 */
	public static function settable(): array {
		return array(
			'desk.horizon_days',
			'desk.ghost_days',
			'desk.max_suggestions',
			'desk.ideas_per_request',
			'desk.gap_days',
			'desk.reshare_min_age_days',
			'desk.reshare_cooldown_days',
			'desk.series_window_days',
			'swap.lookahead_days',
			'swap.min_score_delta',
			'swap.candidate_window_days',
			'swap.weights',
			'stats.window_days',
			'ai.monthly_request_cap',
			'ai.models.fast',
			'ai.models.strong',
			'ai.context_days',
			'ai.include_draft_excerpts',
			'abilities.expose_mcp',
			'abilities.expose_share',
		);
	}

	/**
	 * Resolves one key.
	 *
	 * @param string $key Dotted key.
	 * @return mixed
	 * @throws InvalidArgumentException For an unknown key.
	 */
	public static function get( string $key ) {
		$all = self::all();
		if ( ! array_key_exists( $key, $all ) ) {
			throw new InvalidArgumentException( 'Unknown Masthead config key: ' . esc_html( $key ) );
		}
		return $all[ $key ];
	}

	/**
	 * Resolves every key.
	 *
	 * @return array<string,mixed>
	 */
	public static function all(): array {
		$flat = self::defaults();
		// Settings are stored nested by dotted path.
		$stored = get_option( self::OPTION, array() );
		if ( is_array( $stored ) ) {
			foreach ( self::settable() as $key ) {
				$found = self::lookup( $stored, $key );
				if ( null !== $found && self::same_type( $flat[ $key ], $found[0] ) ) {
					$flat[ $key ] = $found[0];
				}
			}
		}
		if ( null === $flat['calendar.week_starts_on'] ) {
			$flat['calendar.week_starts_on'] = (int) get_option( 'start_of_week' );
		}
		$filtered = apply_filters( 'masthead_config', $flat );
		return is_array( $filtered ) ? $filtered : $flat;
	}

	/**
	 * Finds a dotted path in a nested array.
	 *
	 * @param array<mixed> $tree Nested array.
	 * @param string       $key  Dotted key.
	 * @return array{0:mixed}|null Wrapped value, or null when absent.
	 */
	private static function lookup( array $tree, string $key ): ?array {
		$node = $tree;
		foreach ( explode( '.', $key ) as $part ) {
			if ( ! is_array( $node ) || ! array_key_exists( $part, $node ) ) {
				return null;
			}
			$node = $node[ $part ];
		}
		return array( $node );
	}

	/**
	 * Whether a stored value has the default's type (int accepted for float).
	 *
	 * @param mixed $default_value Default.
	 * @param mixed $value         Stored.
	 * @return bool
	 */
	private static function same_type( $default_value, $value ): bool {
		if ( is_float( $default_value ) ) {
			return is_float( $value ) || is_int( $value );
		}
		return gettype( $default_value ) === gettype( $value );
	}
}
