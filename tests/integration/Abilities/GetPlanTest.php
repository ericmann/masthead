<?php
/**
 * GetPlan ability integration tests.
 *
 * @package Masthead
 */

namespace Masthead\Tests\Integration\Abilities;

use Masthead\Plan\SeriesSource;
use Masthead\Plan\Slots;
use Masthead\Tests\Integration\TestCase;

/**
 * GetPlan ability integration tests.
 */
final class GetPlanTest extends TestCase {

	private function ability() {
		$ability = wp_get_ability( 'masthead/get-plan' );
		$this->assertNotNull( $ability, 'masthead/get-plan is registered' );
		return $ability;
	}

	public function test_allowed(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$input = array(
			'from' => '2026-11-09',
			'to'   => '2026-11-15',
		);
		$this->assertTrue( $this->ability()->check_permissions( $input ) );
	}

	public function test_denied(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$input = array(
			'from' => '2026-11-09',
			'to'   => '2026-11-15',
		);
		$result = $this->ability()->check_permissions( $input );
		$this->assertTrue( false === $result || is_wp_error( $result ) );
		$this->assertWPError( $this->ability()->execute( $input ) );
	}

	public function test_happy_path(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$slots = new Slots( new SeriesSource() );
		$id    = $slots->insert(
			array(
				'post_title'    => 'Planned',
				'post_date'     => '2026-11-10 07:30:00',
				'post_date_gmt' => '2026-11-10 15:30:00',
				'edit_date'     => true,
			),
			'future'
		);
		$result = $this->ability()->execute(
			array(
				'from' => '2026-11-09',
				'to'   => '2026-11-15',
			)
		);
		$this->assertNotWPError( $result );
		$this->assertCount( 7, $result['days'] );
		$this->assertSame( $id, $result['days'][1]['posts'][0]['id'] );
		$this->assertFalse( $result['truncated'] );
		$this->assertSame( 'technology', $result['days'][1]['lane']['slug'] );
	}

	public function test_range_longer_than_max_range_days_is_refused(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$ok = $this->ability()->execute(
			array(
				'from' => '2026-11-01',
				'to'   => '2027-01-31',
			)
		);
		$this->assertNotWPError( $ok, '92 inclusive days is allowed' );
		$result = $this->ability()->execute(
			array(
				'from' => '2026-11-01',
				'to'   => '2027-02-01',
			)
		);
		$this->assertWPError( $result );
		$this->assertSame( 'masthead_range_too_long', $result->get_error_code() );
		$this->assertSame( 400, $result->get_error_data()['status'] );
	}

	public function test_reversed_range_is_refused(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$result = $this->ability()->execute(
			array(
				'from' => '2026-11-15',
				'to'   => '2026-11-09',
			)
		);
		$this->assertWPError( $result );
		$this->assertSame( 'masthead_invalid_date', $result->get_error_code() );
	}

	public function test_ability_is_in_the_masthead_category_with_annotations(): void {
		$ability = $this->ability();
		$this->assertSame( 'masthead', $ability->get_category() );
		$meta = $ability->get_meta();
		$this->assertTrue( $meta['annotations']['readonly'] );
		$this->assertTrue( $meta['annotations']['idempotent'] );
		$this->assertTrue( $meta['show_in_rest'] );
		$this->assertTrue( $meta['public'] );
		$this->assertTrue( wp_has_ability_category( 'masthead' ) );
	}
}
