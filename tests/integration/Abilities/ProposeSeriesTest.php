<?php
/**
 * ProposeSeries ability integration tests.
 *
 * @package Masthead
 */

namespace Masthead\Tests\Integration\Abilities;

use Masthead\Tests\Integration\TestCase;

/**
 * ProposeSeries ability integration tests.
 */
final class ProposeSeriesTest extends TestCase {

	public function set_up(): void {
		parent::set_up();
		delete_option( 'masthead_ai_usage' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		foreach ( array( 'technology', 'faith', 'security' ) as $slug ) {
			if ( ! term_exists( $slug, 'category' ) ) {
				wp_insert_term( ucfirst( $slug ), 'category', array( 'slug' => $slug ) );
			}
		}
	}

	private function ability() {
		$ability = wp_get_ability( 'masthead/propose-series' );
		$this->assertNotNull( $ability );
		return $ability;
	}

	public function test_allowed(): void {
		$this->assertTrue( $this->ability()->check_permissions( array( 'prompt' => 'x' ) ) );
		$this->assertTrue( $this->ability()->get_meta()['annotations']['readonly'] );
	}

	public function test_denied(): void {
		// A contributor has edit_posts but not publish_posts, so the prompt_ai fallback does not apply.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'contributor' ) ) );
		$result = $this->ability()->check_permissions( array( 'prompt' => 'x' ) );
		$this->assertTrue( false === $result || is_wp_error( $result ) );
		$this->assertWPError( $this->ability()->execute( array( 'prompt' => 'x' ) ) );
	}

	public function test_happy_path(): void {
		$result = $this->ability()->execute(
			array(
				'prompt' => 'a 4-part security series starting the first Thursday in January',
				'start'  => '2099-01-01',
			)
		);
		$this->assertNotWPError( $result );
		$this->assertSame( 'Locking things down', $result['name'] );
		$this->assertSame( 'security', $result['category'] );
		$this->assertSame( array( '2099-01-01', '2099-01-08', '2099-01-15', '2099-01-22' ), array_column( $result['parts'], 'date' ) );
		$this->assertSame( 4, $result['cadence']['weekday'] );
		$this->assertSame( 'Start with the threat you actually face', $result['parts'][0]['title'] );
		$this->assertSame( array( 'Who might want in', 'What they would take' ), $result['parts'][0]['outline'] );
	}

	public function test_proposing_creates_no_post_and_no_term(): void {
		$posts = wp_count_posts()->draft + wp_count_posts()->publish + wp_count_posts()->future;
		$terms = wp_count_terms( array( 'hide_empty' => false ) );
		$this->ability()->execute(
			array(
				'prompt' => 'x',
				'start'  => '2099-01-01',
			)
		);
		$this->assertSame( $posts, wp_count_posts()->draft + wp_count_posts()->publish + wp_count_posts()->future );
		$this->assertEquals( $terms, wp_count_terms( array( 'hide_empty' => false ) ) );
	}

	public function test_bad_input_is_refused(): void {
		$this->assertSame( 'masthead_invalid_input', $this->ability()->execute( array( 'prompt' => '' ) )->get_error_code() );
		$this->assertSame(
			'masthead_invalid_date',
			$this->ability()->execute(
				array(
					'prompt' => 'x',
					'start' => '2001-01-01',
				) 
			)->get_error_code() 
		);
	}
}
