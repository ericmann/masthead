<?php
/**
 * SeriesSource integration tests.
 *
 * @package Masthead
 */

namespace Masthead\Tests\Integration\Plan;

use Masthead\Plan\SeriesSource;
use Masthead\Tests\Integration\TestCase;

/**
 * SeriesSource integration tests.
 */
final class SeriesSourceTest extends TestCase {

	public function test_registers_masthead_series_when_series_is_absent(): void {
		$source = new SeriesSource();
		// The test case clears registered meta between tests, so register again.
		$source->register();
		$this->assertSame( 'masthead_series', $source->taxonomy() );
		$this->assertTrue( taxonomy_exists( 'masthead_series' ) );
		$taxonomy = get_taxonomy( 'masthead_series' );
		$this->assertFalse( $taxonomy->public );
		$this->assertFalse( $taxonomy->hierarchical );
		$this->assertTrue( $taxonomy->show_in_rest );
		$this->assertFalse( $taxonomy->rewrite );
		$keys = get_registered_meta_keys( 'term', 'masthead_series' );
		$this->assertArrayHasKey( 'masthead_cadence', $keys );
		$this->assertArrayHasKey( 'masthead_lane_exception', $keys );
		$this->assertArrayHasKey( 'masthead_total_parts', $keys );
		$this->assertArrayHasKey( 'masthead_series_part', get_registered_meta_keys( 'post', 'post' ) );
	}

	public function test_uses_stub_series_taxonomy_and_ttm_meta_when_present(): void {
		register_taxonomy(
			'series',
			array( 'post' ),
			array(
				'public' => false,
				'show_in_rest' => true,
			) 
		);
		try {
			$source = new SeriesSource();
			$source->register();
			$this->assertSame( 'series', $source->taxonomy() );
			$this->assertSame( 'ttm_series_part', $source->part_meta() );
			$this->assertSame( 'ttm_total_parts', $source->total_meta() );
			$this->assertArrayHasKey( 'masthead_cadence', get_registered_meta_keys( 'term', 'series' ) );

			$term = self::factory()->term->create_and_get(
				array(
					'taxonomy' => 'series',
					'name' => 'The Mantle',
				) 
			);
			update_term_meta( $term->term_id, 'ttm_total_parts', 5 );
			$post = self::factory()->post->create();
			$source->set_part( $post, $term->term_id, 2 );
			$this->assertSame( '2', get_post_meta( $post, 'ttm_series_part', true ) );
			$found = $source->for_post( $post );
			$this->assertSame( $term->term_id, $found['term_id'] );
			$this->assertSame( 2, $found['part'] );
			$this->assertSame( 5, $found['total'] );
			$this->assertSame( 'series', $source->rest_base() );
		} finally {
			unregister_taxonomy( 'series' );
		}
		$this->assertSame( 'masthead_series', ( new SeriesSource() )->taxonomy() );
	}

	public function test_term_meta_is_exposed_in_rest(): void {
		( new SeriesSource() )->register();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$term_id = self::factory()->term->create(
			array(
				'taxonomy' => 'masthead_series',
				'name' => 'REST Series',
			) 
		);
		$request = new \WP_REST_Request( 'POST', '/wp/v2/masthead_series/' . $term_id );
		$request->set_body_params(
			array(
				'meta' => array(
					'masthead_cadence'        => '{"every_weeks":4,"weekday":6,"time":"08:00"}',
					'masthead_lane_exception' => true,
				),
			)
		);
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$meta = $response->get_data()['meta'];
		$this->assertSame( '{"every_weeks":4,"weekday":6,"time":"08:00"}', $meta['masthead_cadence'] );
		$this->assertTrue( $meta['masthead_lane_exception'] );
		$this->assertSame( 'masthead_series', ( new SeriesSource() )->rest_base() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$denied = new \WP_REST_Request( 'POST', '/wp/v2/masthead_series/' . $term_id );
		$denied->set_body_params( array( 'meta' => array( 'masthead_lane_exception' => false ) ) );
		$this->assertContains( rest_do_request( $denied )->get_status(), array( 401, 403 ) );
	}

	public function test_parts_are_ordered_and_report_dated_flag(): void {
		$source  = new SeriesSource();
		$term_id = self::factory()->term->create(
			array(
				'taxonomy' => 'masthead_series',
				'name' => 'Ordered',
			) 
		);
		$late    = self::factory()->post->create(
			array(
				'post_status' => 'draft',
				'post_date' => '2026-12-14 08:00:00',
				'post_date_gmt' => '2026-12-14 16:00:00',
			) 
		);
		$early   = self::factory()->post->create(
			array(
				'post_status' => 'draft',
				'post_date' => '2026-11-09 08:00:00',
				'post_date_gmt' => '2026-11-09 16:00:00',
			) 
		);
		$undated = self::factory()->post->create(
			array(
				'post_status' => 'draft',
				'post_date_gmt' => '0000-00-00 00:00:00',
			) 
		);
		$source->set_part( $late, $term_id, 2 );
		$source->set_part( $early, $term_id, 1 );
		$source->set_part( $undated, $term_id, 3 );

		$parts = $source->parts( $term_id );
		$this->assertSame( array( $early, $late, $undated ), wp_list_pluck( $parts, 'post_id' ) );
		$this->assertSame( array( 1, 2, 3 ), wp_list_pluck( $parts, 'part' ) );
		$this->assertSame( array( true, true, false ), wp_list_pluck( $parts, 'dated' ) );
		$this->assertSame( '2026-11-09 08:00:00', $parts[0]['date'] );
		$this->assertNull( $parts[2]['date'] );
		$this->assertSame( 'draft', $parts[0]['status'] );
	}
}
