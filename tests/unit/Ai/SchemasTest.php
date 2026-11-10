<?php
/**
 * Schemas tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Ai;

use Masthead\Ai\Schemas;
use Masthead\Tests\Unit\TestCase;

/**
 * Schemas tests.
 */
final class SchemasTest extends TestCase {

	private function idea( string $category = 'technology' ): array {
		return array(
			'title'    => 'A title',
			'angle'    => 'An angle',
			'category' => $category,
		);
	}

	public function test_valid_ideas_pass(): void {
		$schema = Schemas::ideas( array( 'technology', 'faith' ), 3 );
		$this->assertSame( 'ideas', $schema['title'] );
		$this->assertFalse( $schema['additionalProperties'] );
		$data = array( 'ideas' => array( $this->idea(), $this->idea( 'faith' ) ) );
		$this->assertTrue( Schemas::validate( $data, $schema ) );
	}

	public function test_idea_with_category_outside_the_enum_fails(): void {
		$schema = Schemas::ideas( array( 'technology' ), 3 );
		$data   = array( 'ideas' => array( $this->idea( 'sports' ) ) );
		$this->assertSame( '$.ideas[0].category', Schemas::validate( $data, $schema ) );
	}

	public function test_extra_property_fails(): void {
		$schema        = Schemas::ideas( array( 'technology' ), 3 );
		$idea          = $this->idea();
		$idea['extra'] = 'x';
		$this->assertSame( '$.ideas[0].extra', Schemas::validate( array( 'ideas' => array( $idea ) ), $schema ) );
	}

	public function test_missing_required_property_fails(): void {
		$schema = Schemas::ideas( array( 'technology' ), 3 );
		$idea   = $this->idea();
		unset( $idea['angle'] );
		$this->assertSame( '$.ideas[0].angle', Schemas::validate( array( 'ideas' => array( $idea ) ), $schema ) );
		$this->assertSame( '$.ideas', Schemas::validate( array(), $schema ) );
	}

	public function test_too_many_ideas_fail(): void {
		$schema = Schemas::ideas( array( 'technology' ), 1 );
		$data   = array( 'ideas' => array( $this->idea(), $this->idea() ) );
		$this->assertSame( '$.ideas', Schemas::validate( $data, $schema ) );
	}

	public function test_empty_string_fails(): void {
		$this->assertSame( '$.message', Schemas::validate( array( 'message' => '' ), Schemas::share_copy() ) );
		$this->assertTrue( Schemas::validate( array( 'message' => 'Hi' ), Schemas::share_copy() ) );
		$this->assertSame( '$.outline', Schemas::validate( array( 'outline' => array() ), Schemas::outline() ) );
	}

	public function test_wrong_type_fails_without_coercion(): void {
		$series = Schemas::series_proposal( array( 'technology' ), 3 );
		$data   = $this->series( 1 );
		$data['every_weeks'] = '2';
		$this->assertSame( '$.every_weeks', Schemas::validate( $data, $series ) );
		$data['every_weeks'] = 1.0;
		$this->assertSame( '$.every_weeks', Schemas::validate( $data, $series ) );
		$this->assertSame( '$.message', Schemas::validate( array( 'message' => 5 ), Schemas::share_copy() ) );
		$this->assertSame( '$', Schemas::validate( 'text', Schemas::share_copy() ) );
		$this->assertSame( '$', Schemas::validate( array( 'a' ), Schemas::share_copy() ) );
		$this->assertSame( '$.every_weeks', Schemas::validate( array_merge( $this->series( 1 ), array( 'every_weeks' => 0 ) ), $series ) );
	}

	private function series( int $parts ): array {
		$list = array();
		for ( $i = 0; $i < $parts; $i++ ) {
			$list[] = array(
				'title'   => 'Part',
				'outline' => array( 'Point' ),
			);
		}
		return array(
			'name'        => 'Series',
			'category'    => 'technology',
			'every_weeks' => 1,
			'parts'       => $list,
		);
	}

	public function test_valid_series_proposal_passes(): void {
		$schema = Schemas::series_proposal( array( 'technology' ), 3 );
		$this->assertSame( 'series_proposal', $schema['title'] );
		$this->assertTrue( Schemas::validate( $this->series( 3 ), $schema ) );
	}

	public function test_series_with_more_parts_than_the_maximum_fails(): void {
		$schema = Schemas::series_proposal( array( 'technology' ), 3 );
		$this->assertSame( '$.parts', Schemas::validate( $this->series( 4 ), $schema ) );
	}

	public function test_validate_reports_the_path_of_the_first_violation(): void {
		$schema = Schemas::series_proposal( array( 'technology' ), 3 );
		$data   = $this->series( 2 );
		$data['parts'][1]['outline'] = array( '' );
		$this->assertSame( '$.parts[1].outline[0]', Schemas::validate( $data, $schema ) );
		$before = $data;
		Schemas::validate( $data, $schema );
		$this->assertSame( $before, $data );
	}
	public function test_wire_drops_the_constraints_structured_output_apis_reject(): void {
		$full = Schemas::series_proposal( array( 'technology' ), 12 );
		$wire = Schemas::wire( $full );
		$json = (string) json_encode( $wire );
		foreach ( array( '"title":"', '"minLength"', '"minimum"', '"maxItems"' ) as $keyword ) {
			$this->assertStringNotContainsString( $keyword, $json, $keyword );
		}
		$this->assertSame( 'At least 1.', $wire['properties']['every_weeks']['description'] );
		$this->assertSame( 'At most 12 items.', $wire['properties']['parts']['description'] );
		$this->assertSame( 'Not empty.', $wire['properties']['name']['description'] );
		$this->assertSame( 1, $wire['properties']['parts']['items']['properties']['outline']['minItems'] );
		$this->assertFalse( $wire['properties']['parts']['items']['additionalProperties'] );
		$this->assertSame( array( 'title', 'outline' ), $wire['properties']['parts']['items']['required'] );
		$this->assertSame( array( 'technology' ), $wire['properties']['category']['enum'] );
		$this->assertSame( 'series_proposal', $full['title'] );
	}

	public function test_wire_keeps_an_existing_description_and_drops_min_items_above_one(): void {
		$wire = Schemas::wire(
			array(
				'type'        => 'array',
				'description' => 'Tags.',
				'minItems'    => 2,
				'items'       => array( 'type' => 'string' ),
			)
		);
		$this->assertArrayNotHasKey( 'minItems', $wire );
		$this->assertSame( 'Tags. At least 2 items.', $wire['description'] );
	}

	public function test_validate_still_enforces_what_wire_drops(): void {
		$schema = Schemas::ideas( array( 'technology' ), 1 );
		$two    = array(
			'ideas' => array(
				array(
					'title' => 'A',
					'angle' => 'B',
					'category' => 'technology',
				),
				array(
					'title' => 'C',
					'angle' => 'D',
					'category' => 'technology',
				),
			),
		);
		$this->assertTrue( Schemas::validate( $two, Schemas::wire( $schema ) ) );
		$this->assertSame( '$.ideas', Schemas::validate( $two, $schema ) );
	}
}
