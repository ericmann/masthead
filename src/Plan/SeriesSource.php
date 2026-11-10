<?php
/**
 * Series adapter.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Plan;

use Masthead\Config;

/**
 * Reads series from the series taxonomy when present, else from masthead_series. Not final so Desk tests can stub it.
 */
class SeriesSource {

	/**
	 * Masthead's own series taxonomy.
	 */
	public const OWN_TAXONOMY = 'masthead_series';

	/**
	 * Resolved taxonomy name.
	 *
	 * @return string
	 */
	public function taxonomy(): string {
		$default = taxonomy_exists( 'series' ) ? 'series' : self::OWN_TAXONOMY;
		/**
		 * Filters the series taxonomy.
		 *
		 * @param string $default Taxonomy name.
		 */
		$filtered = apply_filters( 'masthead_series_taxonomy', $default );
		if ( is_string( $filtered ) && ( self::OWN_TAXONOMY === $filtered || taxonomy_exists( $filtered ) ) ) {
			return $filtered;
		}
		return self::OWN_TAXONOMY;
	}

	/**
	 * Whether the series come from ttm-core.
	 *
	 * @return bool
	 */
	private function is_ttm(): bool {
		return 'series' === $this->taxonomy();
	}

	/**
	 * Post meta holding the part number.
	 *
	 * @return string
	 */
	public function part_meta(): string {
		return $this->is_ttm() ? 'ttm_series_part' : 'masthead_series_part';
	}

	/**
	 * Term meta holding the total parts.
	 *
	 * @return string
	 */
	public function total_meta(): string {
		return $this->is_ttm() ? 'ttm_total_parts' : 'masthead_total_parts';
	}

	/**
	 * Registers the taxonomy (when needed) and Masthead's series meta. Hooked on init, last.
	 *
	 * @return void
	 */
	public function register(): void {
		$taxonomy = $this->taxonomy();
		if ( self::OWN_TAXONOMY === $taxonomy && ! taxonomy_exists( $taxonomy ) ) {
			register_taxonomy(
				$taxonomy,
				array( 'post' ),
				array(
					'labels'       => array(
						'name'          => __( 'Series', 'masthead' ),
						'singular_name' => __( 'Series', 'masthead' ),
					),
					'hierarchical' => false,
					'public'       => false,
					'show_ui'      => true,
					'show_in_rest' => true,
					'rewrite'      => false,
				)
			);
		}

		$auth = static fn() => current_user_can( 'manage_categories' );
		register_term_meta(
			$taxonomy,
			'masthead_cadence',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( Cadence::class, 'sanitize_json' ),
				'auth_callback'     => $auth,
			)
		);
		register_term_meta(
			$taxonomy,
			'masthead_lane_exception',
			array(
				'type'          => 'boolean',
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => $auth,
			)
		);

