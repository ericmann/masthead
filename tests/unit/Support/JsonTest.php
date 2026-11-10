<?php
/**
 * Json tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Support;

use Brain\Monkey\Functions;
use Masthead\Support\Json;
use Masthead\Tests\Unit\TestCase;

/**
 * Json tests.
 */
final class JsonTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
	}

	public function test_decode_object_returns_null_for_invalid_or_non_object_json(): void {
		$this->assertNull( Json::decode_object( 'nope' ) );
		$this->assertNull( Json::decode_object( '[1,2]' ) );
		$this->assertNull( Json::decode_object( '"text"' ) );
		$this->assertSame( array( 'a' => 1 ), Json::decode_object( ' {"a":1}' ) );
		$this->assertSame( array(), Json::decode_object( '{}' ) );
	}

	public function test_string_list_drops_non_strings_and_blanks(): void {
		$this->assertSame( array( 'a', 'b' ), Json::string_list( array( ' a ', '', 5, null, '  ', 'b' ) ) );
		$this->assertSame( array(), Json::string_list( 'a' ) );
	}

	public function test_encode_keeps_unicode_and_slashes(): void {
		$this->assertSame( '{"u":"/é"}', Json::encode( array( 'u' => '/é' ) ) );
	}
}
