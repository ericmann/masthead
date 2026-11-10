<?php
/**
 * SwapCheck acceptance on the fixture (SPEC 6.3).
 *
 * @package Masthead
 */

namespace Masthead\Tests\Integration\Desk;

use Masthead\Cli\Seeder;
use Masthead\Desk\Reasons;
use Masthead\Desk\Scoring;
use Masthead\Desk\SwapCheck;
use Masthead\Plan\PlanReader;
use Masthead\Plan\SeriesSource;
use Masthead\Plan\Slots;
use Masthead\Stats\NullStats;
use Masthead\Support\Clock;
use Masthead\Tests\Integration\TestCase;

/**
 * The Mantle scenario, stats unavailable, pinned clock 2026-11-09 14:00.
 */
final class SwapCheckMantleTest extends TestCase {

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
		$seeder = new Seeder( new Slots( $this->series ), $this->series );
		$seeder->reset();
		$seeder->seed( json_decode( (string) file_get_contents( dirname( __DIR__, 3 ) . '/docs/fixtures/queue-2026-11-09.json' ), true ), '2026-11-09 14:00:00' );
	}

	private function findings(): array {
		$clock = new Clock();
		return ( new SwapCheck(
			new PlanReader( $clock, $this->series ),
			$this->series,
			new Scoring(),
			new Reasons( $clock ),
			new NullStats(),
			$clock
		) )->run();
	}

	private function keys( array $findings ): array {
		return array_column( $findings, 'key' );
	}

	public function test_parts_two_three_and_four_each_produce_lane_mismatch(): void {
		$keys = $this->keys( $this->findings() );
		foreach ( array( 12156, 12157, 12158 ) as $id ) {
			$this->assertContains( 'lane_mismatch:' . $id, $keys );
		}
		$mismatch = array_values( array_filter( $this->findings(), static fn( $f ) => 'lane_mismatch:12156' === $f['key'] ) )[0];
		$this->assertSame( 'The Mantle – Part 2 of 4 is on a Monday, which your rhythm gives to Business & leadership.', $mismatch['why'] );
		$this->assertSame( '2026-12-14', $mismatch['date'] );
		$this->assertSame( 'review', $mismatch['severity'] );
	}

	public function test_series_option_label_is_move_to_saturdays_with_the_three_dates(): void {
		$finding = array_values( array_filter( $this->findings(), static fn( $f ) => 'lane_mismatch:12157' === $f['key'] ) )[0];
		$this->assertSame( 'Move to Saturdays: 2026-12-12, 2027-01-09, 2027-02-06', $finding['options'][0]['label'] );
		$this->assertSame( 'masthead/reschedule', $finding['options'][0]['ability'] );
		$this->assertSame(
			array(
				'post_id' => 12157,
				'date'    => '2027-01-09',
			),
			$finding['options'][0]['input']
		);
		$this->assertCount( 2, $finding['options'][0]['also'] );
		$this->assertNull( $finding['options'][1]['ability'] );
		$this->assertStringContainsString( '/wp/v2/masthead_series/', $finding['options'][1]['input']['rest']['path'] );
	}

	public function test_part_two_also_produces_cadence_break_expecting_2026_12_07(): void {
		$finding = array_values( array_filter( $this->findings(), static fn( $f ) => 'cadence_break:12156' === $f['key'] ) )[0];
		$this->assertSame( 'Move to 2026-12-07', $finding['options'][0]['label'] );
		$this->assertSame( 'Part 2 is on Dec 14; the series cadence (every 4 weeks) expects Dec 7.', $finding['why'] );
		$this->assertNotContains( 'cadence_break:12157', $this->keys( $this->findings() ) );
		$this->assertNotContains( 'cadence_break:12158', $this->keys( $this->findings() ) );
	}

	public function test_lane_exception_clears_lane_mismatches_and_leaves_cadence_break(): void {
		$term_id = $this->series->for_post( 12156 )['term_id'];
		update_term_meta( $term_id, 'masthead_lane_exception', true );
		$this->assertSame( array( 'cadence_break:12156' ), $this->keys( $this->findings() ) );
	}

	public function test_posts_12177_and_10194_produce_no_findings(): void {
		$ids = array_column( $this->findings(), 'post_id' );
		$this->assertNotContains( 12177, $ids );
		$this->assertNotContains( 10194, $ids );
		$this->assertSame( array( 12156, 12156, 12157, 12158 ), $ids, 'ordered by date, post and type' );
	}

	public function test_fixture_produces_exactly_four_findings(): void {
		$findings = $this->findings();
		$this->assertSame(
			array( 'lane_mismatch:12156', 'cadence_break:12156', 'lane_mismatch:12157', 'lane_mismatch:12158' ),
			$this->keys( $findings )
		);
		$this->assertCount( 3, array_filter( $findings, static fn( $f ) => 'lane_mismatch' === $f['type'] ) );
		$this->assertCount( 1, array_filter( $findings, static fn( $f ) => 'cadence_break' === $f['type'] ) );
		$this->assertNotContains( 12177, array_column( $findings, 'post_id' ) );
		$this->assertNotContains( 10194, array_column( $findings, 'post_id' ) );
	}
}
