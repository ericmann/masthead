<?php
/**
 * The production stats source, on top of Jetpack's stats client.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Stats;

use Masthead\Config;
use Throwable;

/**
 * The only file that names Jetpack's stats client. Anything that goes wrong is null, never a guess.
 */
final class JetpackStats implements StatsSource {

	/**
	 * Jetpack's stats client class.
	 */
	private const CLIENT_CLASS = 'Automattic\Jetpack\Stats\WPCOM_Stats';

	/**
	 * Cache entries read so far: [ name, args ] pairs.
	 *
	 * @var array<int,array{0:string,1:array}>
	 */
	private array $used = array();

	/**
	 * Constructor.
	 *
	 * @param Cache       $cache      Cache.
	 * @param callable    $primary_of Returns a post's primary category slug.
	 * @param object|null $client     Jetpack stats client; created lazily when null.
	 */
	public function __construct( private Cache $cache, private $primary_of, private ?object $client = null ) {}

	/**
	 * Whether Jetpack's stats client exists, the site is connected and the Stats module is active.
	 *
	 * @return bool
	 */
	public static function usable(): bool {
		return class_exists( self::CLIENT_CLASS )
			&& class_exists( 'Jetpack' )
			&& \Jetpack::is_connection_ready()
			&& \Jetpack::is_module_active( 'stats' );
	}

	/**
	 * Available when a client was injected, or Jetpack is usable.
	 *
	 * @return bool
	 */
	public function available(): bool {
		return null !== $this->client || self::usable();
	}

	/**
	 * Views per primary category, aggregated from top posts (spike P3-01 decision).
	 *
	 * @param int $days Window in days.
	 * @return array<string,int>|null
	 */
	public function views_by_category( int $days ): ?array {
		$top = $this->top_posts( $days, (int) Config::get( 'desk.candidate_pool' ) );
		return null === $top ? null : Payloads::by_category_from_top_posts( $top, $this->primary_of );
	}

	/**
	 * Average daily views per weekday.
	 *
	 * @param int $days Window in days.
	 * @return array<int,float>|null
	 */
	public function views_by_weekday( int $days ): ?array {
		$raw = $this->fetch(
			'get_visits',
			array(
				array(
					'unit'     => 'day',
					'quantity' => $days,
				),
			)
		);
		return null === $raw ? null : Payloads::by_weekday( $raw );
	}

	/**
	 * Most viewed posts.
	 *
	 * @param int $days  Window in days.
	 * @param int $limit Maximum posts.
	 * @return array<int,int>|null
	 */
	public function top_posts( int $days, int $limit ): ?array {
		$raw = $this->fetch( 'get_top_posts', array( $this->top_posts_args( $days ) ) );
		return null === $raw ? null : Payloads::top_posts( $raw, $limit );
	}

	/**
	 * Views of one post over the last days, summed from the per-day rows. Null when the payload has no such rows.
	 *
	 * @param int $post_id Post ID.
	 * @param int $days    Window in days.
	 * @return int|null
	 */
	public function post_views( int $post_id, int $days ): ?int {
		$raw = $this->fetch( 'get_post_views', array( $post_id, array( 'fields' => 'views,data' ) ) );
		$rows = is_array( $raw ) ? ( $raw['data'] ?? null ) : null;
		if ( ! is_array( $rows ) || array() === $rows ) {
			return null;
		}
		$total = 0;
		foreach ( array_slice( $rows, -max( 0, $days ) ) as $row ) {
			if ( ! is_array( $row ) || ! isset( $row[1] ) || ! is_int( $row[1] ) || $row[1] < 0 ) {
				return null;
			}
			$total += $row[1];
		}
		return $total;
	}

	/**
	 * The oldest fetch time among the cache entries read so far.
	 *
	 * @return string|null
	 */
	public function fetched_at(): ?string {
		$oldest = null;
		foreach ( $this->used as list( $name, $args ) ) {
			$at = $this->cache->fetched_at( $name, $args );
			if ( null !== $at && ( null === $oldest || $at < $oldest ) ) {
				$oldest = $at;
			}
		}
		return $oldest;
	}

	/**
	 * Raw payload shapes with every number replaced by 0 and every string by an empty string; bypasses the cache.
	 *
	 * @param int $days Window in days.
	 * @return array<string,mixed>
	 */
	public function probe( int $days ): array {
		$calls  = array(
			'get_visits'    => array(
				array(
					'unit'     => 'day',
					'quantity' => $days,
				),
			),
			'get_top_posts' => array( $this->top_posts_args( $days ) ),
			'get_tags'      => array( array() ),
		);
		$latest = get_posts(
			array(
				'post_type'     => Config::get( 'post_types' ),
				'post_status'   => 'publish',
				'numberposts'   => 1,
				'fields'        => 'ids',
				'no_found_rows' => true,
			)
		);
		if ( array() !== $latest ) {
			$calls['get_post_views'] = array( (int) $latest[0], array( 'fields' => 'views' ) );
		}

		$out = array();
		foreach ( $calls as $method => $arguments ) {
			try {
				$result       = $this->client()->$method( ...$arguments );
				$out[ $method ] = is_wp_error( $result ) ? array( 'error' => $result->get_error_code() ) : self::scrub( $result );
			} catch ( Throwable $error ) {
				$out[ $method ] = array( 'error' => 'exception' );
			}
		}
		return $out;
	}

	/**
	 * Replaces numbers with 0 and strings with an empty string, keeping keys, booleans and null.
	 *
	 * @param mixed $value Payload.
	 * @return mixed
	 */
	public static function scrub( $value ) {
		if ( is_array( $value ) ) {
			return array_map( array( self::class, 'scrub' ), $value );
		}
		if ( is_int( $value ) || is_float( $value ) ) {
			return 0;
		}
		if ( is_string( $value ) ) {
			return '';
		}
		return $value;
	}

	/**
	 * Arguments of the top-posts request: per-day lists over the window.
	 *
	 * @param int $days Window in days.
	 * @return array<string,mixed>
	 */
	private function top_posts_args( int $days ): array {
		return array(
			'period' => 'day',
			'num'    => $days,
			'max'    => (int) Config::get( 'desk.candidate_pool' ),
		);
	}

	/**
	 * Calls a client method through the cache. Errors, exceptions and non-arrays are null.
	 *
	 * @param string $method    Client method.
	 * @param array  $arguments Positional arguments.
	 * @return array|null
	 */
	private function fetch( string $method, array $arguments ): ?array {
		$this->used[] = array( $method, $arguments );
		$value        = $this->cache->remember(
			$method,
			$arguments,
			function () use ( $method, $arguments ) {
				try {
					$result = $this->client()->$method( ...$arguments );
				} catch ( Throwable $error ) {
					return null;
				}
				return is_array( $result ) ? $result : null;
			}
		);
		return is_array( $value ) ? $value : null;
	}

	/**
	 * The Jetpack client, created on first use.
	 *
	 * @return object
	 */
	private function client(): object {
		if ( null === $this->client ) {
			$class        = self::CLIENT_CLASS;
			$this->client = new $class();
		}
		return $this->client;
	}
}
