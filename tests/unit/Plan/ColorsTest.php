<?php
/**
 * Colors tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Plan;

use Brain\Monkey\Functions;
use Masthead\Plan\Colors;
use Masthead\Tests\Unit\TestCase;

/**
 * Colors tests.
 */
final class ColorsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'sanitize_hex_color' )->alias(
			// Mirrors core: '' comes back as '', invalid input as null.
			static fn( $color ) => '' === $color ? '' : ( is_string( $color ) && 1 === preg_match( '/^#([0-9a-fA-F]{3}){1,2}$/', $color ) ? $color : null )
		);
	}

	private function meta( $value ): void {
		Functions\when( 'get_term_meta' )->justReturn( $value );
	}

	public function test_term_meta_wins_when_valid_hex(): void {
		$this->meta( '#112233' );
		$this->assertSame( '#112233', Colors::for_term( 4, 'technology' ) );
		$this->meta( 'red' );
		$this->assertSame( '#2f5fd0', Colors::for_term( 4, 'technology' ) );
	}

	public function test_no_stored_colour_falls_back_rather_than_returning_empty(): void {
		$this->meta( '' );
		$this->assertSame( '#2f5fd0', Colors::for_term( 4, 'technology' ) );
		$this->assertSame( Colors::PALETTE[0], Colors::for_term( 7, 'recipes' ) );
	}

	public function test_default_by_slug(): void {
		$this->meta( '' );
		$this->assertSame( '#1f7a52', Colors::for_term( 99, 'journal' ) );
		$this->assertSame( '#0f6e7a', Colors::for_term( 99, 'security' ) );
	}

	public function test_unknown_slug_uses_palette_by_term_id(): void {
		$this->meta( '' );
		$this->assertSame( Colors::PALETTE[0], Colors::for_term( 7, 'recipes' ) );
		$this->assertSame( Colors::PALETTE[2], Colors::for_term( 16, 'recipes' ) );
	}

	public function test_legend_marks_only_the_site_default_category(): void {
		$this->meta( '' );
		Functions\when( 'get_option' )->alias(
			static fn( $name ) => 'default_category' === $name ? '1' : array()
		);
		Functions\when( 'get_terms' )->justReturn(
			array(
				(object) array(
					'term_id' => 1,
					'slug'    => 'uncategorized',
					'name'    => 'Uncategorized',
				),
				(object) array(
					'term_id' => 8,
					'slug'    => 'technology',
					'name'    => 'Technology',
				),
			)
		);

		$legend = Colors::legend();

		$this->assertSame(
			array(
				'slug'    => 'uncategorized',
				'name'    => 'Uncategorized',
				'color'   => Colors::PALETTE[1],
				'default' => true,
			),
			$legend[0]
		);
		$this->assertSame(
			array(
				'slug'    => 'technology',
				'name'    => 'Technology',
				'color'   => '#2f5fd0',
				'default' => false,
			),
			$legend[1]
		);
	}

	private static function luminance( string $hex ): float {
		$channels = array_map(
			static function ( $pair ) {
				$value = hexdec( $pair ) / 255;
				return $value <= 0.03928 ? $value / 12.92 : pow( ( $value + 0.055 ) / 1.055, 2.4 );
			},
			str_split( ltrim( $hex, '#' ), 2 )
		);
		return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
	}

	public function test_every_default_and_palette_colour_has_contrast_of_at_least_4_5_on_white(): void {
		foreach ( array_merge( array_values( Colors::DEFAULTS ), Colors::PALETTE ) as $hex ) {
			$ratio = 1.05 / ( self::luminance( $hex ) + 0.05 );
			$this->assertGreaterThanOrEqual( 4.5, $ratio, $hex );
		}
		$this->assertSame( array_values( Colors::DEFAULTS ), Colors::PALETTE );
	}
}
