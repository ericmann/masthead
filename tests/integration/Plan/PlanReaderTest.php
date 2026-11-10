<?php
/**
 * PlanReader integration tests.
 *
 * @package Masthead
 */

namespace Masthead\Tests\Integration\Plan;

use Masthead\Plan\PlanReader;
use Masthead\Plan\SeriesSource;
use Masthead\Plan\Slots;
use Masthead\Support\Clock;
use Masthead\Tests\Integration\TestCase;

/**
 * PlanReader integration tests.
 */
final class PlanReaderTest extends TestCase {

	/**
	 * Slots.
	 *
	 * @var Slots
	 */
	private Slots $slots;

	/**
	 * Reader under test.
	 *
	 * @var PlanReader
	 */
	private PlanReader $reader;

	public function set_up(): void {
		parent::set_up();
		$series       = new SeriesSource();
		$this->slots  = new Slots( $series );
		$this->reader = new PlanReader( new Clock(), $series );
	}

	private function post( string $status, ?string $local, string $title ): int {
		$arr = array( 'post_title' => $title );
		if ( null !== $local ) {
			$arr['post_date']     = $local;
			$arr['post_date_gmt'] = get_gmt_from_date( $local );
			$arr['edit_date']     = true;
		} else {
			$arr['post_date_gmt'] = '0000-00-00 00:00:00';
		}
		return $this->slots->insert( $arr, $status );
	}

	public function test_range_includes_publish_future_and_dated_draft(): void {
		$published = $this->post( 'publish', '2026-11-02 08:00:00', 'Published' );
		$future    = $this->post( 'future', '2026-11-10 07:30:00', 'Future' );
		$draft     = $this->post( 'draft', '2026-11-12 07:30:00', 'Dated draft' );
		$this->post( 'draft', null, 'Undated' );
		$this->post( 'publish', '2026-10-01 08:00:00', 'Outside' );

		$result = $this->reader->range( '2026-11-01', '2026-11-30' );
		$this->assertCount( 30, $result['days'] );
		$ids = array();
		foreach ( $result['days'] as $day ) {
			foreach ( $day['posts'] as $plan_post ) {
				$ids[] = $plan_post['id'];
			}
		}
		$this->assertSame( array( $published, $future, $draft ), $ids );
		$this->assertFalse( $result['truncated'] );
	}

	public function test_unscheduled_lists_undated_drafts_newest_edit_first(): void {
		$old = $this->post( 'draft', null, 'Old' );
		$new = $this->post( 'draft', null, 'New' );
		$this->post( 'draft', '2026-11-12 07:30:00', 'Dated' );
		global $wpdb;
		$wpdb->update( $wpdb->posts, array( 'post_modified' => '2026-10-01 10:00:00' ), array( 'ID' => $old ) );
		$wpdb->update( $wpdb->posts, array( 'post_modified' => '2026-10-05 10:00:00' ), array( 'ID' => $new ) );
		clean_post_cache( $old );
		clean_post_cache( $new );

		$unscheduled = $this->reader->unscheduled();
		$this->assertSame( array( $new, $old ), array_column( $unscheduled, 'id' ) );
		$this->assertNull( $unscheduled[0]['date'] );
		$this->assertSame( '2026-10-05T10:00:00-07:00', $unscheduled[0]['modified'] );
	}

	public function test_posts_on_the_last_day_of_the_range_are_included(): void {
		$late = $this->post( 'future', '2026-11-15 23:59:00', 'Last minute' );
		$next = $this->post( 'future', '2026-11-16 00:00:00', 'Next day' );
		$ids  = array_column( $this->reader->scheduled_between( '2026-11-09', '2026-11-15' ), 'id' );
		$this->assertSame( array( $late ), $ids );
		$this->assertNotContains( $next, $ids );
		$this->assertSame( $late, $this->reader->post( $late )['id'] );
		$this->assertNull( $this->reader->post( 999999 ) );
	}

	public function test_category_without_stored_colour_still_gets_one(): void {
		$term = self::factory()->category->create( array( 'slug' => 'technology' ) );
		$id   = $this->post( 'publish', '2026-11-02 08:00:00', 'Coloured' );
		wp_set_post_categories( $id, array( $term ) );
		$this->assertSame( '', get_term_meta( $term, 'masthead_color', true ) );
		$this->assertSame( '#2f5fd0', $this->reader->post( $id )['color'] );
	}
}
