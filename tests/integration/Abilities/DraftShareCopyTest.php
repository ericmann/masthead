<?php
/**
 * DraftShareCopy ability integration tests.
 *
 * @package Masthead
 */

namespace Masthead\Tests\Integration\Abilities;

use Masthead\Tests\Integration\TestCase;

/**
 * DraftShareCopy ability integration tests.
 */
final class DraftShareCopyTest extends TestCase {

	public function set_up(): void {
		parent::set_up();
		delete_option( 'masthead_ai_usage' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	private function ability() {
		$ability = wp_get_ability( 'masthead/draft-share-copy' );
		$this->assertNotNull( $ability );
		return $ability;
	}

	public function test_allowed(): void {
		$this->assertTrue( $this->ability()->check_permissions( array( 'post_id' => 1 ) ) );
		$this->assertTrue( $this->ability()->get_meta()['annotations']['readonly'] );
	}

	public function test_denied(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$result = $this->ability()->check_permissions( array( 'post_id' => 1 ) );
		$this->assertTrue( false === $result || is_wp_error( $result ) );
		$this->assertWPError( $this->ability()->execute( array( 'post_id' => 1 ) ) );
	}

	public function test_happy_path(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_title' => 'Shareable',
			) 
		);
		$result  = $this->ability()->execute( array( 'post_id' => $post_id ) );
		$this->assertNotWPError( $result );
		$this->assertSame( 'New on the blog: a closer look at the tools we rely on every day. Worth a read.', $result['message'] );
	}

	public function test_draft_missing_and_invalid_posts_are_refused(): void {
		$draft = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		$this->assertSame( 'masthead_not_published', $this->ability()->execute( array( 'post_id' => $draft ) )->get_error_code() );
		$this->assertSame( 'masthead_not_found', $this->ability()->execute( array( 'post_id' => 999999 ) )->get_error_code() );
		$this->assertSame( 'masthead_invalid_post', $this->ability()->execute( array( 'post_id' => 0 ) )->get_error_code() );
	}
}
