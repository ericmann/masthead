<?php
/**
 * PlanReader tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Plan;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Masthead\Plan\PlanReader;
use Masthead\Plan\SeriesSource;
use Masthead\Support\Clock;
use Masthead\Tests\Unit\TestCase;

/**
 * PlanReader tests.
 */
final class PlanReaderTest extends TestCase {

	/**
	 * Arguments of the last get_posts call.
	 *
	 * @var array
	 */
	private array $query_args = array();

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_option' )->alias(
			static fn( $name, $fallback = false ) => 'masthead_settings' === $name ? array() : $fallback
		);
		Filters\expectApplied( 'masthead_now' )->zeroOrMoreTimes()->andReturn( '2026-11-09 14:00:00' );
		Functions\when( 'taxonomy_exists' )->justReturn( false );
		Functions\when( 'get_the_terms' )->alias(
			static fn( $id, $taxonomy ) => 1 === $id && 'category' === $taxonomy
				? array(
					(object) array(
						'term_id' => 3,
						'slug'    => 'business',
					),
					(object) array(
						'term_id' => 2,
						'slug'    => 'faith',
					),
				)
				: false
		);
		Functions\when( 'get_term_meta' )->justReturn( '' );
		Functions\when( 'sanitize_hex_color' )->justReturn( null );
		Functions\when( 'get_post_meta' )->alias( static fn( $id, $key ) => 'masthead_planned' === $key ? '1' : '' );
		Functions\when( 'get_edit_post_link' )->alias( static fn( $id ) => 'http://example.test/wp-admin/post.php?post=' . $id . '&action=edit' );
	}

	private function reader(): PlanReader {
		return new PlanReader( new Clock(), new SeriesSource() );
	}

	private function post( int $id, string $status, ?string $date, string $title = 'T' ): object {
		return (object) array(
			'ID'            => $id,
			'post_title'    => $title,
			'post_status'   => $status,
			'post_date'     => $date ?? '2026-10-01 09:00:00',
			'post_date_gmt' => null === $date ? '0000-00-00 00:00:00' : '2026-01-01 00:00:00',
			'post_modified' => '2026-10-02 10:00:00',
		);
	}

	private function posts( array $posts ): void {
		Functions\when( 'get_posts' )->alias(
			function ( $args ) use ( $posts ) {
				if ( array() === $this->query_args ) {
					$this->query_args = $args;
				}
				return $posts;
			}
		);
	}

	public function test_range_returns_one_day_per_date_with_lane(): void {
		$this->posts( array( $this->post( 1, 'future', '2026-11-10 07:30:00', 'Tuesday post' ) ) );
		$result = $this->reader()->range( '2026-11-09', '2026-11-11' );
		$this->assertSame( array( '2026-11-09', '2026-11-10', '2026-11-11' ), array_column( $result['days'], 'date' ) );
		$this->assertSame( 'business', $result['days'][0]['lane']['slug'] );
		$this->assertSame( 'technology', $result['days'][1]['lane']['slug'] );
		$this->assertSame( array( 'technology' ), $result['days'][1]['lane']['categories'] );
		$this->assertCount( 1, $result['days'][1]['posts'] );
		$this->assertSame( 'Tuesday post', $result['days'][1]['posts'][0]['title'] );
		$this->assertSame( array(), $result['days'][0]['posts'] );
	}

	public function test_undated_drafts_are_not_on_any_day(): void {
		$this->posts( array( $this->post( 1, 'draft', null ) ) );
		$result = $this->reader()->range( '2026-10-01', '2026-10-03' );
		foreach ( $result['days'] as $day ) {
			$this->assertSame( array(), $day['posts'] );
		}
	}

	public function test_open_is_false_for_past_days_and_days_with_posts(): void {
		$this->posts( array( $this->post( 1, 'future', '2026-11-10 07:30:00' ) ) );
		$days = $this->reader()->range( '2026-11-08', '2026-11-11' )['days'];
		$this->assertFalse( $days[0]['open'], 'past day' );
		$this->assertTrue( $days[1]['open'], 'today, no post' );
		$this->assertFalse( $days[2]['open'], 'has a post' );
		$this->assertTrue( $days[3]['open'], 'future, no post' );
	}

	public function test_query_is_bounded_by_config_and_sets_no_found_rows(): void {
		$this->posts( array() );
		$this->reader()->range( '2026-11-09', '2026-11-15' );
		$this->assertSame( 500, $this->query_args['posts_per_page'] );
		$this->assertTrue( $this->query_args['no_found_rows'] );
		$this->assertFalse( $this->query_args['suppress_filters'] );
		$this->assertSame( array( 'publish', 'future', 'draft' ), $this->query_args['post_status'] );
		$this->assertSame( '2026-11-15 23:59:59', $this->query_args['date_query'][0]['before'] );
		$this->assertTrue( $this->query_args['date_query'][0]['inclusive'] );
	}

	public function test_truncated_when_cap_is_reached(): void {
		Filters\expectApplied( 'masthead_config' )->zeroOrMoreTimes()->andReturnUsing(
			static function ( $flat ) {
				$flat['calendar.max_posts_per_range'] = 2;
				return $flat;
			}
		);
		$this->posts(
			array(
				$this->post( 1, 'future', '2026-11-10 07:30:00' ),
				$this->post( 2, 'future', '2026-11-11 07:30:00' ),
			)
		);
		$this->assertTrue( $this->reader()->range( '2026-11-09', '2026-11-15' )['truncated'] );
		$this->posts( array( $this->post( 1, 'future', '2026-11-10 07:30:00' ) ) );
		$this->assertFalse( $this->reader()->range( '2026-11-09', '2026-11-15' )['truncated'] );
	}

	public function test_plan_post_shape(): void {
		$shaped = $this->reader()->plan_post( $this->post( 1, 'future', '2026-11-10 07:30:00', 'Shaped' ) );
		$this->assertSame( 1, $shaped['id'] );
		$this->assertSame( 'future', $shaped['status'] );
		$this->assertSame( '2026-11-10T07:30:00-08:00', $shaped['date'] );
		$this->assertSame( array( 'faith', 'business' ), $shaped['categories'] );
		$this->assertSame( 'faith', $shaped['primary_category'] );
		$this->assertSame( '#7a5c2e', $shaped['color'] );
		$this->assertTrue( $shaped['planned'] );
		$this->assertStringContainsString( 'post=1', $shaped['edit_url'] );
		$this->assertArrayNotHasKey( 'series', $shaped );
		$this->assertArrayNotHasKey( 'modified', $shaped );

		$undated = $this->reader()->plan_post( $this->post( 2, 'draft', null ) );
		$this->assertNull( $undated['date'] );
		$this->assertSame( '2026-10-02T10:00:00-07:00', $undated['modified'] );
		$this->assertSame( array(), $undated['categories'] );
		$this->assertNull( $undated['primary_category'] );
	}
}
