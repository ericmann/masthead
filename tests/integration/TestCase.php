<?php
/**
 * Base integration test case.
 *
 * @package Masthead
 */

namespace Masthead\Tests\Integration;

use WP_UnitTestCase;

/**
 * Pins the clock and the site timezone.
 */
abstract class TestCase extends WP_UnitTestCase {

	/**
	 * Pinned "now" in site time.
	 */
	public const NOW = '2026-11-09 14:00:00';

	/**
	 * Pins the clock.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		update_option( 'timezone_string', 'America/Los_Angeles' );
		add_filter( 'masthead_now', array( $this, 'pinned_now' ) );
	}

	/**
	 * Filter callback returning the pinned time.
	 *
	 * @return string
	 */
	public function pinned_now(): string {
		return self::NOW;
	}
}
