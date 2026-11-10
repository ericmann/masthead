<?php
/**
 * StatsSummary ability integration tests.
 *
 * @package Masthead
 */

namespace Masthead\Tests\Integration\Abilities;

use Masthead\Abilities\StatsSummary;
use Masthead\Stats\FixtureStats;
use Masthead\Stats\NullStats;
use Masthead\Tests\Integration\TestCase;

/**
 * StatsSummary ability integration tests.
 */
final class StatsSummaryTest extends TestCase {

	private function fixture_stats(): FixtureStats {
		$primary = static fn( int $id ) => array(
			12155 => 'writing',
			10193 => 'technology',
			10192 => 'technology',
			10191 => 'technology',
		)[ $id ] ?? null;
		return new FixtureStats( dirname( __DIR__, 3 ) . '/docs/fixtures/stats-sample.json', $primary );
	}

	public function test_allowed(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'contributor' ) ) );
		$ability = wp_get_ability( 'masthead/stats-summary' );
		$this->assertNotNull( $ability );
		$this->assertTrue( $ability->check_permissions( array() ) );
		$this->assertTrue( $ability->get_meta()['annotations']['readonly'] );
	}

	public function test_denied(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$ability = wp_get_ability( 'masthead/stats-summary' );
		$result  = $ability->check_permissions( array() );
		$this->assertTrue( false === $result || is_wp_error( $result ) );
		$this->assertWPError( $ability->execute( array() ) );
	}

	public function test_happy_path(): void {
		$result = ( new StatsSummary( $this->fixture_stats() ) )->execute( array( 'days' => 14 ) );
		$this->assertTrue( $result['available'] );
		$this->assertSame( 'Jetpack Stats', $result['source'] );
		$this->assertSame( '2026-11-09T13:00:00-08:00', $result['fetched_at'] );
		$this->assertSame( 14, $result['window_days'] );
		$this->assertSame(
			array(
				array(
					'slug'  => 'technology',
					'views' => 325,
				),
				array(
					'slug'  => 'writing',
					'views' => 210,
				),
			),
			$result['by_category']
		);
		$this->assertSame( 0, $result['by_weekday'][0]['weekday'] );
		$this->assertSame( 12155, $result['top_posts'][0]['post_id'] );
		$this->assertSame( 210, $result['top_posts'][0]['views'] );
		$default = ( new StatsSummary( $this->fixture_stats() ) )->execute( array() );
		$this->assertSame( 30, $default['window_days'] );
	}

	public function test_unavailable_source_returns_available_false_with_reason(): void {
		$result = ( new StatsSummary( new NullStats() ) )->execute( array() );
		$this->assertFalse( $result['available'] );
		$this->assertNotSame( '', $result['reason'] );
		$this->assertSame( array( 'available', 'reason' ), array_keys( $result ) );
	}

	public function test_top_posts_respects_the_config_limit(): void {
		add_filter(
			'masthead_config',
			static function ( $flat ) {
				$flat['stats.top_posts_limit'] = 2;
				return $flat;
			}
		);
		$result = ( new StatsSummary( $this->fixture_stats() ) )->execute( array() );
		$this->assertCount( 2, $result['top_posts'] );
	}

	public function test_runs_over_rest_without_an_input_parameter(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$response = rest_do_request( new \WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/masthead/stats-summary/run' ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( $response->get_data()['available'] );
	}
}
