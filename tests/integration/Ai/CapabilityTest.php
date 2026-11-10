<?php
/**
 * prompt_ai fallback integration tests.
 *
 * @package Masthead
 */

namespace Masthead\Tests\Integration\Ai;

use Masthead\Tests\Integration\TestCase;

/**
 * The user_has_cap fallback wired by Plugin::boot().
 */
final class CapabilityTest extends TestCase {

	private function user( string $role ): int {
		return self::factory()->user->create( array( 'role' => $role ) );
	}

	public function test_core_roles_do_not_define_prompt_ai(): void {
		foreach ( array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' ) as $role ) {
			$this->assertArrayNotHasKey( 'prompt_ai', get_role( $role )->capabilities, $role );
		}
	}

	public function test_administrator_without_prompt_ai_is_granted(): void {
		$admin = $this->user( 'administrator' );
		$this->assertArrayNotHasKey( 'prompt_ai', get_userdata( $admin )->allcaps );
		$this->assertTrue( user_can( $admin, 'prompt_ai' ) );

		wp_set_current_user( $admin );
		$this->assertTrue( wp_get_ability( 'masthead/draft-outline' )->check_permissions( array( 'title' => 'A title' ) ) );
	}

	public function test_editor_and_author_are_granted_through_publish_posts(): void {
		$this->assertTrue( user_can( $this->user( 'editor' ), 'prompt_ai' ) );
		$this->assertTrue( user_can( $this->user( 'author' ), 'prompt_ai' ) );
	}

	public function test_subscriber_and_contributor_are_not_granted(): void {
		$this->assertFalse( user_can( $this->user( 'subscriber' ), 'prompt_ai' ) );
		$contributor = $this->user( 'contributor' );
		$this->assertTrue( user_can( $contributor, 'edit_posts' ) );
		$this->assertFalse( user_can( $contributor, 'prompt_ai' ) );

		wp_set_current_user( $contributor );
		$denied = wp_get_ability( 'masthead/draft-outline' )->check_permissions( array( 'title' => 'A title' ) );
		$this->assertTrue( false === $denied || is_wp_error( $denied ) );
	}

	public function test_explicit_deny_on_the_user_is_respected(): void {
		foreach ( array( 'administrator', 'editor' ) as $role ) {
			$user = $this->user( $role );
			get_userdata( $user )->add_cap( 'prompt_ai', false );
			$this->assertTrue( user_can( $user, 'publish_posts' ), $role );
			$this->assertFalse( user_can( $user, 'prompt_ai' ), $role );
		}
	}

	public function test_explicit_deny_on_the_role_is_respected(): void {
		$role = get_role( 'editor' );
		$role->add_cap( 'prompt_ai', false );
		try {
			$editor = $this->user( 'editor' );
			$this->assertTrue( user_can( $editor, 'publish_posts' ) );
			$this->assertFalse( user_can( $editor, 'prompt_ai' ) );
		} finally {
			$role->remove_cap( 'prompt_ai' );
		}
		$this->assertTrue( user_can( $this->user( 'editor' ), 'prompt_ai' ) );
	}

	public function test_another_plugins_decision_wins(): void {
		$deny  = static function ( $allcaps ) {
			$allcaps['prompt_ai'] = false;
			return $allcaps;
		};
		$admin = $this->user( 'administrator' );
		add_filter( 'user_has_cap', $deny );
		try {
			$this->assertFalse( user_can( $admin, 'prompt_ai' ) );
		} finally {
			remove_filter( 'user_has_cap', $deny );
		}

		$allow       = static function ( $allcaps ) {
			$allcaps['prompt_ai'] = true;
			return $allcaps;
		};
		$contributor = $this->user( 'contributor' );
		add_filter( 'user_has_cap', $allow );
		try {
			$this->assertTrue( user_can( $contributor, 'prompt_ai' ) );
		} finally {
			remove_filter( 'user_has_cap', $allow );
		}
	}

	public function test_fallback_capability_follows_config(): void {
		$only_admins = static function ( $config ) {
			$config['ai.fallback_capability'] = 'manage_options';
			return $config;
		};
		add_filter( 'masthead_config', $only_admins );
		try {
			$this->assertFalse( user_can( $this->user( 'editor' ), 'prompt_ai' ) );
			$this->assertTrue( user_can( $this->user( 'administrator' ), 'prompt_ai' ) );
		} finally {
			remove_filter( 'masthead_config', $only_admins );
		}
	}
}
