<?php
/**
 * JetpackSocial tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Social;

use Brain\Monkey\Functions;
use Masthead\Social\JetpackSocial;
use Masthead\Tests\Unit\TestCase;

/**
 * JetpackSocial tests.
 */
final class JetpackSocialTest extends TestCase {

	/**
	 * Recorded dispatches.
	 *
	 * @var array<int,array{0:string,1:string,2:array}>
	 */
	private array $calls = array();

	protected function setUp(): void {
		parent::setUp();
		$this->calls = array();
		Functions\when( 'is_wp_error' )->alias( static fn( $v ) => $v instanceof \WP_Error );
	}

	private function items(): array {
		return array(
			array(
				'connection_id' => '1',
				'service_name'  => 'mastodon',
				'display_name'  => 'Account One',
			),
			array(
				'connection_id' => '2',
				'service_name'  => 'bluesky',
				'display_name'  => 'Account Two',
			),
			array(
				'connection_id' => '3',
				'service_name'  => 'linkedin',
				'display_name'  => 'Account Three',
			),
			array( 'service_name' => 'broken' ),
		);
	}

	private function social( $share_response = array( 'ok' => true ) ): JetpackSocial {
		return new JetpackSocial(
			function ( string $method, string $route, array $params ) use ( $share_response ) {
				$this->calls[] = array( $method, $route, $params );
				return 'GET' === $method ? $this->items() : $share_response;
			}
		);
	}

	public function test_connections_maps_id_service_and_display_name(): void {
		$this->assertSame(
			array(
				array(
					'id'           => 1,
					'service'      => 'mastodon',
					'display_name' => 'Account One',
				),
				array(
					'id'           => 2,
					'service'      => 'bluesky',
					'display_name' => 'Account Two',
				),
				array(
					'id'           => 3,
					'service'      => 'linkedin',
					'display_name' => 'Account Three',
				),
			),
			$this->social()->connections()
		);
		$this->assertSame( array( 'GET', '/wpcom/v2/publicize/connections', array() ), $this->calls[0] );
	}

	public function test_share_now_dispatches_message_and_connections_and_reports_shared(): void {
		$result = $this->social()->share_now( 5, 'Worth a re-read', array( 2 ) );
		$this->assertSame( 'POST', $this->calls[1][0] );
		$this->assertSame( '/wpcom/v2/publicize/share-post/5', $this->calls[1][1] );
		$this->assertSame(
			array(
				'message'             => 'Worth a re-read',
				'skipped_connections' => array( 1, 3 ),
				'async'               => false,
			),
			$this->calls[1][2]
		);
		$this->assertSame(
			array(
				'status'  => 'shared',
				'results' => array(
					array(
						'connection_id' => 2,
						'ok'            => true,
						'message'       => '',
					),
				),
			),
			$result
		);
	}

	public function test_plan_gated_error_becomes_unavailable_with_reason(): void {
		$result = $this->social( new \WP_Error( 'plan_upgrade_required', 'Upgrade your plan to share.' ) )->share_now( 5, 'Hi', array( 1 ) );
		$this->assertSame( 'unavailable', $result['status'] );
		$this->assertSame( 'Upgrade your plan to share.', $result['results'][0]['message'] );
		$this->assertFalse( $result['results'][0]['ok'] );
		$disconnected = $this->social( new \WP_Error( 'publicize_not_available', 'Not available.' ) )->share_now( 5, 'Hi', array( 1 ) );
		$this->assertSame( 'unavailable', $disconnected['status'] );
	}

	public function test_share_at_follows_the_spike_decision(): void {
		$result = $this->social()->share_at( 5, 'Later', array( 1, 3 ), '2026-11-12T09:00:00-08:00' );
		$this->assertSame( 'queued', $result['status'] );
		$this->assertCount( 2, $result['results'] );
		$posts = array_values( array_filter( $this->calls, static fn( $call ) => 'POST' === $call[0] ) );
		$this->assertCount( 2, $posts, 'one scheduled action per connection' );
		$this->assertSame( '/wpcom/v2/publicize/scheduled-actions', $posts[0][1] );
		$this->assertSame(
			array(
				'post_id'       => 5,
				'connection_id' => 1,
				'message'       => 'Later',
				'timestamp'     => ( new \DateTimeImmutable( '2026-11-12T09:00:00-08:00' ) )->getTimestamp(),
			),
			$posts[0][2]
		);
		$bad = $this->social()->share_at( 5, 'Later', array( 1 ), 'tomorrow' );
		$this->assertSame( 'error', $bad['status'] );
	}

	public function test_dispatch_error_becomes_error_status(): void {
		$result = $this->social( new \WP_Error( 'http_request_failed', 'Connection refused.' ) )->share_now( 5, 'Hi', array( 1 ) );
		$this->assertSame( 'error', $result['status'] );
		$this->assertSame( 'Connection refused.', $result['results'][0]['message'] );

		$throwing = new JetpackSocial(
			static function () {
				throw new \RuntimeException( 'boom' );
			}
		);
		$this->assertSame( array(), $throwing->connections() );
		$this->assertSame( 'unavailable', $throwing->share_now( 5, 'Hi', array( 1 ) )['status'], 'no connections means nothing to share to' );
	}

	public function test_probe_contains_no_names_or_ids(): void {
		$server = new class() {
			public function get_routes(): array {
				return array(
					'/wpcom/v2/publicize/connections'   => array(),
					'/wpcom/v2/publicize/share-post/(?P<postId>\d+)' => array(),
				);
			}
		};
		Functions\when( 'rest_get_server' )->justReturn( $server );
		$probe = $this->social()->probe();
		$this->assertSame(
			array(
				'available'        => true,
				'connection_count' => 3,
				'routes'           => array(
					'connections' => true,
					'share'       => true,
					'schedule'    => false,
				),
			),
			$probe
		);
		$encoded = (string) json_encode( $probe );
		foreach ( array( 'Account', 'mastodon', 'bluesky' ) as $leak ) {
			$this->assertStringNotContainsString( $leak, $encoded );
		}
	}
}
