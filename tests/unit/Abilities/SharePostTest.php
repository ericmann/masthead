<?php
/**
 * SharePost tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Abilities;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Masthead\Abilities\SharePost;
use Masthead\Social\ShareSource;
use Masthead\Support\Clock;
use Masthead\Tests\Unit\TestCase;

/**
 * SharePost tests.
 */
final class SharePostTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'is_wp_error' )->alias( static fn( $v ) => $v instanceof \WP_Error );
		Functions\when( 'sanitize_textarea_field' )->alias( static fn( $v ) => trim( (string) $v ) );
		Functions\when( 'get_post' )->justReturn( (object) array( 'post_status' => 'publish' ) );
		Filters\expectApplied( 'masthead_now' )->zeroOrMoreTimes()->andReturn( '2026-11-09 14:00:00' );
	}

	private function source( string $status ): object {
		return new class( $status ) implements ShareSource {
			public array $calls = array();

			public function __construct( private string $status ) {}

			public function available(): bool {
				return true;
			}

			public function connections(): array {
				return array(
					array(
						'id'           => 1,
						'service'      => 'mastodon',
						'display_name' => 'One',
					),
					array(
						'id'           => 2,
						'service'      => 'bluesky',
						'display_name' => 'Two',
					),
				);
			}

			public function share_now( int $post_id, string $message, array $connection_ids ): array {
				$this->calls[] = 'now';
				return $this->result();
			}

			public function share_at( int $post_id, string $message, array $connection_ids, string $iso_datetime ): array {
				$this->calls[] = 'at';
				return $this->result();
			}

			private function result(): array {
				return array(
					'status'  => $this->status,
					'results' => array(
						array(
							'connection_id' => 1,
							'ok'            => 'shared' === $this->status || 'queued' === $this->status,
							'message'       => 'shared' === $this->status || 'queued' === $this->status ? '' : 'Because reasons.',
						),
					),
				);
			}
		};
	}

	private function input( array $extra = array() ): array {
		return array_merge(
			array(
				'post_id'        => 12177,
				'message'        => 'Worth a read',
				'connection_ids' => array( 1 ),
			),
			$extra
		);
	}

	public function test_shared_sets_last_shared_and_fires_action(): void {
		Functions\expect( 'update_post_meta' )->once()->with( 12177, 'masthead_last_shared', '2026-11-09T14:00:00-08:00' );
		Actions\expectDone( 'masthead_shared' )->once();
		$result = ( new SharePost( $this->source( 'shared' ), new Clock() ) )->execute( $this->input() );
		$this->assertSame( 'shared', $result['status'] );
	}

	public function test_queued_fires_action_without_setting_last_shared(): void {
		Functions\expect( 'update_post_meta' )->never();
		Actions\expectDone( 'masthead_shared' )->once();
		$source = $this->source( 'queued' );
		$result = ( new SharePost( $source, new Clock() ) )->execute( $this->input( array( 'at' => '2026-11-12T09:00:00-08:00' ) ) );
		$this->assertSame( 'queued', $result['status'] );
		$this->assertSame( array( 'at' ), $source->calls );
	}

	public function test_unavailable_source_becomes_error_and_writes_nothing(): void {
		Functions\expect( 'update_post_meta' )->never();
		Actions\expectDone( 'masthead_shared' )->never();
		$result = ( new SharePost( $this->source( 'unavailable' ), new Clock() ) )->execute( $this->input() );
		$this->assertSame( 'masthead_share_unavailable', $result->get_error_code() );
		$this->assertSame( array( 'status' => 409 ), $result->get_error_data() );
		$this->assertSame( 'Because reasons.', $result->get_error_message() );
		$failed = ( new SharePost( $this->source( 'error' ), new Clock() ) )->execute( $this->input() );
		$this->assertSame( 'masthead_share_failed', $failed->get_error_code() );
		$this->assertSame( array( 'status' => 502 ), $failed->get_error_data() );
	}

	public function test_unknown_connection_is_refused(): void {
		$source = $this->source( 'shared' );
		$result = ( new SharePost( $source, new Clock() ) )->execute( $this->input( array( 'connection_ids' => array( 1, 99 ) ) ) );
		$this->assertSame( 'masthead_unknown_connection', $result->get_error_code() );
		$this->assertSame( array(), $source->calls );
		$empty = ( new SharePost( $source, new Clock() ) )->execute( $this->input( array( 'connection_ids' => array() ) ) );
		$this->assertSame( 'masthead_invalid_input', $empty->get_error_code() );
		$blank = ( new SharePost( $source, new Clock() ) )->execute( $this->input( array( 'message' => '   ' ) ) );
		$this->assertSame( 'masthead_invalid_input', $blank->get_error_code() );
	}

	public function test_past_at_is_refused(): void {
		$source = $this->source( 'queued' );
		foreach ( array( '2026-11-09T13:00:00-08:00', '2026-11-09T14:00:00-08:00', 'tomorrow' ) as $at ) {
			$result = ( new SharePost( $source, new Clock() ) )->execute( $this->input( array( 'at' => $at ) ) );
			$this->assertSame( 'masthead_invalid_date', $result->get_error_code(), $at );
		}
		$this->assertSame( array(), $source->calls );
		Functions\when( 'get_post' )->justReturn( (object) array( 'post_status' => 'future' ) );
		$this->assertSame( 'masthead_not_published', ( new SharePost( $source, new Clock() ) )->execute( $this->input() )->get_error_code() );
		Functions\when( 'get_post' )->justReturn( null );
		$this->assertSame( 'masthead_not_found', ( new SharePost( $source, new Clock() ) )->execute( $this->input() )->get_error_code() );
	}
}
