<?php
/**
 * Categories tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Support;

use Brain\Monkey\Functions;
use Masthead\Support\Categories;
use Masthead\Tests\Unit\TestCase;

/**
 * Categories tests.
 */
final class CategoriesTest extends TestCase {

	private function term( int $id, string $slug, string $name = '' ): object {
		return (object) array(
			'term_id' => $id,
			'slug'    => $slug,
			'name'    => '' === $name ? ucfirst( $slug ) : $name,
		);
	}

	public function test_primary_is_lowest_term_id(): void {
		$this->assertSame( 'faith', Categories::primary( array( $this->term( 9, 'writing' ), $this->term( 3, 'faith' ), $this->term( 5, 'business' ) ) ) );
	}

	public function test_primary_is_null_without_categories(): void {
		$this->assertNull( Categories::primary( array() ) );
	}

	public function test_for_post_sorts_slugs_by_term_id(): void {
		Functions\when( 'get_the_terms' )->justReturn( array( $this->term( 9, 'writing' ), $this->term( 3, 'faith' ) ) );
		$this->assertSame(
			array(
				'slugs'   => array( 'faith', 'writing' ),
				'primary' => 'faith',
			),
			Categories::for_post( 12156 )
		);
		Functions\when( 'get_the_terms' )->justReturn( false );
		$this->assertSame(
			array(
				'slugs'   => array(),
				'primary' => null,
			),
			Categories::for_post( 1 )
		);
	}

	public function test_all_is_bounded_by_candidate_pool(): void {
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\expect( 'get_terms' )->once()->andReturnUsing(
			function ( $args ) {
				$this->assertSame( 50, $args['number'] );
				$this->assertSame( 'category', $args['taxonomy'] );
				return array( $this->term( 4, 'security' ) );
			}
		);
		$this->assertSame(
			array(
				array(
					'term_id' => 4,
					'slug'    => 'security',
					'name'    => 'Security',
				),
			),
			Categories::all()
		);
	}
}
