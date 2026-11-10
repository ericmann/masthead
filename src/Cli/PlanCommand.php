<?php
/**
 * Command masthead plan.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Cli;

use Masthead\Abilities\Ability;
use Masthead\Support\Clock;
use Masthead\Support\Dates;
use Masthead\Support\Json;

/**
 * Prints the plan from the same ability the UI uses.
 */
final class PlanCommand {

	/**
	 * Constructor.
	 *
	 * @param Ability $ability The masthead/get-plan ability.
	 * @param Clock   $clock   Clock.
	 */
	public function __construct( private Ability $ability, private Clock $clock ) {}

	/**
	 * Prints the plan for a date range.
	 *
	 * ## OPTIONS
	 *
	 * <from>
	 * : First date, YYYY-MM-DD.
	 *
	 * <to>
	 * : Last date, YYYY-MM-DD.
	 *
	 * [--format=<format>]
	 * : table or json. Default table.
	 *
	 * [--now=<datetime>]
	 * : Pin the clock to this site-time datetime.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Options.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		Commands::apply_now( $assoc_args );
		$result = $this->ability->execute(
			array(
				'from' => (string) ( $args[0] ?? '' ),
				'to'   => (string) ( $args[1] ?? '' ),
			)
		);
		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() );
			return;
		}
		if ( 'json' === ( $assoc_args['format'] ?? 'table' ) ) {
			\WP_CLI::line( Json::encode( $result ) );
			return;
		}
		\WP_CLI\Utils\format_items( 'table', $this->rows( $result ), array( 'date', 'weekday', 'lane', 'status', 'id', 'title' ) );
	}

	/**
	 * One row per post and one per day without a post.
	 *
	 * @param array $plan Ability output.
	 * @return array<int,array<string,mixed>>
	 */
	public function rows( array $plan ): array {
		$rows = array();
		foreach ( (array) ( $plan['days'] ?? array() ) as $day ) {
			$parsed  = Dates::parse_date( (string) $day['date'] );
			$weekday = null === $parsed ? '' : $this->clock->format( $parsed, 'D' );
			$lane    = (string) ( $day['lane']['slug'] ?? '' );
			if ( array() === $day['posts'] ) {
				$rows[] = array(
					'date'    => $day['date'],
					'weekday' => $weekday,
					'lane'    => $lane,
					'status'  => 'open',
					'id'      => '',
					'title'   => '',
				);
				continue;
			}
			foreach ( $day['posts'] as $post ) {
				$rows[] = array(
					'date'    => $day['date'],
					'weekday' => $weekday,
					'lane'    => $lane,
					'status'  => $post['status'],
					'id'      => $post['id'],
					'title'   => $post['title'],
				);
			}
		}
		return $rows;
	}
}
