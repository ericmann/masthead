<?php
/**
 * Rhythm tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Plan;

use Brain\Monkey\Functions;
use Masthead\Plan\Rhythm;
use Masthead\Tests\Unit\TestCase;

/**
 * Rhythm tests.
 */
final class RhythmTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'sanitize_title' )->alias( static fn( $v ) => trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $v ) ), '-' ) );
		Functions\when( 'sanitize_text_field' )->alias( static fn( $v ) => trim( (string) $v ) );
	}

	public function test_defaults_equal_the_fixture_file(): void {
		$fixture = json_decode( (string) file_get_contents( dirname( __DIR__, 3 ) . '/docs/fixtures/rhythm-defaults.json' ), true );
		$this->assertSame( array_keys( Rhythm::DEFAULTS ), array_map( 'intval', array_keys( $fixture ) ) );
		foreach ( $fixture as $weekday => $day ) {
			$this->assertSame( $day, Rhythm::DEFAULTS[ (int) $weekday ], "weekday $weekday" );
		}
	}

	public function test_sanitize_returns_seven_weekdays(): void {
		$out = Rhythm::sanitize( null );
		$this->assertSame( array( 0, 1, 2, 3, 4, 5, 6 ), array_keys( $out ) );
		$this->assertSame( Rhythm::DEFAULTS, $out );

		$out = Rhythm::sanitize(
			array(
				'3' => array(
					'lane'       => 'Open Day',
					'label'      => ' Open ',
					'categories' => array( 'Writing', '', 5 ),
					'time'       => '09:15',
				),
			)
		);
		$this->assertCount( 7, $out );
		$this->assertSame( 'open-day', $out[3]['lane'] );
		$this->assertSame( 'Open', $out[3]['label'] );
		$this->assertSame( array( 'writing' ), $out[3]['categories'] );
		$this->assertSame( '09:15', $out[3]['time'] );
		$this->assertSame( Rhythm::DEFAULTS[2], $out[2] );
	}

	public function test_sanitize_rejects_bad_time_and_keeps_default(): void {
		$out = Rhythm::sanitize( array( 1 => array( 'time' => '25:00' ) ) );
		$this->assertSame( '07:00', $out[1]['time'] );
		$out = Rhythm::sanitize( array( 1 => array( 'categories' => array() ) ) );
		$this->assertSame( array(), $out[1]['categories'] );
		$out = Rhythm::sanitize( array( 1 => array( 'categories' => 'business' ) ) );
		$this->assertSame( array( 'business' ), $out[1]['categories'] );
	}

	public function test_install_defaults_keeps_only_existing_categories(): void {
		Functions\when( 'get_option' )->justReturn( false );
		Functions\when( 'term_exists' )->alias( static fn( $slug ) => in_array( $slug, array( 'technology', 'journal' ), true ) ? array( 'term_id' => 1 ) : null );
		Functions\expect( 'add_option' )->once()->andReturnUsing(
			function ( $name, $value ) {
				$this->assertSame( 'masthead_rhythm', $name );
				$this->assertSame( array( 'technology' ), $value[2]['categories'] );
				$this->assertSame( array( 'technology' ), $value[4]['categories'] );
				$this->assertSame( array( 'journal' ), $value[5]['categories'] );
				$this->assertSame( array(), $value[0]['categories'] );
				$this->assertSame( array(), $value[3]['categories'] );
				$this->assertSame( 'faith', $value[0]['lane'] );
				return true;
			}
		);
		Rhythm::install_defaults();
	}

	public function test_install_defaults_does_not_overwrite(): void {
		Functions\when( 'get_option' )->justReturn( array( 'x' => 1 ) );
		Functions\expect( 'add_option' )->never();
		Functions\expect( 'update_option' )->never();
		Rhythm::install_defaults();
		$this->assertTrue( true );
	}
}
