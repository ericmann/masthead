<?php
/**
 * Planned slots: dated drafts.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Plan;

use Masthead\Config;
use Masthead\Support\Dates;
use Masthead\Support\Json;
use WP_Error;

/**
 * Creates planned slots and owns every post insert and delete in the plugin.
 */
final class Slots {

	/**
	 * Constructor.
	 *
	 * @param SeriesSource $series Series adapter.
	 */
	public function __construct( private SeriesSource $series ) {}

	/**
	 * Registers the planning post meta. Not exposed over REST, so theme-facing responses never change.
	 *
	 * @return void
	 */
	public function register_meta(): void {
		$meta = array(
			'masthead_planned'     => 'boolean',
			'masthead_outline'     => 'string',
			'masthead_last_shared' => 'string',
		);
		foreach ( (array) Config::get( 'post_types' ) as $post_type ) {
			foreach ( $meta as $key => $type ) {
				register_post_meta(
					(string) $post_type,
					$key,
					array(
						'type'         => $type,
						'single'       => true,
						'show_in_rest' => false,
					)
				);
			}
		}
	}

	/**
	 * The only wp_insert_post call. The status is pinned for its duration: core flips
	 * future and publish by the wall clock, and Masthead never changes a status it was not asked to set.
	 *
	 * @param array  $postarr Arguments for wp_insert_post().
	 * @param string $status  Status to keep.
	 * @return int|WP_Error
	 */
	public function insert( array $postarr, string $status ) {
		$pin = static function ( $data ) use ( $status ) {
			$data['post_status'] = $status;
			return $data;
		};
		add_filter( 'wp_insert_post_data', $pin, PHP_INT_MAX );
		try {
			return wp_insert_post( $postarr, true );
		} finally {
			remove_filter( 'wp_insert_post_data', $pin, PHP_INT_MAX );
		}
	}

	/**
	 * The only wp_delete_post call (force delete).
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function delete( int $post_id ): bool {
		return false !== wp_delete_post( $post_id, true );
	}

	/**
	 * Whether a post has a fixed date.
	 *
	 * @param object $post Post.
	 * @return bool
	 */
	public static function is_dated( object $post ): bool {
		return '0000-00-00 00:00:00' !== (string) $post->post_date_gmt;
	}

	/**
	 * Creates a planned, dated draft.
	 *
	 * @param array $input { date, time?, title, category, series?: { term_id, part }, outline?: string[] }.
	 * @return int|WP_Error Post ID.
	 */
	public function create( array $input ) {
		$date = isset( $input['date'] ) && is_string( $input['date'] ) ? $input['date'] : '';
		if ( ! Dates::is_date( $date ) ) {
			return new WP_Error( 'masthead_invalid_date', __( 'The date must be a real YYYY-MM-DD date.', 'masthead' ), array( 'status' => 400 ) );
		}
		$time = $input['time'] ?? '';
		if ( null === $time || '' === $time ) {
			$time = Lanes::for_date( $date )['time'];
		}
		if ( ! is_string( $time ) || ! Dates::is_time( $time ) ) {
			return new WP_Error( 'masthead_invalid_date', __( 'The time must be HH:MM in 24-hour format.', 'masthead' ), array( 'status' => 400 ) );
		}
		$slug     = isset( $input['category'] ) && is_string( $input['category'] ) ? $input['category'] : '';
		$category = '' === $slug ? false : get_term_by( 'slug', $slug, 'category' );
		if ( ! is_object( $category ) ) {
			return new WP_Error( 'masthead_unknown_category', __( 'The category does not exist.', 'masthead' ), array( 'status' => 400 ) );
		}

		$local   = Dates::combine( $date, $time );
		$outline = Json::string_list( $input['outline'] ?? array() );
		$types   = (array) Config::get( 'post_types' );

		$post_id = $this->insert(
			array(
				'post_type'     => (string) reset( $types ),
				'post_title'    => sanitize_text_field( (string) ( $input['title'] ?? '' ) ),
				'post_content'  => self::outline_markup( $outline ),
				'post_date'     => $local,
				'post_date_gmt' => Dates::local_to_gmt( $local ),
				'edit_date'     => true,
				'post_category' => array( (int) $category->term_id ),
			),
			'draft'
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( $post_id, 'masthead_planned', true );
		if ( array() !== $outline ) {
			update_post_meta( $post_id, 'masthead_outline', Json::encode( $outline ) );
		}
		$series = $input['series'] ?? null;
		if ( is_array( $series ) && isset( $series['term_id'], $series['part'] ) ) {
			$this->series->set_part( $post_id, (int) $series['term_id'], (int) $series['part'] );
		}

		/**
		 * Fires after a planned draft is created.
		 *
		 * @param int $post_id Post ID.
		 */
		do_action( 'masthead_planned_draft_created', $post_id );
		return $post_id;
	}

	/**
	 * Block markup for an outline: a core/group containing a core/list.
	 *
	 * @param string[] $items Outline items.
	 * @return string
	 */
	public static function outline_markup( array $items ): string {
		if ( array() === $items ) {
			return '';
		}
		$list = '';
		foreach ( $items as $item ) {
			$list .= '<!-- wp:list-item --><li>' . esc_html( $item ) . '</li><!-- /wp:list-item -->';
		}
		return '<!-- wp:group {"className":"masthead-outline"} --><div class="wp-block-group masthead-outline">'
			. '<!-- wp:list --><ul class="wp-block-list">' . $list . '</ul><!-- /wp:list -->'
			. '</div><!-- /wp:group -->';
	}
}
