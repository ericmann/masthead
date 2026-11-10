<?php
/**
 * Nightly integration tests.
 *
 * @package Masthead
 */

namespace Masthead\Tests\Integration\Cron;

use Masthead\Cli\Seeder;
use Masthead\Plan\SeriesSource;
use Masthead\Plan\Slots;
use Masthead\Tests\Integration\TestCase;

/**
 * Nightly integration tests.
 */
final class NightlyTest extends TestCase {

	public function set_up(): void {
		parent::set_up();
		$series = new SeriesSource();
		$series->register();
		$seeder = new Seeder( new Slots( $series ), $series );
		$seeder->reset();
		$seeder->seed( json_decode( (string) file_get_contents( dirname( __DIR__, 3 ) . '/docs/fixtures/queue-2026-11-09.json' ), true ), '2026-11-09 14:00:00' );
	}

	public function test_running_the_hook_stores_four_findings_with_generated_at(): void {
		delete_option( 'masthead_findings' );
		$fired = array();
		add_action(
			'masthead_findings_updated',
			static function ( $count ) use ( &$fired ) {
				$fired[] = $count;
			}
		);
		do_action( 'masthead_nightly' );
		$stored = get_option( 'masthead_findings' );
		$this->assertSame( '2026-11-09T14:00:00-08:00', $stored['generated_at'] );
		$this->assertSame(
			array( 'lane_mismatch:12156', 'cadence_break:12156', 'lane_mismatch:12157', 'lane_mismatch:12158' ),
			array_column( $stored['items'], 'key' )
		);
		$this->assertSame( array( 4 ), $fired );
		global $wpdb;
		$autoload = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", 'masthead_findings' ) );
		$this->assertContains( $autoload, array( 'no', 'off' ) );
	}

	public function test_running_the_hook_changes_no_post(): void {
		$snapshot = static function () {
			$posts = get_posts(
				array(
					'post_type'     => 'post',
					'post_status'   => 'any',
					'numberposts'   => 50,
					'no_found_rows' => true,
					'orderby'       => 'ID',
					'order'         => 'ASC',
				)
			);
			return array_map(
				static fn( $p ) => array( $p->ID, $p->post_status, $p->post_date, $p->post_date_gmt, $p->post_modified, $p->post_title ),
				$posts
			);
		};
		$before = $snapshot();
		do_action( 'masthead_nightly' );
		$this->assertCount( 10, $before );
		$this->assertSame( $before, $snapshot() );
		$this->assertNotFalse( wp_next_scheduled( 'masthead_nightly' ), 'the nightly event is scheduled on init' );
	}
}
