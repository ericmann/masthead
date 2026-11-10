<?php
/**
 * Base unit test case.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;

/**
 * Sets up Brain Monkey with the stubs every test needs.
 */
abstract class TestCase extends PhpUnitTestCase {

	/**
	 * Site zone used by every unit test.
	 */
	public const ZONE = 'America/Los_Angeles';

	/**
	 * Sets up Brain Monkey.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
		Functions\when( 'wp_timezone' )->alias(
			static function () {
				return new \DateTimeZone( self::ZONE );
			}
		);
	}

	/**
	 * Tears down Brain Monkey.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}
}
