<?php
/**
 * Fixture seeder.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Cli;

use Masthead\Config;
use Masthead\Plan\Cadence;
use Masthead\Plan\Rhythm;
use Masthead\Plan\SeriesSource;
use Masthead\Plan\Slots;
use Masthead\Support\Dates;

/**
 * Loads a fixture into a local site. Posts are inserted and deleted only through Slots.
 */
final class Seeder {

	/**
	 * Options removed by reset().
	 *
	 * @var string[]
	 */
	private const OPTIONS = array(
		'masthead_rhythm',
		'masthead_findings',
		'masthead_ai_usage',
		'masthead_settings',
		'masthead_seed_now',
	);

	/**
	 * Constructor.
	 *
	 * @param Slots        $slots  Slots.
	 * @param SeriesSource $series Series adapter.
	 */
	public function __construct( private Slots $slots, private SeriesSource $series ) {}

	/**
	 * Deletes every post of the configured types, every series term, and Masthead's options.
	 *
	 * @return void
	 */
	public function reset(): void {
		$batch = (int) Config::get( 'calendar.max_posts_per_range' );
		do {
			$ids = get_posts(
				array(
					'post_type'      => Config::get( 'post_types' ),
					'post_status'    => 'any',
					'posts_per_page' => $batch,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);
			$deleted = 0;
			foreach ( $ids as $id ) {
				if ( $this->slots->delete( (int) $id ) ) {
					++$deleted;
				}
			}
		} while ( $deleted > 0 );

		$taxonomy = $this->series->taxonomy();
		do {
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'fields'     => 'ids',
					'number'     => $batch,
				)
			);
			$terms = is_array( $terms ) ? $terms : array();
			foreach ( $terms as $term_id ) {
				wp_delete_term( (int) $term_id, $taxonomy );
			}
		} while ( array() !== $terms );

		foreach ( self::OPTIONS as $option ) {
			delete_option( $option );
		}
		delete_metadata( 'user', 0, 'masthead_dismissed', '', true );
	}

	/**
	 * Loads a fixture.
	 *
	 * @param array       $fixture Decoded fixture.
	 * @param string|null $now     Pinned now (Y-m-d H:i:s), or null to clear the pin.
	 * @return int Number of posts inserted.
	 */
	public function seed( array $fixture, ?string $now ): int {
		if ( isset( $fixture['timezone'] ) && is_string( $fixture['timezone'] ) ) {
			update_option( 'timezone_string', $fixture['timezone'] );
		}

		$category_ids = array();
		foreach ( (array) ( $fixture['categories'] ?? array() ) as $category ) {
			$slug = (string) ( $category['slug'] ?? '' );
			if ( '' === $slug ) {
				continue;
			}
			$existing = get_term_by( 'slug', $slug, 'category' );
			if ( is_object( $existing ) ) {
				$category_ids[ $slug ] = (int) $existing->term_id;
				continue;
			}
			$created = wp_insert_term( (string) ( $category['name'] ?? $slug ), 'category', array( 'slug' => $slug ) );
			if ( is_array( $created ) ) {
				$category_ids[ $slug ] = (int) $created['term_id'];
			}
		}

		Rhythm::reinstall_defaults();

		$series_ids = array();
		foreach ( (array) ( $fixture['series'] ?? array() ) as $series ) {
			$created = wp_insert_term( (string) ( $series['name'] ?? '' ), $this->series->taxonomy(), array( 'slug' => (string) ( $series['slug'] ?? '' ) ) );
			if ( ! is_array( $created ) ) {
				continue;
			}
			$term_id                                = (int) $created['term_id'];
			$series_ids[ (string) $series['slug'] ] = $term_id;
			$cadence                                = Cadence::sanitize( $series['masthead_cadence'] ?? null );
			if ( null !== $cadence ) {
				update_term_meta( $term_id, 'masthead_cadence', Cadence::to_json( $cadence ) );
			}
			update_term_meta( $term_id, $this->series->total_meta(), (int) ( $series['ttm_total_parts'] ?? 0 ) );
		}

		$author   = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => 'ID',
			)
		);
		$author   = array() === $author ? 0 : (int) $author[0];
		$inserted = 0;
		foreach ( (array) ( $fixture['posts'] ?? array() ) as $post ) {
			$postarr                = self::post_array( $post, $category_ids );
			$postarr['post_author'] = $author;
			$post_id                = $this->slots->insert( $postarr, (string) $post['status'] );
			if ( is_wp_error( $post_id ) ) {
				continue;
			}
			++$inserted;
			$series_slug = (string) ( $post['series'] ?? '' );
			if ( '' !== $series_slug && isset( $series_ids[ $series_slug ] ) ) {
				$this->series->set_part( $post_id, $series_ids[ $series_slug ], (int) ( $post['part'] ?? 0 ) );
			}
			if ( 'future' === $post['status'] ) {
				wp_clear_scheduled_hook( 'publish_future_post', array( $post_id ) );
			}
		}

		if ( null !== $now && null !== Dates::parse_local( $now ) ) {
			update_option( 'masthead_seed_now', $now );
		} else {
			delete_option( 'masthead_seed_now' );
		}
		return $inserted;
	}

	/**
	 * Insert arguments for one fixture post.
	 *
	 * @param array             $post         Fixture post.
	 * @param array<string,int> $category_ids Category term IDs by slug.
	 * @return array<string,mixed>
	 */
	public static function post_array( array $post, array $category_ids ): array {
		$postarr = array(
			'import_id'     => (int) $post['id'],
			'post_type'     => 'post',
			'post_title'    => (string) $post['title'],
			'post_category' => array(),
		);
		foreach ( (array) ( $post['categories'] ?? array() ) as $slug ) {
			if ( isset( $category_ids[ $slug ] ) ) {
				$postarr['post_category'][] = $category_ids[ $slug ];
			}
		}
		if ( ! empty( $post['tags'] ) ) {
			$postarr['tags_input'] = array_map( 'strval', (array) $post['tags'] );
		}
		if ( null === ( $post['date'] ?? null ) ) {
			// Undated draft: the draft's modified date becomes its stored date; no GMT date keeps it floating.
			$postarr['post_date'] = (string) ( $post['modified'] ?? '' );
			return $postarr;
		}
		$postarr['post_date']     = (string) $post['date'];
		$postarr['post_date_gmt'] = Dates::local_to_gmt( (string) $post['date'] );
		$postarr['edit_date']     = true;
		return $postarr;
	}
}
