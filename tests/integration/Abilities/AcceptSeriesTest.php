<?php
/**
 * AcceptSeries ability integration tests.
 *
 * @package Masthead
 */

namespace Masthead\Tests\Integration\Abilities;

use Masthead\Plan\Cadence;
use Masthead\Plan\SeriesSource;
use Masthead\Tests\Integration\TestCase;

/**
 * AcceptSeries ability integration tests.
 */
final class AcceptSeriesTest extends TestCase {

	public function set_up(): void {
		parent::set_up();
		( new SeriesSource() )->register();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		foreach ( array( 'technology', 'security' ) as $slug ) {
			if ( ! term_exists( $slug, 'category' ) ) {
				wp_insert_term( ucfirst( $slug ), 'category', array( 'slug' => $slug ) );
			}
		}
	}

	private function ability() {
		$ability = wp_get_ability( 'masthead/accept-series' );
		$this->assertNotNull( $ability );
		return $ability;
	}

	private function input( array $over = array() ): array {
		$parts = array();
		foreach ( array( '2099-01-01', '2099-01-08', '2099-01-15', '2099-01-22' ) as $index => $date ) {
			$parts[] = array(
				'date'    => $date,
				'title'   => 'Security part ' . ( $index + 1 ),
				'outline' => array( 'Point A', 'Point B' ),
			);
		}
		return array_merge(
			array(
				'name'     => 'Locking things down',
				'category' => 'security',
				'cadence'  => array(
					'every_weeks' => 1,
					'weekday'     => 4,
					'time'        => '07:30',
				),
				'parts'    => $parts,
			),
			$over
		);
	}

	public function test_allowed(): void {
		$this->assertTrue( $this->ability()->check_permissions( $this->input() ) );
		$annotations = $this->ability()->get_meta()['annotations'];
		$this->assertFalse( $annotations['readonly'] );
		$this->assertFalse( $annotations['destructive'] );
	}

	public function test_denied(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$result = $this->ability()->check_permissions( $this->input() );
		$this->assertTrue( false === $result || is_wp_error( $result ) );
		$this->assertWPError( $this->ability()->execute( $this->input() ) );
	}

	public function test_happy_path(): void {
		$result = $this->ability()->execute( $this->input() );
		$this->assertNotWPError( $result );
		$series = new SeriesSource();
		$term   = get_term( $result['term_id'], $series->taxonomy() );
		$this->assertSame( 'Locking things down', $term->name );
		$this->assertSame( 4, (int) get_term_meta( $result['term_id'], $series->total_meta(), true ) );
		$this->assertSame( 1, Cadence::from_json( (string) get_term_meta( $result['term_id'], 'masthead_cadence', true ) )['every_weeks'] );
		$this->assertCount( 4, $result['post_ids'] );
		foreach ( $result['post_ids'] as $index => $post_id ) {
			$post = get_post( $post_id );
			$this->assertSame( 'draft', $post->post_status );
			$this->assertSame( array( '2099-01-01', '2099-01-08', '2099-01-15', '2099-01-22' )[ $index ] . ' 07:30:00', $post->post_date );
			$this->assertTrue( (bool) get_post_meta( $post_id, 'masthead_planned', true ) );
			$this->assertSame( $index + 1, (int) get_post_meta( $post_id, $series->part_meta(), true ) );
			$this->assertSame( array( 'security' ), wp_list_pluck( get_the_category( $post_id ), 'slug' ) );
			$this->assertStringContainsString( '<li>Point A</li>', $post->post_content );
		}
		$this->assertSame( 4, count( $series->parts( $result['term_id'] ) ) );
	}

	public function test_invalid_part_creates_no_term_and_no_post(): void {
		$input                       = $this->input();
		$input['parts'][2]['title'] = '';
		$posts                       = (int) wp_count_posts()->draft;
		$result                      = $this->ability()->execute( $input );
		$this->assertWPError( $result );
		$this->assertSame( 'masthead_invalid_input', $result->get_error_code() );
		$this->assertEmpty( term_exists( 'Locking things down', ( new SeriesSource() )->taxonomy() ) );
		$this->assertSame( $posts, (int) wp_count_posts()->draft );
	}

	public function test_existing_series_name_is_refused(): void {
		$first = $this->ability()->execute( $this->input() );
		$this->assertNotWPError( $first );
		$posts  = (int) wp_count_posts()->draft;
		$second = $this->ability()->execute( $this->input() );
		$this->assertWPError( $second );
		$this->assertSame( 'masthead_series_exists', $second->get_error_code() );
		$this->assertSame( 409, $second->get_error_data()['status'] );
		$this->assertSame( $posts, (int) wp_count_posts()->draft );
	}
}
