<?php
/**
 * Reasons tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Desk;

use Brain\Monkey\Functions;
use Masthead\Desk\Reasons;
use Masthead\Support\Clock;
use Masthead\Tests\Unit\TestCase;

/**
 * Reasons tests.
 */
final class ReasonsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wp_date' )->alias(
			static fn( $format, $timestamp, $zone ) => ( new \DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $zone )->format( $format )
		);
	}

	private function reasons(): Reasons {
		return new Reasons( new Clock() );
	}

	public function test_draft_reason_for_fixture_draft_on_2026_11_18_is_the_spec_sentence(): void {
		$why = $this->reasons()->for_candidate(
			array(
				'kind' => 'draft',
				'data' => array( 'modified' => '2026-10-29T21:46:13-07:00' ),
			),
			'2026-11-18',
			array( 'label' => 'Standalone' )
		);
		$this->assertSame( "Wednesday is your Standalone slot. This draft isn't on the calendar; last edited Oct 29.", $why );
	}

	public function test_gap_reason_never_published(): void {
		$why = $this->reasons()->for_candidate(
			array(
				'kind' => 'gap',
				'data' => array(
					'last_published' => null,
					'category_name'  => 'Security',
				),
			),
			'2026-11-18',
			array()
		);
		$this->assertSame( 'Nothing has been published under Security yet.', $why );
	}

	public function test_gap_reason_with_last_published_date(): void {
		$why = $this->reasons()->for_candidate(
			array(
				'kind' => 'gap',
				'data' => array(
					'last_published' => '2026-09-01',
					'category_name'  => 'Faith',
				),
			),
			'2026-11-18',
			array()
		);
		$this->assertSame( 'Nothing has been published under Faith since Sep 1.', $why );
	}

	public function test_series_reason_names_cadence_and_part(): void {
		$why = $this->reasons()->for_candidate(
			array(
				'kind' => 'series',
				'data' => array(
					'expected'    => '2026-12-07',
					'every_weeks' => 4,
					'part'        => 2,
				),
			),
			'2026-12-07',
			array( 'label' => 'Business' )
		);
		$this->assertSame( "Monday is due around Dec 7 (every 4 weeks). Part 2 isn't on the calendar.", $why );
	}

	public function test_reshare_reason_contains_no_digit_other_than_the_date(): void {
		$why = $this->reasons()->for_candidate(
			array(
				'kind' => 'reshare',
				'data' => array( 'published' => '2026-02-01' ),
			),
			'2026-11-18',
			array( 'label' => 'Standalone' )
		);
		$this->assertSame( 'Wednesday is your Standalone slot and nothing new is queued. Published Feb 1, 2026; it ranks among your most viewed posts in Jetpack Stats.', $why );
		$this->assertSame( 0, preg_match( '/\d/', preg_replace( '/Feb 1, 2026/', '', $why ) ) );
		$this->assertSame( '', $this->reasons()->for_candidate( array( 'kind' => 'idea' ), '2026-11-18', array() ) );
	}

	public function test_lane_mismatch_reason(): void {
		$why = $this->reasons()->for_lane_mismatch( 'The Mantle - Part 2 of 4', '2026-12-14', array( 'label' => 'Business & leadership' ) );
		$this->assertSame( 'The Mantle - Part 2 of 4 is on a Monday, which your rhythm gives to Business & leadership.', $why );
	}

	public function test_cadence_break_reason(): void {
		$why = $this->reasons()->for_cadence_break( 2, '2026-12-14', 4, '2026-12-07' );
		$this->assertSame( 'Part 2 is on Dec 14; the series cadence (every 4 weeks) expects Dec 7.', $why );
	}

	public function test_collision_reason(): void {
		$this->assertSame( 'First shares Nov 18 with Second, Third.', $this->reasons()->for_collision( 'First', '2026-11-18', array( 'Second', 'Third' ) ) );
	}

	public function test_better_candidate_reason_contains_no_digit(): void {
		$why = $this->reasons()->for_better_candidate( 'Technology draft', 'Tuesday', 'Technical series', 'Business post' );
		$this->assertSame( "Technology draft fits Tuesday's Technical series lane better than Business post.", $why );
		$this->assertSame( 0, preg_match( '/\d/', $why ) );
	}
}
