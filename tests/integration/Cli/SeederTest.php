<?php
/**
 * Seeder integration tests.
 *
 * @package Masthead
 */

namespace Masthead\Tests\Integration\Cli;

use Masthead\Cli\Seeder;
use Masthead\Plan\Rhythm;
use Masthead\Plan\SeriesSource;
use Masthead\Plan\Slots;
use Masthead\Tests\Integration\TestCase;

/**
 * Seeder integration tests.
 */
final class SeederTest extends TestCase {

	/**
	 * Series adapter.
	 *
	 * @var SeriesSource
	 */
	private SeriesSource $series;

	/**
	 * Seeder under test.
	 *
	 * @var Seeder
	 */
	private Seeder $seeder;

	/**
	 * Decoded fixture.
	 *
	 * @var array
	 */
	private array $fixture;

	public function set_up(): void {
		parent::set_up();
		$this->series = new SeriesSource();
		$this->series->register();
		$this->seeder  = new Seeder( new Slots( $this->series ), $this->series );
		$this->fixture = json_decode( (string) file_get_contents( dirname( __DIR__, 3 ) . '/docs/fixtures/queue-2026-11-09.json' ), true );
	}

	private function seed(): int {
		$this->seeder->reset();
		return $this->seeder->seed( $this->fixture, '2026-11-09 14:00:00' );
	}

	public function test_seeds_ten_posts_with_fixture_ids_and_statuses(): void {
		$this->assertSame( 10, $this->seed() );
		foreach ( $this->fixture['posts'] as $post ) {
			$this->assertSame( $post['status'], get_post_status( $post['id'] ), (string) $post['id'] );
			$this->assertSame( $post['title'], get_post( $post['id'] )->post_title );
		}
		$this->assertSame( '2026-11-11 08:00:00', get_post( 12177 )->post_date );
		$this->assertSame( '2026-11-11 16:00:00', get_post( 12177 )->post_date_gmt );
		$this->assertSame( array( 'technology' ), wp_list_pluck( get_the_category( 12177 ), 'slug' ) );
		$this->assertSame( 'America/Los_Angeles', get_option( 'timezone_string' ) );
		$this->assertSame( '2026-11-09 14:00:00', get_option( 'masthead_seed_now' ) );
	}

	public function test_future_posts_stay_future_and_have_no_publish_event(): void {
		$this->seed();
		foreach ( array( 12177, 10194, 12156, 12157, 12158 ) as $id ) {
			$this->assertSame( 'future', get_post_status( $id ), (string) $id );
			$this->assertFalse( wp_next_scheduled( 'publish_future_post', array( $id ) ), (string) $id );
		}
	}

	public function test_draft_10178_is_undated_with_modified_2026_10_29(): void {
		$this->seed();
		$post = get_post( 10178 );
		$this->assertSame( 'draft', $post->post_status );
		$this->assertFalse( Slots::is_dated( $post ) );
		$this->assertSame( '2026-10-29 21:46:13', $post->post_modified );
		$this->assertEqualsCanonicalizing( array( 'AI', 'developer tools' ), wp_list_pluck( wp_get_post_tags( 10178 ), 'name' ) );
	}

	public function test_mantle_series_has_four_parts_and_cadence(): void {
		$this->seed();
		$found = $this->series->for_post( 12156 );
		$this->assertSame( 'The Mantle', $found['name'] );
		$this->assertSame( 2, $found['part'] );
		$this->assertSame( 4, $found['total'] );
		$parts = $this->series->parts( $found['term_id'] );
		$this->assertSame( array( 12155, 12156, 12157, 12158 ), wp_list_pluck( $parts, 'post_id' ) );
		$this->assertSame(
			array(
				'every_weeks' => 4,
				'weekday'     => null,
				'time'        => '06:23',
			),
			$this->series->cadence( $found['term_id'] )
		);
	}

	public function test_rhythm_defaults_are_installed_for_seeded_categories(): void {
		$this->seed();
		$rhythm = Rhythm::get();
		$this->assertSame( array( 'technology' ), $rhythm[2]['categories'] );
		$this->assertSame( array( 'technology', 'security' ), $rhythm[4]['categories'] );
		$this->assertSame( array( 'writing' ), $rhythm[6]['categories'] );
		$this->assertSame( array(), $rhythm[3]['categories'] );
	}

	public function test_reset_removes_previous_posts_and_options(): void {
		$stray = self::factory()->post->create( array( 'post_title' => 'Stray' ) );
		update_option( 'masthead_settings', array( 'desk' => array( 'horizon_days' => 30 ) ) );
		update_option( 'masthead_findings', array( 'items' => array() ) );
		$user = self::factory()->user->create();
		update_user_meta( $user, 'masthead_dismissed', array( 'x' ) );

		$this->seeder->reset();
		$this->assertNull( get_post( $stray ) );
		$this->assertFalse( get_option( 'masthead_settings' ) );
		$this->assertFalse( get_option( 'masthead_findings' ) );
		$this->assertSame( '', get_user_meta( $user, 'masthead_dismissed', true ) );
		$this->assertSame( array(), $this->series->terms() );
	}

	public function test_seeding_twice_with_reset_is_stable(): void {
		$this->seed();
		$first = wp_list_pluck(
			get_posts(
				array(
					'post_status' => 'any',
					'posts_per_page' => 50,
					'no_found_rows' => true,
					'orderby' => 'ID',
					'order' => 'ASC',
				) 
			),
			'post_title',
			'ID' 
		);
		$this->seed();
		$second = wp_list_pluck(
			get_posts(
				array(
					'post_status' => 'any',
					'posts_per_page' => 50,
					'no_found_rows' => true,
					'orderby' => 'ID',
					'order' => 'ASC',
				) 
			),
			'post_title',
			'ID' 
		);
		$this->assertSame( $first, $second );
		$this->assertCount( 10, $second );
		$this->assertCount( 1, $this->series->terms() );
	}
}
