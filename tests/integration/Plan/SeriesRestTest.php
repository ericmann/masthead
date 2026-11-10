<?php
/**
 * Series planning meta in REST responses.
 *
 * @package Masthead
 */

namespace Masthead\Tests\Integration\Plan;

use Masthead\Plan\SeriesSource;
use Masthead\Tests\Integration\TestCase;

/**
 * masthead_cadence and masthead_lane_exception appear only in edit-context responses (SPEC 2: nothing added to the
 * REST responses the theme uses).
 */
final class SeriesRestTest extends TestCase {

	private const CADENCE = '{"every_weeks":4,"weekday":6,"time":"08:00"}';

	public function tear_down(): void {
		if ( taxonomy_exists( 'series' ) ) {
			unregister_taxonomy( 'series' );
		}
		$GLOBALS['wp_rest_server'] = null;
		parent::tear_down();
	}

	/**
	 * Registers the series meta and starts a fresh REST server so the routes match the taxonomies registered now.
	 *
	 * @param bool $ttm Whether to stand in for ttm-core's public series taxonomy.
	 * @return array{0:string,1:int} REST base and a term ID carrying both meta values.
	 */
	private function series_term( bool $ttm ): array {
		if ( $ttm ) {
			register_taxonomy(
				'series',
				array( 'post' ),
				array(
					'public'       => true,
					'show_in_rest' => true,
				)
			);
		}
		$source = new SeriesSource();
		$source->register();
		$GLOBALS['wp_rest_server'] = null;

		$term_id = self::factory()->term->create(
			array(
				'taxonomy' => $source->taxonomy(),
				'name'     => 'The Mantle',
			)
		);
		update_term_meta( $term_id, 'masthead_cadence', self::CADENCE );
		update_term_meta( $term_id, 'masthead_lane_exception', '1' );
		return array( $source->rest_base(), $term_id );
	}

	private function get( string $path, array $query = array() ): \WP_REST_Response {
		$request = new \WP_REST_Request( 'GET', $path );
		$request->set_query_params( $query );
		return rest_do_request( $request );
	}

	private function assert_meta_hidden( \WP_REST_Response $response, string $label ): void {
		$this->assertSame( 200, $response->get_status(), $label );
		$data  = $response->get_data();
		$items = isset( $data['id'] ) ? array( $data ) : $data;
		$this->assertNotEmpty( $items, $label );
		foreach ( $items as $item ) {
			$meta = (array) ( $item['meta'] ?? array() );
			$this->assertArrayNotHasKey( 'masthead_cadence', $meta, $label );
			$this->assertArrayNotHasKey( 'masthead_lane_exception', $meta, $label );
			$this->assertStringNotContainsString( 'every_weeks', (string) wp_json_encode( $item ), $label );
		}
	}

	public function test_view_context_reads_carry_no_planning_meta(): void {
		foreach ( array( false, true ) as $ttm ) {
			list( $base, $term_id ) = $this->series_term( $ttm );
			wp_set_current_user( 0 );
			$this->assert_meta_hidden( $this->get( '/wp/v2/' . $base . '/' . $term_id ), $base . ' item, anonymous' );
			$this->assert_meta_hidden( $this->get( '/wp/v2/' . $base ), $base . ' list, anonymous' );

			// An editor reading without context=edit gets the view context too.
			wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
			$this->assert_meta_hidden( $this->get( '/wp/v2/' . $base . '/' . $term_id ), $base . ' item, editor' );
			$this->tear_down_series();
		}
	}

	public function test_edit_context_read_by_an_editor_carries_planning_meta(): void {
		foreach ( array( false, true ) as $ttm ) {
			list( $base, $term_id ) = $this->series_term( $ttm );
			wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

			$item = $this->get( '/wp/v2/' . $base . '/' . $term_id, array( 'context' => 'edit' ) );
			$this->assertSame( 200, $item->get_status(), $base );
			$this->assertSame( self::CADENCE, $item->get_data()['meta']['masthead_cadence'], $base );
			$this->assertTrue( $item->get_data()['meta']['masthead_lane_exception'], $base );

			$list = $this->get( '/wp/v2/' . $base, array( 'context' => 'edit' ) );
			$this->assertSame( 200, $list->get_status(), $base );
			$this->assertSame( self::CADENCE, $list->get_data()[0]['meta']['masthead_cadence'], $base );

			// The edit context itself needs edit_terms.
			wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
			$this->assertContains( $this->get( '/wp/v2/' . $base . '/' . $term_id, array( 'context' => 'edit' ) )->get_status(), array( 401, 403 ), $base );
			$this->tear_down_series();
		}
	}

	public function test_writes_still_work_and_answer_in_edit_context(): void {
		list( $base, $term_id ) = $this->series_term( true );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		// The Settings screen saves through core-data, which sends no context; core answers writes in edit context.
		$save = new \WP_REST_Request( 'PUT', '/wp/v2/' . $base . '/' . $term_id );
		$save->set_body_params( array( 'meta' => array( 'masthead_cadence' => '{"every_weeks":2,"weekday":null,"time":"07:30"}' ) ) );
		$response = rest_do_request( $save );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( '{"every_weeks":2,"weekday":null,"time":"07:30"}', $response->get_data()['meta']['masthead_cadence'] );
		$this->assertSame( '{"every_weeks":2,"weekday":null,"time":"07:30"}', get_term_meta( $term_id, 'masthead_cadence', true ) );

		// The swap panel's lane exception option posts with context=edit.
		$mark = new \WP_REST_Request( 'POST', '/wp/v2/' . $base . '/' . $term_id );
		$mark->set_query_params( array( 'context' => 'edit' ) );
		$mark->set_body_params( array( 'meta' => array( 'masthead_lane_exception' => false ) ) );
		$response = rest_do_request( $mark );
		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( $response->get_data()['meta']['masthead_lane_exception'] );
		$this->assertFalse( ( new SeriesSource() )->lane_exception( $term_id ) );

		wp_set_current_user( 0 );
		$this->assert_meta_hidden( $this->get( '/wp/v2/' . $base . '/' . $term_id ), 'after the writes, anonymous' );
	}

	/**
	 * Undoes series_term() inside a test that loops over both taxonomies.
	 *
	 * @return void
	 */
	private function tear_down_series(): void {
		if ( taxonomy_exists( 'series' ) ) {
			unregister_taxonomy( 'series' );
		}
		$GLOBALS['wp_rest_server'] = null;
	}
}
