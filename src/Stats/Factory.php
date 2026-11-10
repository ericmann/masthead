<?php
/**
 * Stats source factory.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Stats;

use Masthead\Support\Categories;
use Masthead\Support\Clock;

/**
 * Chooses the stats source. Fixture numbers are reachable only in a local environment.
 */
final class Factory {

	/**
	 * Builds the source.
	 *
	 * @param string|null $environment  Environment type; defaults to wp_get_environment_type().
	 * @param string|null $fixture_path Fixture path; defaults to the MASTHEAD_STATS_FIXTURE constant.
	 * @param bool|null   $jetpack      Whether Jetpack Stats is usable; defaults to JetpackStats::usable(). Tests pass it.
	 * @return StatsSource
	 */
	public static function make( ?string $environment = null, ?string $fixture_path = null, ?bool $jetpack = null ): StatsSource {
		$environment  = $environment ?? wp_get_environment_type();
		$fixture_path = $fixture_path ?? ( defined( 'MASTHEAD_STATS_FIXTURE' ) ? (string) constant( 'MASTHEAD_STATS_FIXTURE' ) : null );

		$primary_of = static fn( int $post_id ): ?string => Categories::for_post( $post_id )['primary'];

		if ( 'local' === $environment && is_string( $fixture_path ) && '' !== $fixture_path && is_file( $fixture_path ) && is_readable( $fixture_path ) ) {
			return new FixtureStats( $fixture_path, $primary_of );
		}
		if ( $jetpack ?? JetpackStats::usable() ) {
			return new JetpackStats( new Cache( new Clock() ), $primary_of );
		}
		return new NullStats();
	}
}
