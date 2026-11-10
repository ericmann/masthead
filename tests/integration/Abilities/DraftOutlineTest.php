<?php
/**
 * DraftOutline ability integration tests.
 *
 * @package Masthead
 */

namespace Masthead\Tests\Integration\Abilities;

use Masthead\Tests\Integration\TestCase;

/**
 * DraftOutline ability integration tests.
 */
final class DraftOutlineTest extends TestCase {

	public function set_up(): void {
		parent::set_up();
		delete_option( 'masthead_ai_usage' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	private function ability() {
		$ability = wp_get_ability( 'masthead/draft-outline' );
		$this->assertNotNull( $ability );
		return $ability;
	}

	public function test_allowed(): void {
		$this->assertTrue( $this->ability()->check_permissions( array( 'title' => 'A title' ) ) );
		$this->assertTrue( $this->ability()->get_meta()['annotations']['readonly'] );
	}

	public function test_denied(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$result = $this->ability()->check_permissions( array( 'title' => 'A title' ) );
		$this->assertTrue( false === $result || is_wp_error( $result ) );
		$this->assertWPError( $this->ability()->execute( array( 'title' => 'A title' ) ) );
	}

	public function test_happy_path(): void {
		$result = $this->ability()->execute(
			array(
				'title'    => 'A title',
				'category' => 'security',
				'date'     => '2026-11-18',
			)
		);
		$this->assertNotWPError( $result );
		$this->assertCount( 4, $result['outline'] );
		$this->assertSame( 'Why the problem is worth solving now', $result['outline'][0] );
		$this->assertSame( 299, ( new \Masthead\Desk\Budget( new \Masthead\Support\Clock() ) )->remaining() );
	}

	public function test_missing_title_is_refused(): void {
		$result = $this->ability()->execute( array( 'title' => '  ' ) );
		$this->assertWPError( $result );
		$this->assertSame( 'masthead_invalid_title', $result->get_error_code() );
	}

	public function test_budget_exhaustion_returns_the_budget_error(): void {
		$filter = static function ( $flat ) {
			$flat['ai.monthly_request_cap'] = 0;
			return $flat;
		};
		add_filter( 'masthead_config', $filter );
		$result = $this->ability()->execute( array( 'title' => 'A title' ) );
		remove_filter( 'masthead_config', $filter );
		$this->assertWPError( $result );
		$this->assertSame( 'masthead_ai_budget', $result->get_error_code() );
		$this->assertSame( 429, $result->get_error_data()['status'] );
	}
}
