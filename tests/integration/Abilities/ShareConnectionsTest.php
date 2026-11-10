<?php
/**
 * ShareConnections ability integration tests.
 *
 * @package Masthead
 */

namespace Masthead\Tests\Integration\Abilities;

use Masthead\Abilities\ShareConnections;
use Masthead\Social\NullShare;
use Masthead\Social\ShareSource;
use Masthead\Tests\Integration\TestCase;

/**
 * ShareConnections ability integration tests.
 */
final class ShareConnectionsTest extends TestCase {

	private function stub(): ShareSource {
		return new class() implements ShareSource {
			public function available(): bool {
				return true;
			}

			public function connections(): array {
				return array(
					array(
						'id'           => 7,
						'service'      => 'mastodon',
						'display_name' => 'Me',
					),
				);
			}

			public function share_now( int $post_id, string $message, array $connection_ids ): array {
				return array(
					'status'  => 'shared',
					'results' => array(),
				);
			}

			public function share_at( int $post_id, string $message, array $connection_ids, string $iso_datetime ): array {
				return array(
					'status'  => 'queued',
					'results' => array(),
				);
			}
		};
	}

	public function test_allowed(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$ability = wp_get_ability( 'masthead/share-connections' );
		$this->assertNotNull( $ability );
		$this->assertTrue( $ability->check_permissions( array() ) );
	}

	public function test_denied(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'contributor' ) ) );
		$ability = wp_get_ability( 'masthead/share-connections' );
		$result  = $ability->check_permissions( array() );
		$this->assertTrue( false === $result || is_wp_error( $result ) );
		$this->assertWPError( $ability->execute( array() ) );
	}

	public function test_happy_path(): void {
		$result = ( new ShareConnections( $this->stub() ) )->execute( array() );
		$this->assertTrue( $result['available'] );
		$this->assertSame(
			array(
				array(
					'id'           => 7,
					'service'      => 'mastodon',
					'display_name' => 'Me',
				),
			),
			$result['connections']
		);
		$this->assertArrayNotHasKey( 'reason', $result );
	}

	public function test_unconnected_site_reports_unavailable_with_reason(): void {
		$result = ( new ShareConnections( new NullShare() ) )->execute( array() );
		$this->assertFalse( $result['available'] );
		$this->assertSame( array(), $result['connections'] );
		$this->assertNotSame( '', $result['reason'] );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$response = rest_do_request( new \WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/masthead/share-connections/run' ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( $response->get_data()['available'] );
	}
}
