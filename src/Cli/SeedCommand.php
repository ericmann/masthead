<?php
/**
 * wp masthead seed.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Cli;

/**
 * Loads the acceptance fixture into a local site.
 */
final class SeedCommand {

	/**
	 * Constructor.
	 *
	 * @param Seeder $seeder Seeder.
	 */
	public function __construct( private Seeder $seeder ) {}

	/**
	 * Seeds a fixture. Local environments only.
	 *
	 * ## OPTIONS
	 *
	 * --fixture=<path>
	 * : Path to the fixture JSON file.
	 *
	 * [--now=<datetime>]
	 * : Pin the clock to this site-time datetime (Y-m-d H:i:s).
	 *
	 * [--reset]
	 * : Delete existing posts, series terms and Masthead options first.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Options.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		if ( 'local' !== wp_get_environment_type() ) {
			\WP_CLI::error( 'wp masthead seed only runs in a local environment.' );
		}
		$path = $assoc_args['fixture'] ?? '';
		if ( ! is_string( $path ) || '' === $path || ! is_readable( $path ) || ! is_file( $path ) ) {
			\WP_CLI::error( 'The --fixture path is not a readable file.' );
		}
		$fixture = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local fixture path.
		if ( ! is_array( $fixture ) || array() === $fixture || array_is_list( $fixture ) ) {
			\WP_CLI::error( 'The fixture is not a JSON object.' );
		}
		$now = Commands::apply_now( $assoc_args );
		if ( isset( $assoc_args['reset'] ) ) {
			$this->seeder->reset();
		}
		$count = $this->seeder->seed( $fixture, $now );
		\WP_CLI::success( sprintf( 'Seeded %d posts.', $count ) );
	}
}
