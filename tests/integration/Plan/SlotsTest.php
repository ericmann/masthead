<?php
/**
 * Slots integration tests.
 *
 * @package Masthead
 */

namespace Masthead\Tests\Integration\Plan;

use Masthead\Plan\SeriesSource;
use Masthead\Plan\Slots;
use Masthead\Tests\Integration\TestCase;

/**
 * Slots integration tests.
 */
final class SlotsTest extends TestCase {

	/**
	 * Slots under test.
	 *
	 * @var Slots
	 */
	private Slots $slots;

	/**
	 * Series adapter.
	 *
	 * @var SeriesSource
	 */
	private SeriesSource $series;

	public function set_up(): void {
		parent::set_up();
		$this->series = new SeriesSource();
		$this->series->register();
		$this->slots = new Slots( $this->series );
		$this->slots->register_meta();
		self::factory()->category->create(
			array(
				'slug' => 'technology',
				'name' => 'Technology',
			) 
		);
	}

	private function create( array $extra = array() ) {
		return $this->slots->create(
			array_merge(
				array(
					'date'     => '2026-11-18',
					'time'     => '09:30',
					'title'    => 'A planned slot',
					'category' => 'technology',
				),
				$extra
			)
		);
	}

	public function test_created_draft_is_dated_planned_and_categorised(): void {
		$id   = $this->create( array( 'outline' => array( 'First', 'Second <b>' ) ) );
		$post = get_post( $id );
		$this->assertSame( 'draft', $post->post_status );
		$this->assertSame( '2026-11-18 09:30:00', $post->post_date );
		$this->assertSame( '2026-11-18 17:30:00', $post->post_date_gmt );
		$this->assertTrue( Slots::is_dated( $post ) );
		$this->assertSame( array( 'technology' ), wp_list_pluck( get_the_category( $id ), 'slug' ) );
		$this->assertTrue( (bool) get_post_meta( $id, 'masthead_planned', true ) );
		$this->assertSame( '["First","Second <b>"]', get_post_meta( $id, 'masthead_outline', true ) );
		$this->assertStringContainsString( 'masthead-outline', $post->post_content );
		$this->assertStringNotContainsString( '<b>', $post->post_content );
	}

	public function test_dated_draft_keeps_both_dates_after_rest_title_update(): void {
		$id = $this->create();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$request = new \WP_REST_Request( 'POST', '/wp/v2/posts/' . $id );
		$request->set_body_params( array( 'title' => 'Renamed' ) );
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$post = get_post( $id );
		$this->assertSame( 'Renamed', $post->post_title );
		$this->assertSame( '2026-11-18 09:30:00', $post->post_date );
		$this->assertSame( '2026-11-18 17:30:00', $post->post_date_gmt );
		$this->assertSame( 'draft', $post->post_status );
	}

	public function test_series_term_and_part_are_set(): void {
		$term_id = self::factory()->term->create(
			array(
				'taxonomy' => $this->series->taxonomy(),
				'name' => 'The Mantle',
			) 
		);
		$id      = $this->create(
			array(
				'series' => array(
					'term_id' => $term_id,
					'part' => 2,
				),
			) 
		);
		$found   = $this->series->for_post( $id );
		$this->assertSame( $term_id, $found['term_id'] );
		$this->assertSame( 2, $found['part'] );
	}

	public function test_action_fires_with_post_id(): void {
		$fired = array();
		add_action(
			'masthead_planned_draft_created',
			static function ( $post_id ) use ( &$fired ) {
				$fired[] = $post_id;
			}
		);
		$id = $this->create();
		$this->assertSame( array( $id ), $fired );
	}

	public function test_insert_keeps_future_status_for_a_date_in_the_wall_clock_past(): void {
		$id   = $this->slots->insert(
			array(
				'post_title'    => 'Past future',
				'post_date'     => '2020-01-01 08:00:00',
				'post_date_gmt' => '2020-01-01 16:00:00',
				'edit_date'     => true,
			),
			'future'
		);
		$this->assertSame( 'future', get_post_status( $id ) );
		$published = $this->slots->insert(
			array(
				'post_title'    => 'Far future publish',
				'post_date'     => '2090-01-01 08:00:00',
				'post_date_gmt' => '2090-01-01 16:00:00',
				'edit_date'     => true,
			),
			'publish'
		);
		$this->assertSame( 'publish', get_post_status( $published ) );
		$this->assertTrue( $this->slots->delete( $id ) );
		$this->assertNull( get_post( $id ) );
	}
}
