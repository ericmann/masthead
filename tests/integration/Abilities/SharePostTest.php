<?php
/**
 * SharePost ability integration tests.
 *
 * @package Masthead
 */

namespace Masthead\Tests\Integration\Abilities;

use Masthead\Abilities\Registrar;
use Masthead\Abilities\SharePost;
use Masthead\Social\NullShare;
use Masthead\Social\ShareSource;
use Masthead\Support\Clock;
use Masthead\Tests\Integration\TestCase;

/**
 * SharePost ability integration tests.
 */
final class SharePostTest extends TestCase {

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
					'results' => array(
						array(
							'connection_id' => 7,
							'ok'            => true,
							'message'       => '',
						),
					),
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

	private function input( int $post_id ): array {
		return array(
			'post_id'        => $post_id,
			'message'        => 'Worth a re-read',
			'connection_ids' => array( 7 ),
		);
	}

	/**
	 * Re-registers share-post the way the registry would on wp_abilities_api_init.
	 *
	 * @return void
	 */
	private function register_again(): void {
		global $wp_current_filter;
		wp_unregister_ability( 'masthead/share-post' );
		$wp_current_filter[] = 'wp_abilities_api_init';
		( new Registrar( new SharePost( new NullShare(), new Clock() ) ) )->register();
		array_pop( $wp_current_filter );
	}

	public function test_allowed(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$ability = wp_get_ability( 'masthead/share-post' );
		$this->assertNotNull( $ability );
		$this->assertTrue( $ability->check_permissions( $this->input( 1 ) ) );
	}

	public function test_denied(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'contributor' ) ) );
		$ability = wp_get_ability( 'masthead/share-post' );
		$result  = $ability->check_permissions( $this->input( 1 ) );
		$this->assertTrue( false === $result || is_wp_error( $result ) );
	}

	public function test_happy_path(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$id     = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$fired  = array();
		add_action(
			'masthead_shared',
			static function ( $post_id, $result ) use ( &$fired ) {
				$fired[] = array( $post_id, $result['status'] );
			},
			10,
			2
		);
		$result = ( new SharePost( $this->stub(), new Clock() ) )->execute( $this->input( $id ) );
		$this->assertSame( 'shared', $result['status'] );
		$this->assertSame( '2026-11-09T14:00:00-08:00', get_post_meta( $id, 'masthead_last_shared', true ) );
		$this->assertSame( array( array( $id, 'shared' ) ), $fired );
	}

	public function test_non_published_post_is_refused(): void {
		$id     = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		$result = ( new SharePost( $this->stub(), new Clock() ) )->execute( $this->input( $id ) );
		$this->assertWPError( $result );
		$this->assertSame( 'masthead_not_published', $result->get_error_code() );
		$this->assertSame( 409, $result->get_error_data()['status'] );
		$this->assertSame( '', get_post_meta( $id, 'masthead_last_shared', true ) );
	}

	public function test_share_post_is_not_public_by_default(): void {
		$meta = wp_get_ability( 'masthead/share-post' )->get_meta();
		$this->assertFalse( $meta['public'] );
		$this->assertTrue( $meta['show_in_rest'], 'the admin UI still runs it' );
		$this->assertTrue( wp_get_ability( 'masthead/share-connections' )->get_meta()['public'] );
	}

	public function test_share_post_is_public_when_expose_share_is_true(): void {
		add_filter(
			'masthead_config',
			static function ( $flat ) {
				$flat['abilities.expose_share'] = true;
				return $flat;
			}
		);
		$this->register_again();
		$this->assertTrue( wp_get_ability( 'masthead/share-post' )->get_meta()['public'] );
	}
}
