<?php
/**
 * Rhythm activation test.
 *
 * @package Masthead
 */

namespace Masthead\Tests\Integration\Plan;

use Masthead\Plan\Rhythm;
use Masthead\Plugin;
use Masthead\Tests\Integration\TestCase;

/**
 * Activation installs the default rhythm.
 */
final class RhythmActivationTest extends TestCase {

	public function test_activation_installs_defaults_for_existing_categories(): void {
		delete_option( 'masthead_rhythm' );
		self::factory()->category->create(
			array(
				'slug' => 'technology',
				'name' => 'Technology',
			) 
		);
		self::factory()->category->create(
			array(
				'slug' => 'journal',
				'name' => 'Journal',
			) 
		);

		Plugin::activate();

		$stored = get_option( 'masthead_rhythm' );
		$this->assertIsArray( $stored );
		$this->assertCount( 7, $stored );
		$this->assertSame( array( 'technology' ), $stored[2]['categories'] );
		$this->assertSame( array( 'technology' ), $stored[4]['categories'] );
		$this->assertSame( array( 'journal' ), $stored[5]['categories'] );
		$this->assertSame( array(), $stored[0]['categories'] );
		$this->assertSame( 'standalone', Rhythm::get()[3]['lane'] );

		update_option( 'masthead_rhythm', array( 'marker' => 1 ) );
		Plugin::activate();
		$this->assertSame( array( 'marker' => 1 ), get_option( 'masthead_rhythm' ) );
	}
}
