<?php
/**
 * SwapCheck ability integration tests.
 *
 * @package Masthead
 */

namespace Masthead\Tests\Integration\Abilities;

use Masthead\Cli\Seeder;
use Masthead\Plan\Reschedule;
use Masthead\Plan\SeriesSource;
use Masthead\Plan\Slots;
use Masthead\Support\Clock;
use Masthead\Tests\Integration\TestCase;

/**
 * SwapCheck ability integration tests.
 */
final class SwapCheckTest extends TestCase {

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
		do_action( 'masthead_nightly' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
	}

	private function ability() {
		$ability = wp_get_ability( 'masthead/swap-check' );
		$this->assertNotNull( $ability );
		return $ability;
	}

	private function keys( array $result ): array {
		return array_column( $result['findings'], 'key' );
	}

	public function test_allowed(): void {
		$this->assertTrue( $this->ability()->check_permissions( array() ) );
	}

	public function test_denied(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$result = $this->ability()->check_permissions( array() );
		$this->assertTrue( false === $result || is_wp_error( $result ) );
		$this->assertWPError( $this->ability()->execute( array() ) );
	}

	public function test_happy_path(): void {
		$result = $this->ability()->execute( array() );
		$this->assertSame( '2026-11-09T14:00:00-08:00', $result['generated_at'] );
		$this->assertSame(
			array( 'lane_mismatch:12156', 'cadence_break:12156', 'lane_mismatch:12157', 'lane_mismatch:12158' ),
			$this->keys( $result )
		);
		$response = rest_do_request( new \WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/masthead/swap-check/run' ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 4, $response->get_data()['findings'] );
	}

	public function test_range_filter(): void {
		$result = $this->ability()->execute(
			array(
				'from' => '2026-12-01',
				'to'   => '2027-01-31',
			)
		);
		$this->assertSame( array( 'lane_mismatch:12156', 'cadence_break:12156', 'lane_mismatch:12157' ), $this->keys( $result ) );
		$none = $this->ability()->execute(
			array(
				'from' => '2026-11-10',
				'to'   => '2026-11-30',
			)
		);
		$this->assertSame( array(), $none['findings'] );
	}

	public function test_finding_for_a_moved_post_is_dropped_as_stale(): void {
		$moved = ( new Reschedule( new Clock() ) )->move( 12157, '2027-01-09', null );
		$this->assertNotWPError( $moved );
		$this->assertNotContains( 'lane_mismatch:12157', $this->keys( $this->ability()->execute( array() ) ) );
		$this->assertContains( 'lane_mismatch:12158', $this->keys( $this->ability()->execute( array() ) ) );
	}

	public function test_lane_mismatch_is_dropped_once_the_series_has_the_exception(): void {
		$term_id = $this->series->for_post( 12156 )['term_id'];
		update_term_meta( $term_id, 'masthead_lane_exception', true );
		$this->assertSame( array( 'cadence_break:12156' ), $this->keys( $this->ability()->execute( array() ) ) );
	}

	public function test_no_stored_findings_returns_an_empty_list(): void {
		delete_option( 'masthead_findings' );
		$this->assertSame(
			array(
				'generated_at' => null,
				'findings'     => array(),
			),
			$this->ability()->execute( array() )
		);
	}
}
