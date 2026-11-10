<?php
/**
 * REST transport tests: the admin client runs abilities through the core route.
 *
 * @package Masthead
 */

namespace Masthead\Tests\Integration\Abilities;

use Masthead\Tests\Integration\TestCase;

/**
 * Abilities run over the core REST route, per docs/spikes/P1-09-abilities-rest.md.
 */
final class RestTransportTest extends TestCase {

	private const ROUTE = '/wp-abilities/v1/abilities/%s/run';

	private function run_ability( string $name, string $method, array $input ): \WP_REST_Response {
		$request = new \WP_REST_Request( $method, sprintf( self::ROUTE, $name ) );
		if ( 'GET' === $method ) {
			$request->set_query_params( array( 'input' => $input ) );
		} else {
			$request->set_header( 'content-type', 'application/json' );
			$request->set_body( wp_json_encode( array( 'input' => $input ) ) );
		}
		return rest_do_request( $request );
	}

	public function test_editor_runs_get_plan_over_rest(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$response = $this->run_ability(
			'masthead/get-plan',
			'GET',
			array(
				'from' => '2026-11-09',
				'to'   => '2026-11-15',
			)
		);
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertCount( 7, $data['days'] );
		$this->assertArrayHasKey( 'unscheduled', $data );
		$wrong = $this->run_ability(
			'masthead/get-plan',
			'POST',
			array(
				'from' => '2026-11-09',
				'to'   => '2026-11-15',
			)
		);
		$this->assertSame( 405, $wrong->get_status() );
	}

	public function test_subscriber_is_denied_over_rest(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$response = $this->run_ability(
			'masthead/get-plan',
			'GET',
			array(
				'from' => '2026-11-09',
				'to'   => '2026-11-15',
			)
		);
		$this->assertContains( $response->get_status(), array( 401, 403 ) );
		wp_set_current_user( 0 );
		$anonymous = $this->run_ability(
			'masthead/get-plan',
			'GET',
			array(
				'from' => '2026-11-09',
				'to'   => '2026-11-15',
			)
		);
		$this->assertSame( 401, $anonymous->get_status() );
	}

	public function test_editor_runs_create_planned_draft_over_rest(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		self::factory()->category->create(
			array(
				'slug' => 'technology',
				'name' => 'Technology',
			) 
		);
		$response = $this->run_ability(
			'masthead/create-planned-draft',
			'POST',
			array(
				'date'     => '2026-11-18',
				'time'     => '09:30',
				'title'    => 'Over REST',
				'category' => 'technology',
			)
		);
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( '2026-11-18T09:30:00-08:00', $data['date'] );
		$this->assertSame( 'draft', get_post_status( $data['post_id'] ) );
	}

	public function test_invalid_input_returns_a_client_error(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$missing = $this->run_ability( 'masthead/get-plan', 'GET', array( 'from' => '2026-11-09' ) );
		$this->assertSame( 400, $missing->get_status() );
		$reversed = $this->run_ability(
			'masthead/get-plan',
			'GET',
			array(
				'from' => '2026-11-15',
				'to'   => '2026-11-09',
			)
		);
		$this->assertSame( 400, $reversed->get_status() );
		$unknown = $this->run_ability( 'masthead/nonexistent', 'GET', array() );
		$this->assertSame( 404, $unknown->get_status() );
	}

	public function test_boot_data_run_route_matches_the_route_under_test(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		set_current_screen( 'dashboard' );
		do_action( 'admin_menu' );
		wp_dequeue_script( 'masthead' );
		wp_deregister_script( 'masthead' );
		do_action( 'admin_enqueue_scripts', get_plugin_page_hookname( 'masthead', 'edit.php' ) );
		$inline = implode( '', (array) wp_scripts()->get_data( 'masthead', 'before' ) );
		$this->assertSame( 1, preg_match( '/window\.mastheadBoot = (\{.*\});/s', $inline, $match ), 'boot data is printed' );
		$boot = json_decode( $match[1], true );
		$this->assertSame( self::ROUTE, $boot['run']['path'] );
		$this->assertSame( 'GET', $boot['run']['readonly']['method'] );
		$this->assertSame( 'POST', $boot['run']['write']['method'] );
		$this->assertTrue( $boot['abilities']['masthead/get-plan']['readonly'] );
		$this->assertFalse( $boot['abilities']['masthead/create-planned-draft']['readonly'] );
		$this->assertFalse( $boot['abilities']['masthead/reschedule']['readonly'] );
	}
}
