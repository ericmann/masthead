<?php
/**
 * Seeder tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Cli;

use Masthead\Cli\Seeder;
use Masthead\Tests\Unit\TestCase;

/**
 * Seeder tests.
 */
final class SeederTest extends TestCase {

	private const IDS = array(
		'technology' => 8,
		'faith'      => 106,
	);

	public function test_post_array_for_dated_post_has_explicit_gmt_date(): void {
		$arr = Seeder::post_array(
			array(
				'id'         => 12177,
				'status'     => 'future',
				'date'       => '2026-11-11 08:00:00',
				'title'      => 'Nineteen Years',
				'categories' => array( 'technology', 'unknown' ),
			),
			self::IDS
		);
		$this->assertSame( '2026-11-11 08:00:00', $arr['post_date'] );
		$this->assertSame( '2026-11-11 16:00:00', $arr['post_date_gmt'] );
		$this->assertTrue( $arr['edit_date'] );
		$this->assertSame( array( 8 ), $arr['post_category'] );
		$this->assertSame( 'Nineteen Years', $arr['post_title'] );
	}

	public function test_post_array_for_undated_draft_uses_modified_and_no_gmt_date(): void {
		$arr = Seeder::post_array(
			array(
				'id'         => 10178,
				'status'     => 'draft',
				'date'       => null,
				'modified'   => '2026-10-29 21:46:13',
				'title'      => 'Swarms',
				'categories' => array( 'technology' ),
				'tags'       => array( 'AI', 'developer tools' ),
			),
			self::IDS
		);
		$this->assertSame( '2026-10-29 21:46:13', $arr['post_date'] );
		$this->assertArrayNotHasKey( 'post_date_gmt', $arr );
		$this->assertArrayNotHasKey( 'edit_date', $arr );
		$this->assertSame( array( 'AI', 'developer tools' ), $arr['tags_input'] );
	}

	public function test_import_id_is_the_fixture_id(): void {
		$arr = Seeder::post_array(
			array(
				'id'     => 10194,
				'status' => 'future',
				'date'   => '2026-11-12 07:37:00',
				'title'  => 'T',
			),
			self::IDS
		);
		$this->assertSame( 10194, $arr['import_id'] );
		$this->assertSame( array(), $arr['post_category'] );
	}
}
