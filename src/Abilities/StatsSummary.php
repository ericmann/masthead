<?php
/**
 * Ability masthead/stats-summary.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Abilities;

use Masthead\Config;
use Masthead\Stats\StatsSource;

/**
 * Traffic summary from the stats source, or an honest "unavailable".
 */
final class StatsSummary implements Ability {

	/**
	 * Constructor.
	 *
	 * @param StatsSource $stats Stats source.
	 */
	public function __construct( private StatsSource $stats ) {}

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'masthead/stats-summary';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Stats summary', 'masthead' );
	}

	/**
	 * Description.
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'Returns views by category, by weekday and for the top posts from Jetpack Stats, or says why they are unavailable. Never estimates.', 'masthead' );
	}

	/**
	 * Input schema.
	 *
	 * @return array<string,mixed>
	 */
	public function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'days' => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
			),
			'additionalProperties' => false,
			// A read-only ability is called with GET; an empty input is not sent, so null must mean the empty object.
			'default'              => array(),
		);
	}

	/**
	 * Output schema.
	 *
	 * @return array<string,mixed>
	 */
	public function output_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'available'   => array( 'type' => 'boolean' ),
				'reason'      => array( 'type' => 'string' ),
				'source'      => array( 'type' => 'string' ),
				'fetched_at'  => array( 'type' => 'string' ),
				'window_days' => array( 'type' => 'integer' ),
				'by_category' => array(
					'type'  => 'array',
					'items' => array( 'type' => 'object' ),
				),
				'by_weekday'  => array(
					'type'  => 'array',
					'items' => array( 'type' => 'object' ),
				),
				'top_posts'   => array(
					'type'  => 'array',
					'items' => array( 'type' => 'object' ),
				),
			),
			'required'   => array( 'available' ),
		);
	}

	/**
	 * Annotations.
	 *
	 * @return array{readonly:bool,destructive:bool,idempotent:bool}
	 */
	public function annotations(): array {
		return array(
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
		);
	}

	/**
	 * Permission.
	 *
	 * @param mixed $input Input.
	 * @return bool
	 */
	public function permission( $input ): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Runs the ability.
	 *
	 * @param mixed $input { days? }.
	 * @return array<string,mixed>
	 */
	public function execute( $input ) {
		$days = is_array( $input ) && isset( $input['days'] ) ? max( 1, (int) $input['days'] ) : (int) Config::get( 'stats.window_days' );

		$category = $this->stats->available() ? $this->stats->views_by_category( $days ) : null;
		$weekday  = $this->stats->available() ? $this->stats->views_by_weekday( $days ) : null;
		$top      = $this->stats->available() ? $this->stats->top_posts( $days, (int) Config::get( 'stats.top_posts_limit' ) ) : null;
		$fetched  = $this->stats->fetched_at();

		if ( null === $category || null === $weekday || null === $top || null === $fetched ) {
			return array(
				'available' => false,
				'reason'    => __( 'Traffic numbers are unavailable: Jetpack Stats is not connected or did not answer.', 'masthead' ),
			);
		}

		$by_category = array();
		foreach ( $category as $slug => $views ) {
			$by_category[] = array(
				'slug'  => (string) $slug,
				'views' => $views,
			);
		}
		$by_weekday = array();
		foreach ( $weekday as $day => $average ) {
			$by_weekday[] = array(
				'weekday'   => (int) $day,
				'avg_views' => $average,
			);
		}
		$top_posts = array();
		foreach ( $top as $post_id => $views ) {
			$top_posts[] = array(
				'post_id' => (int) $post_id,
				'views'   => $views,
			);
		}

		return array(
			'available'   => true,
			'source'      => 'Jetpack Stats',
			'fetched_at'  => $fetched,
			'window_days' => $days,
			'by_category' => $by_category,
			'by_weekday'  => $by_weekday,
			'top_posts'   => $top_posts,
		);
	}
}
