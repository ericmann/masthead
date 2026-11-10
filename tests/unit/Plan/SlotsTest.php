<?php
/**
 * Slots tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Plan;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Masthead\Plan\SeriesSource;
use Masthead\Plan\Slots;
use Masthead\Tests\Unit\TestCase;

/**
 * Slots tests.
 */
final class SlotsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_option' )->alias(
			static fn( $name, $fallback = false ) => 'masthead_settings' === $name ? array() : $fallback
		);
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'sanitize_text_field' )->alias( static fn( $v ) => trim( (string) $v ) );
		Functions\when( 'is_wp_error' )->alias( static fn( $v ) => $v instanceof \WP_Error );
		Functions\when( 'update_post_meta' )->justReturn( true );
	}

	private function slots(): Slots {
		return new Slots( new SeriesSource() );
	}

	public function test_create_rejects_invalid_date(): void {
		Functions\expect( 'wp_insert_post' )->never();
		foreach ( array( '2026-02-30', 'soon', '' ) as $date ) {
			$result = $this->slots()->create(
				array(
					'date'     => $date,
					'title'    => 'T',
					'category' => 'faith',
				)
			);
			$this->assertInstanceOf( \WP_Error::class, $result );
			$this->assertSame( 'masthead_invalid_date', $result->get_error_code() );
			$this->assertSame( array( 'status' => 400 ), $result->get_error_data() );
		}
		$bad_time = $this->slots()->create(
			array(
				'date'     => '2026-11-18',
				'time'     => '25:00',
				'title'    => 'T',
				'category' => 'faith',
			)
		);
		$this->assertSame( 'masthead_invalid_date', $bad_time->get_error_code() );
	}

	public function test_create_rejects_unknown_category(): void {
		Functions\when( 'get_term_by' )->justReturn( false );
		Functions\expect( 'wp_insert_post' )->never();
		$result = $this->slots()->create(
			array(
				'date'     => '2026-11-18',
				'title'    => 'T',
				'category' => 'nope',
			)
		);
		$this->assertSame( 'masthead_unknown_category', $result->get_error_code() );
		$this->assertSame( array( 'status' => 400 ), $result->get_error_data() );
	}

	public function test_create_defaults_time_to_the_lane_time(): void {
		Functions\when( 'get_term_by' )->justReturn( (object) array( 'term_id' => 4 ) );
		Functions\expect( 'wp_insert_post' )->once()->andReturnUsing(
			function ( $postarr ) {
				// 2026-11-18 is a Wednesday: the standalone lane, 08:00.
				$this->assertSame( '2026-11-18 08:00:00', $postarr['post_date'] );
				$this->assertSame( '2026-11-18 16:00:00', $postarr['post_date_gmt'] );
				$this->assertTrue( $postarr['edit_date'] );
				$this->assertSame( array( 4 ), $postarr['post_category'] );
				$this->assertSame( 'Title', $postarr['post_title'] );
				return 77;
			}
		);
		Actions\expectDone( 'masthead_planned_draft_created' )->once()->with( 77 );
		$this->assertSame(
			77,
			$this->slots()->create(
				array(
					'date'     => '2026-11-18',
					'title'    => ' Title ',
					'category' => 'faith',
				)
			)
		);
	}

	public function test_outline_markup_is_a_group_with_a_list_and_escaped_items(): void {
		$html = Slots::outline_markup( array( 'One', 'A <b>bold</b> & claim' ) );
		$this->assertStringStartsWith( '<!-- wp:group {"className":"masthead-outline"} -->', $html );
		$this->assertStringContainsString( '<!-- wp:list -->', $html );
		$this->assertStringContainsString( '<li>One</li>', $html );
		$this->assertStringNotContainsString( '<b>', $html );
		$this->assertStringEndsWith( '<!-- /wp:group -->', $html );
		$this->assertSame( '', Slots::outline_markup( array() ) );
	}

	public function test_insert_pins_status_and_removes_its_filter(): void {
		$seen = null;
		Functions\expect( 'wp_insert_post' )->once()->andReturnUsing(
			function () use ( &$seen ) {
				$seen = apply_filters( 'wp_insert_post_data', array( 'post_status' => 'publish' ) );
				return 5;
			}
		);
		Filters\expectAdded( 'wp_insert_post_data' )->once();
		$this->assertSame( 5, $this->slots()->insert( array( 'post_title' => 'x' ), 'future' ) );
		$this->assertTrue( Filters\has( 'wp_insert_post_data' ) === false );
		$this->assertIsArray( $seen );
	}

	public function test_is_dated_is_false_for_zero_gmt_date(): void {
		$this->assertFalse( Slots::is_dated( (object) array( 'post_date_gmt' => '0000-00-00 00:00:00' ) ) );
		$this->assertTrue( Slots::is_dated( (object) array( 'post_date_gmt' => '2026-11-18 16:00:00' ) ) );
	}
}
