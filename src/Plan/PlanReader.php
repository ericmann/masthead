<?php
/**
 * Reads the plan.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Plan;

use Masthead\Config;
use Masthead\Support\Categories;
use Masthead\Support\Clock;
use Masthead\Support\Dates;

/**
 * Reads posts in a range as PlanPost records grouped by day. Not final so Desk tests can stub it.
 */
class PlanReader {

	/**
	 * Constructor.
	 *
	 * @param Clock        $clock  Clock.
	 * @param SeriesSource $series Series adapter.
	 */
	public function __construct( private Clock $clock, private SeriesSource $series ) {}

	/**
	 * Days in a range with their lane and posts, plus the unscheduled drafts.
	 *
	 * @param string $from First date, Y-m-d.
	 * @param string $to   Last date, Y-m-d, inclusive.
	 * @return array{days:array<int,array<string,mixed>>,unscheduled:array<int,array<string,mixed>>,truncated:bool}
	 */
	public function range( string $from, string $to ): array {
		$cap   = (int) Config::get( 'calendar.max_posts_per_range' );
		$posts = $this->query( array( 'publish', 'future', 'draft' ), $from, $to, $cap );

		$by_day = array();
		foreach ( $posts as $post ) {
			if ( ! Slots::is_dated( $post ) ) {
				continue;
			}
			$plan_post = $this->plan_post( $post );
			$by_day[ explode( ' ', (string) $post->post_date )[0] ][] = $plan_post;
		}

		$today = $this->clock->today();
		$days  = array();
		foreach ( Dates::range( $from, $to ) as $date ) {
			$lane   = Lanes::for_date( $date );
			$posted = $by_day[ $date ] ?? array();
			$days[] = array(
				'date'  => $date,
				'lane'  => array(
					'slug'       => $lane['slug'],
					'label'      => $lane['label'],
					'categories' => $lane['categories'],
				),
				'posts' => $posted,
				'open'  => array() === $posted && $date >= $today,
			);
		}

		return array(
			'days'        => $days,
			'unscheduled' => $this->unscheduled(),
			'truncated'   => count( $posts ) >= $cap,
		);
	}

	/**
	 * Undated drafts, most recently modified first, bounded by desk.candidate_pool.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function unscheduled(): array {
		$only_undated = static function ( $where ) {
			global $wpdb;
			return $where . " AND {$wpdb->posts}.post_date_gmt = '0000-00-00 00:00:00'";
		};
		add_filter( 'posts_where', $only_undated );
		try {
			$posts = get_posts(
				array(
					'post_type'        => Config::get( 'post_types' ),
					'post_status'      => 'draft',
					'posts_per_page'   => (int) Config::get( 'desk.candidate_pool' ),
					'no_found_rows'    => true,
					'orderby'          => 'modified',
					'order'            => 'DESC',
					'suppress_filters' => false,
				)
			);
		} finally {
			remove_filter( 'posts_where', $only_undated );
		}
		$out = array();
		foreach ( $posts as $post ) {
			if ( ! Slots::is_dated( $post ) ) {
				$out[] = $this->plan_post( $post );
			}
		}
		return $out;
	}

	/**
	 * One post as a PlanPost.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string,mixed>|null
	 */
	public function post( int $post_id ): ?array {
		$post = get_post( $post_id );
		if ( ! is_object( $post ) || ! in_array( $post->post_type, (array) Config::get( 'post_types' ), true ) ) {
			return null;
		}
		return $this->plan_post( $post );
	}

	/**
	 * Future posts and dated drafts in a range, ordered by date then ID.
	 *
	 * @param string $from First date.
	 * @param string $to   Last date, inclusive.
	 * @return array<int,array<string,mixed>>
	 */
	public function scheduled_between( string $from, string $to ): array {
		$posts = $this->query( array( 'future', 'draft' ), $from, $to, (int) Config::get( 'calendar.max_posts_per_range' ) );
		$out   = array();
		foreach ( $posts as $post ) {
			if ( Slots::is_dated( $post ) ) {
				$out[] = $this->plan_post( $post );
			}
		}
		return $out;
	}

	/**
	 * Maps a post object to a PlanPost.
	 *
	 * @param object $post Post with ID, post_title, post_status, post_date, post_date_gmt, post_modified.
	 * @return array<string,mixed>
	 */
	public function plan_post( object $post ): array {
		$post_id = (int) $post->ID;
		$dated   = Slots::is_dated( $post );
		$terms   = get_the_terms( $post_id, 'category' );
		$terms   = is_array( $terms ) ? $terms : array();
		usort(
			$terms,
			static fn( $a, $b ) => (int) $a->term_id <=> (int) $b->term_id
		);
		$primary = Categories::primary( $terms );
		$color   = '';
		foreach ( $terms as $term ) {
			if ( (string) $term->slug === $primary ) {
				$color = Colors::for_term( (int) $term->term_id, (string) $term->slug );
				break;
			}
		}

		$date = null;
		if ( $dated ) {
			$parsed = Dates::parse_local( (string) $post->post_date );
			$date   = null === $parsed ? null : Dates::to_iso( $parsed );
		}

		$plan_post = array(
			'id'               => $post_id,
			'title'            => (string) $post->post_title,
			'status'           => (string) $post->post_status,
			'date'             => $date,
			'categories'       => array_map( static fn( $term ) => (string) $term->slug, $terms ),
			'primary_category' => $primary,
			'color'            => $color,
			'planned'          => (bool) get_post_meta( $post_id, 'masthead_planned', true ),
			'edit_url'         => (string) get_edit_post_link( $post_id, 'raw' ),
		);
		$series    = $this->series->for_post( $post_id );
		if ( null !== $series ) {
			$plan_post['series'] = array(
				'term_id' => $series['term_id'],
				'name'    => $series['name'],
				'part'    => $series['part'],
				'total'   => $series['total'],
			);
		}
		if ( ! $dated ) {
			$modified            = Dates::parse_local( (string) $post->post_modified );
			$plan_post['modified'] = null === $modified ? null : Dates::to_iso( $modified );
		}
		return $plan_post;
	}

	/**
	 * Runs the one bounded range query.
	 *
	 * @param string[] $statuses Post statuses.
	 * @param string   $from     First date.
	 * @param string   $to       Last date, inclusive.
	 * @param int      $cap      Maximum posts.
	 * @return array<int,object>
	 */
	private function query( array $statuses, string $from, string $to, int $cap ): array {
		$posts = get_posts(
			array(
				'post_type'        => Config::get( 'post_types' ),
				'post_status'      => $statuses,
				'posts_per_page'   => $cap,
				'no_found_rows'    => true,
				'orderby'          => array(
					'date' => 'ASC',
					'ID'   => 'ASC',
				),
				'suppress_filters' => false,
				'date_query'       => array(
					array(
						'column'    => 'post_date',
						'after'     => $from . ' 00:00:00',
						'before'    => $to . ' 23:59:59',
						'inclusive' => true,
					),
				),
			)
		);
		return is_array( $posts ) ? $posts : array();
	}
}
