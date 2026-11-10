<?php
/**
 * Capability (prompt_ai fallback) tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Ai;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Masthead\Ai\Capability;
use Masthead\Config;
use Masthead\Tests\Unit\TestCase;

/**
 * Capability tests.
 */
final class CapabilityTest extends TestCase {

	private array $overrides = array();

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_option' )->justReturn( array() );
		Filters\expectApplied( 'masthead_config' )->zeroOrMoreTimes()->andReturnUsing(
			function ( $flat ) {
				return array_merge( $flat, $this->overrides );
			}
		);
	}

	private function grant( array $allcaps, array $caps = array( 'prompt_ai' ) ) {
		return ( new Capability() )->grant( $allcaps, $caps );
	}

	public function test_default_fallback_is_publish_posts_and_not_settable(): void {
		$this->assertSame( 'publish_posts', Config::defaults()['ai.fallback_capability'] );
		$this->assertNotContains( 'ai.fallback_capability', Config::settable() );
	}

	public function test_missing_prompt_ai_is_granted_to_a_publisher(): void {
		$caps = $this->grant(
			array(
				'edit_posts'    => true,
				'publish_posts' => true,
			)
		);
		$this->assertTrue( $caps['prompt_ai'] );
	}

	public function test_missing_prompt_ai_is_not_granted_without_the_fallback_capability(): void {
		$this->assertArrayNotHasKey( 'prompt_ai', $this->grant( array( 'read' => true ) ) );
		$this->assertArrayNotHasKey( 'prompt_ai', $this->grant( array( 'edit_posts' => true ) ) );
		$this->assertArrayNotHasKey(
			'prompt_ai',
			$this->grant(
				array(
					'edit_posts'    => true,
					'publish_posts' => false,
				)
			)
		);
	}

	public function test_explicit_prompt_ai_wins(): void {
		$denied = array(
			'publish_posts' => true,
			'prompt_ai'     => false,
		);
		$this->assertSame( $denied, $this->grant( $denied ) );
		$granted = array(
			'read'      => true,
			'prompt_ai' => true,
		);
		$this->assertSame( $granted, $this->grant( $granted ) );
	}

	public function test_fallback_capability_comes_from_config(): void {
		$this->overrides = array( 'ai.fallback_capability' => 'manage_options' );
		$this->assertArrayNotHasKey( 'prompt_ai', $this->grant( array( 'publish_posts' => true ) ) );
		$this->assertTrue(
			$this->grant(
				array(
					'publish_posts'  => true,
					'manage_options' => true,
				)
			)['prompt_ai']
		);
	}

	public function test_an_empty_or_invalid_fallback_grants_nothing(): void {
		foreach ( array( '', null, false, array( 'publish_posts' ) ) as $fallback ) {
			$this->overrides = array( 'ai.fallback_capability' => $fallback );
			$this->assertArrayNotHasKey( 'prompt_ai', $this->grant( array( 'publish_posts' => true ) ), gettype( $fallback ) );
		}
	}

	public function test_other_capability_checks_are_left_untouched(): void {
		$allcaps = array( 'publish_posts' => true );
		$this->assertSame( $allcaps, $this->grant( $allcaps, array( 'edit_posts' ) ) );
		$this->assertSame( $allcaps, $this->grant( $allcaps, array() ) );
		$this->assertSame( 'not an array', ( new Capability() )->grant( 'not an array', array( 'prompt_ai' ) ) );
	}
}
