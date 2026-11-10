<?php
/**
 * Category helpers.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Support;

use Masthead\Config;

/**
 * A post's primary category is its category term with the lowest term_id.
 */
final class Categories {

	/**
	 * The primary category slug of a set of terms.
	 *
	 * @param array<int,object> $terms Objects with term_id and slug.
	 * @return string|null
	 */
	public static function primary( array $terms ): ?string {
		$best = null;
		foreach ( $terms as $term ) {
			if ( null === $best || (int) $term->term_id < (int) $best->term_id ) {
				$best = $term;
			}
		}
		return null === $best ? null : (string) $best->slug;
	}

	/**
	 * Category slugs and primary category of a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array{slugs:string[],primary:?string}
	 */
	public static function for_post( int $post_id ): array {
		$terms = get_the_terms( $post_id, 'category' );
		if ( ! is_array( $terms ) ) {
			return array(
				'slugs'   => array(),
				'primary' => null,
			);
		}
		usort(
			$terms,
			static fn( $a, $b ) => (int) $a->term_id <=> (int) $b->term_id
		);
		return array(
			'slugs'   => array_map( static fn( $term ) => (string) $term->slug, $terms ),
			'primary' => self::primary( $terms ),
		);
	}

	/**
	 * Every category, bounded by desk.candidate_pool.
	 *
	 * @return array<int,array{term_id:int,slug:string,name:string}>
	 */
	public static function all(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => false,
				'orderby'    => 'term_id',
				'order'      => 'ASC',
				'number'     => (int) Config::get( 'desk.candidate_pool' ),
			)
		);
		if ( ! is_array( $terms ) ) {
			return array();
		}
		$out = array();
		foreach ( $terms as $term ) {
			$out[] = array(
				'term_id' => (int) $term->term_id,
				'slug'    => (string) $term->slug,
				'name'    => (string) $term->name,
			);
		}
		return $out;
	}
}
