<?php
/**
 * Stats read from a synthetic fixture file.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Stats;

use Masthead\Support\Json;

/**
 * Reachable only through Factory, only in a local environment. Ignores the window arguments: the file is one snapshot.
 */
final class FixtureStats implements StatsSource {

	/**
	 * Decoded file, or null when it could not be read.
	 *
	 * @var array<string,mixed>|null
	 */
	private ?array $data;

	/**
	 * Constructor.
	 *
	 * @param string   $path       Fixture path.
	 * @param callable $primary_of Returns a post's primary category slug.
	 */
	public function __construct( string $path, private $primary_of ) {
		$raw        = is_readable( $path ) && is_file( $path ) ? file_get_contents( $path ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local fixture path.
		$decoded    = is_string( $raw ) ? Json::decode_object( $raw ) : null;
		$this->data = ( is_array( $decoded ) && isset( $decoded['payloads'] ) && is_array( $decoded['payloads'] ) ) ? $decoded : null;
	}

	/**
	 * Available when the file parsed.
	 *
	 * @return bool
	 */
	public function available(): bool {
		return null !== $this->data;
	}

	/**
	 * Views by primary category.
	 *
	 * @param int $days Ignored.
	 * @return array<string,int>|null
	 */
	public function views_by_category( int $days ): ?array {
		$top = $this->top_posts( $days, PHP_INT_MAX );
		return null === $top ? null : Payloads::by_category_from_top_posts( $top, $this->primary_of );
	}

	/**
	 * Average daily views by weekday.
	 *
	 * @param int $days Ignored.
	 * @return array<int,float>|null
	 */
	public function views_by_weekday( int $days ): ?array {
		$payload = $this->payload( 'get_visits' );
		return null === $payload ? null : Payloads::by_weekday( $payload );
	}

	/**
	 * Top posts.
	 *
	 * @param int $days  Ignored.
	 * @param int $limit Maximum posts.
	 * @return array<int,int>|null
	 */
	public function top_posts( int $days, int $limit ): ?array {
		$payload = $this->payload( 'get_top_posts' );
		return null === $payload ? null : Payloads::top_posts( $payload, $limit );
	}

	/**
	 * Views of one post. Only the post in the fixture has a number.
	 *
	 * @param int $post_id Post ID.
	 * @param int $days    Ignored.
	 * @return int|null
	 */
	public function post_views( int $post_id, int $days ): ?int {
		$top = $this->top_posts( $days, PHP_INT_MAX );
		return $top[ $post_id ] ?? null;
	}

	/**
	 * The file's fetched_at.
	 *
	 * @return string|null
	 */
	public function fetched_at(): ?string {
		$fetched = $this->data['fetched_at'] ?? null;
		return is_string( $fetched ) ? $fetched : null;
	}

	/**
	 * One method's payload.
	 *
	 * @param string $method stats method name used as the key.
	 * @return array|null
	 */
	private function payload( string $method ): ?array {
		$payload = $this->data['payloads'][ $method ] ?? null;
		return is_array( $payload ) ? $payload : null;
	}
}
