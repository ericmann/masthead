<?php
/**
 * SeriesSource tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Plan;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Masthead\Plan\SeriesSource;
use Masthead\Tests\Unit\TestCase;

/**
 * SeriesSource tests.
 */
final class SeriesSourceTest extends TestCase {

	public function test_taxonomy_is_series_when_registered(): void {
		Functions\when( 'taxonomy_exists' )->alias( static fn( $name ) => 'series' === $name );
		$this->assertSame( 'series', ( new SeriesSource() )->taxonomy() );
	}

	public function test_taxonomy_falls_back_to_masthead_series(): void {
		Functions\when( 'taxonomy_exists' )->justReturn( false );
		$this->assertSame( 'masthead_series', ( new SeriesSource() )->taxonomy() );
	}

	public function test_filter_value_must_be_a_registered_taxonomy(): void {
		Functions\when( 'taxonomy_exists' )->alias( static fn( $name ) => 'shows' === $name );
		Filters\expectApplied( 'masthead_series_taxonomy' )->once()->andReturn( 'shows' );
		$this->assertSame( 'shows', ( new SeriesSource() )->taxonomy() );
		Filters\expectApplied( 'masthead_series_taxonomy' )->once()->andReturn( 'nope' );
		$this->assertSame( 'masthead_series', ( new SeriesSource() )->taxonomy() );
		Filters\expectApplied( 'masthead_series_taxonomy' )->once()->andReturn( 42 );
		$this->assertSame( 'masthead_series', ( new SeriesSource() )->taxonomy() );
	}

	/**
	 * A stand-in for WP_REST_Response.
	 *
	 * @param array $data Response data.
	 * @return object
	 */
	private function response( array $data ): object {
		return new class( $data ) {
			public function __construct( private array $data ) {}
			public function get_data(): array {
				return $this->data;
			}
			public function set_data( array $data ): void {
				$this->data = $data;
			}
		};
	}

	private function term_data(): array {
		return array(
			'id'   => 3,
			'name' => 'The Mantle',
			'meta' => array(
				'ttm_total_parts'         => 4,
				'masthead_cadence'        => '{"every_weeks":4,"weekday":6,"time":"08:00"}',
				'masthead_lane_exception' => true,
			),
		);
	}

	public function test_planning_meta_is_removed_outside_the_edit_context(): void {
		$source = new SeriesSource();
		foreach ( array( array( 'context' => 'view' ), array( 'context' => 'embed' ), array() ) as $params ) {
			$response = $source->hide_planning_meta( $this->response( $this->term_data() ), null, new \ArrayObject( $params ) );
			$this->assertSame( array( 'ttm_total_parts' => 4 ), $response->get_data()['meta'], (string) ( $params['context'] ?? 'none' ) );
			$this->assertSame( 'The Mantle', $response->get_data()['name'] );
		}
	}

	public function test_planning_meta_is_kept_in_the_edit_context(): void {
		$response = ( new SeriesSource() )->hide_planning_meta( $this->response( $this->term_data() ), null, new \ArrayObject( array( 'context' => 'edit' ) ) );
		$this->assertSame( $this->term_data(), $response->get_data() );
	}

	public function test_other_responses_pass_through(): void {
		$source = new SeriesSource();
		$this->assertSame( 'nope', $source->hide_planning_meta( 'nope', null, new \ArrayObject() ) );
		$plain = $this->response( array( 'id' => 3 ) );
		$this->assertSame( array( 'id' => 3 ), $source->hide_planning_meta( $plain, null, new \ArrayObject() )->get_data() );
		$no_request = $source->hide_planning_meta( $this->response( $this->term_data() ), null, null );
		$this->assertArrayNotHasKey( 'masthead_cadence', $no_request->get_data()['meta'] );
	}

	public function test_register_hooks_the_filter_for_the_resolved_taxonomy(): void {
		Functions\when( 'taxonomy_exists' )->alias( static fn( $name ) => 'series' === $name );
		Functions\when( 'register_term_meta' )->justReturn( true );
		$source = new SeriesSource();
		$source->register();
		$this->assertSame( PHP_INT_MAX, has_filter( 'rest_prepare_series', array( $source, 'hide_planning_meta' ) ) );
		$this->assertSame( array( 'masthead_cadence', 'masthead_lane_exception' ), SeriesSource::EDIT_ONLY_META );
	}

	public function test_meta_names_follow_the_taxonomy(): void {
		Functions\when( 'taxonomy_exists' )->alias( static fn( $name ) => 'series' === $name );
		$ttm = new SeriesSource();
		$this->assertSame( 'ttm_series_part', $ttm->part_meta() );
		$this->assertSame( 'ttm_total_parts', $ttm->total_meta() );

		Functions\when( 'taxonomy_exists' )->justReturn( false );
		$own = new SeriesSource();
		$this->assertSame( 'masthead_series_part', $own->part_meta() );
		$this->assertSame( 'masthead_total_parts', $own->total_meta() );
	}
}