		if ( self::OWN_TAXONOMY === $taxonomy ) {
			register_term_meta(
				$taxonomy,
				'masthead_total_parts',
				array(
					'type'          => 'integer',
					'single'        => true,
					'show_in_rest'  => true,
					'auth_callback' => $auth,
				)
			);
			foreach ( (array) Config::get( 'post_types' ) as $post_type ) {
				register_post_meta(
					(string) $post_type,
					'masthead_series_part',
					array(
						'type'         => 'integer',
						'single'       => true,
						'show_in_rest' => false,
					)
				);
			}
		}
	}

	/**
	 * The series a post belongs to.
	 *
	 * @param int $post_id Post ID.
	 * @return array{term_id:int,name:string,slug:string,part:?int,total:?int}|null
	 */
	public function for_post( int $post_id ): ?array {
		$terms = get_the_terms( $post_id, $this->taxonomy() );
		if ( ! is_array( $terms ) || array() === $terms ) {
			return null;
		}
		usort(
			$terms,
			static fn( $a, $b ) => (int) $a->term_id <=> (int) $b->term_id
		);
		$term = $terms[0];
		return array(
			'term_id' => (int) $term->term_id,
			'name'    => (string) $term->name,
			'slug'    => (string) $term->slug,
			'part'    => $this->positive_int( get_post_meta( $post_id, $this->part_meta(), true ) ),
			'total'   => $this->positive_int( get_term_meta( (int) $term->term_id, $this->total_meta(), true ) ),
		);
	}

	/**
	 * Series terms, bounded by desk.candidate_pool.
	 *
	 * @return array<int,object>
	 */
	public function terms(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => $this->taxonomy(),
				'hide_empty' => false,
				'orderby'    => 'term_id',
				'order'      => 'ASC',
				'number'     => (int) Config::get( 'desk.candidate_pool' ),
			)
		);
		return is_array( $terms ) ? $terms : array();
	}

	/**
	 * Parts of a series, ordered by part then post ID.
	 *
	 * @param int $term_id Series term ID.
	 * @return array<int,array{post_id:int,part:?int,status:string,date:?string,dated:bool}>
	 */
	public function parts( int $term_id ): array {
		$posts = get_posts(
			array(
				'post_type'        => Config::get( 'post_types' ),
				'post_status'      => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'numberposts'      => (int) Config::get( 'calendar.max_posts_per_range' ),
				'no_found_rows'    => true,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'suppress_filters' => false,
				'tax_query'        => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => $this->taxonomy(),
						'field'    => 'term_id',
						'terms'    => array( $term_id ),
					),
				),
			)
		);

		$parts = array();
		foreach ( $posts as $post ) {
			$dated   = '0000-00-00 00:00:00' !== (string) $post->post_date_gmt;
			$parts[] = array(
				'post_id' => (int) $post->ID,
				'part'    => $this->positive_int( get_post_meta( (int) $post->ID, $this->part_meta(), true ) ),
				'status'  => (string) $post->post_status,
				'date'    => $dated ? (string) $post->post_date : null,
				'dated'   => $dated,
			);
		}
		usort(
			$parts,
			static function ( array $a, array $b ): int {
				return array( $a['part'] ?? PHP_INT_MAX, $a['post_id'] ) <=> array( $b['part'] ?? PHP_INT_MAX, $b['post_id'] );
			}
		);
		return $parts;
	}

	/**
	 * A series' cadence.
	 *
	 * @param int $term_id Series term ID.
	 * @return array|null
	 */
	public function cadence( int $term_id ): ?array {
		$stored = get_term_meta( $term_id, 'masthead_cadence', true );
		return is_string( $stored ) ? Cadence::from_json( $stored ) : null;
	}

	/**
	 * Whether the series is exempt from lane mismatches.
	 *
	 * @param int $term_id Series term ID.
	 * @return bool
	 */
	public function lane_exception( int $term_id ): bool {
		return filter_var( get_term_meta( $term_id, 'masthead_lane_exception', true ), FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * REST base of the series taxonomy.
	 *
	 * @return string
	 */
	public function rest_base(): string {
		$taxonomy = get_taxonomy( $this->taxonomy() );
		if ( is_object( $taxonomy ) && ! empty( $taxonomy->rest_base ) ) {
			return (string) $taxonomy->rest_base;
		}
		return $this->taxonomy();
	}

	/**
	 * Assigns a post to a series part.
	 *
	 * @param int $post_id Post ID.
	 * @param int $term_id Series term ID.
	 * @param int $part    Part number.
	 * @return void
	 */
	public function set_part( int $post_id, int $term_id, int $part ): void {
		wp_set_object_terms( $post_id, array( $term_id ), $this->taxonomy() );
		update_post_meta( $post_id, $this->part_meta(), $part );
	}

	/**
	 * A positive integer from stored meta, else null.
	 *
	 * @param mixed $value Stored value.
	 * @return int|null
	 */
	private function positive_int( $value ): ?int {
		$int = is_numeric( $value ) ? (int) $value : 0;
		return $int > 0 ? $int : null;
	}
}
