<?php
/**
 * Category colours.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Plan;

use Masthead\Support\Categories;

/**
 * Resolves the colour of each category. All colours pass 4.5:1 against white.
 */
final class Colors {

	/**
	 * Term meta key.
	 */
	public const META = 'masthead_color';

	/**
	 * Default colour by category slug.
	 */
	public const DEFAULTS = array(
		'technology' => '#2f5fd0',
		'business'   => '#8a5409',
		'faith'      => '#7a5c2e',
		'writing'    => '#b8325a',
		'journal'    => '#1f7a52',
		'opinion'    => '#6d45c9',
		'security'   => '#0f6e7a',
	);

	/**
	 * Palette for categories without a default.
	 */
	public const PALETTE = array(
		'#2f5fd0',
		'#8a5409',
		'#7a5c2e',
		'#b8325a',
		'#1f7a52',
		'#6d45c9',
		'#0f6e7a',
	);

	/**
	 * Registers the term meta.
	 *
	 * @return void
	 */
	public static function register_meta(): void {
		register_term_meta(
			'category',
			self::META,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_hex_color',
				'auth_callback'     => static fn() => current_user_can( 'manage_categories' ),
			)
		);
	}

	/**
	 * The colour of a category.
	 *
	 * @param int    $term_id Term ID.
	 * @param string $slug    Term slug.
	 * @return string Hex colour.
	 */
	public static function for_term( int $term_id, string $slug ): string {
		$stored = get_term_meta( $term_id, self::META, true );
		// Core's sanitize_hex_color() returns '' (not null) for '', which is
		// also what get_term_meta() returns when no colour is stored.
		$valid = is_string( $stored ) ? sanitize_hex_color( $stored ) : null;
		if ( is_string( $valid ) && '' !== $valid ) {
			return $valid;
		}
		if ( isset( self::DEFAULTS[ $slug ] ) ) {
			return self::DEFAULTS[ $slug ];
		}
		return self::PALETTE[ $term_id % count( self::PALETTE ) ];
	}

	/**
	 * Legend entries for every category.
	 *
	 * @return array<int,array{slug:string,name:string,color:string}>
	 */
	public static function legend(): array {
		$legend = array();
		foreach ( Categories::all() as $category ) {
			$legend[] = array(
				'slug'  => $category['slug'],
				'name'  => $category['name'],
				'color' => self::for_term( $category['term_id'], $category['slug'] ),
			);
		}
		return $legend;
	}
}
