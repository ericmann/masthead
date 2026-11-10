<?php
/**
 * Scoring tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Desk;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Masthead\Desk\Scoring;
use Masthead\Tests\Unit\TestCase;

/**
 * Scoring tests.
 */
final class ScoringTest extends TestCase {

	/**
	 * Weights for the test, or null for the defaults.
	 *
	 * @var array|null
	 */
	private ?array $weights = null;

	protected function setUp(): void {
		parent::setUp();
		$this->weights = null;
		Functions\when( 'get_option' )->alias(
			static fn( $name, $fallback = false ) => 'masthead_settings' === $name ? array() : $fallback
		);
		Filters\expectApplied( 'masthead_config' )->zeroOrMoreTimes()->andReturnUsing(
			function ( $flat ) {
				if ( null !== $this->weights ) {
					$flat['swap.weights'] = $this->weights;
				}
				return $flat;
			}
		);
	}

	private function lane( array $categories ): array {
		return array(
			'slug'       => 's',
			'label'      => 'L',
			'categories' => $categories,
		);
	}

	private function post( string $primary, array $categories ): array {
		return array(
			'primary_category' => $primary,
			'categories'       => $categories,
		);
	}

	public function test_lane_term_values(): void {
		$scoring = new Scoring();
		$lane    = $this->lane( array( 'technology' ) );
		$this->assertSame( 1.0, $scoring->terms( $this->post( 'technology', array( 'technology' ) ), '2026-11-17', $lane, false, null, null )['lane'] );
		$this->assertSame( 0.5, $scoring->terms( $this->post( 'faith', array( 'faith', 'technology' ) ), '2026-11-17', $lane, false, null, null )['lane'] );
		$this->assertSame( 0.0, $scoring->terms( $this->post( 'writing', array( 'writing' ) ), '2026-11-17', $lane, false, null, null )['lane'] );
		$this->assertSame( 1.0, $scoring->terms( $this->post( 'writing', array( 'writing' ) ), '2026-11-18', $this->lane( array() ), false, null, null )['lane'] );
	}

	public function test_lane_exception_forces_lane_term_to_one(): void {
		$terms = ( new Scoring() )->terms( $this->post( 'writing', array( 'writing' ) ), '2026-12-14', $this->lane( array( 'business' ) ), true, null, null );
		$this->assertSame( 1.0, $terms['lane'] );
	}

	public function test_category_and_weekday_terms_are_relative_to_their_maximum(): void {
		$terms = ( new Scoring() )->terms(
			$this->post( 'writing', array( 'writing' ) ),
			'2026-11-09',
			$this->lane( array() ),
			false,
			array(
				'technology' => 400,
				'writing'    => 100,
			),
			array(
				1 => 20.0,
				3 => 50.0,
			)
		);
		$this->assertSame( 0.25, $terms['category'] );
		$this->assertSame( 0.4, $terms['weekday'] );
		$absent = ( new Scoring() )->terms( $this->post( 'faith', array( 'faith' ) ), '2026-11-10', $this->lane( array() ), false, array( 'technology' => 400 ), array( 1 => 20.0 ) );
		$this->assertSame( 0.0, $absent['category'], 'a primary category absent from the map counts as zero views' );
		$this->assertSame( 0.0, $absent['weekday'] );
	}

	public function test_null_stats_drop_both_stats_terms(): void {
		$scoring = new Scoring();
		$post    = $this->post( 'writing', array( 'writing' ) );
		$terms   = $scoring->terms( $post, '2026-11-09', $this->lane( array() ), false, null, null );
		$this->assertNull( $terms['category'] );
		$this->assertNull( $terms['weekday'] );
		$zero = $scoring->terms( $post, '2026-11-09', $this->lane( array() ), false, array( 'writing' => 0 ), array( 1 => 0.0 ) );
		$this->assertNull( $zero['category'], 'a zero maximum drops the term' );
		$this->assertNull( $zero['weekday'] );
		$nocat = $scoring->terms(
			array(
				'primary_category' => null,
				'categories' => array(),
			),
			'2026-11-09',
			$this->lane( array() ),
			false,
			array( 'writing' => 5 ),
			null 
		);
		$this->assertNull( $nocat['category'] );
	}

	public function test_score_without_stats_equals_the_lane_term(): void {
		$scoring = new Scoring();
		foreach ( array( 0.0, 0.5, 1.0 ) as $lane_term ) {
			$this->assertSame(
				$lane_term,
				$scoring->score(
					array(
						'lane'     => $lane_term,
						'category' => null,
						'weekday'  => null,
					)
				)
			);
		}
	}

	public function test_score_is_the_weighted_mean_of_present_terms(): void {
		$scoring = new Scoring();
		$full    = $scoring->score(
			array(
				'lane'     => 0.5,
				'category' => 1.0,
				'weekday'  => 0.0,
			)
		);
		$this->assertEqualsWithDelta( ( 0.5 * 0.5 + 0.3 * 1.0 + 0.2 * 0.0 ) / 1.0, $full, 1e-9 );
		$partial = $scoring->score(
			array(
				'lane'     => 1.0,
				'category' => 0.0,
				'weekday'  => null,
			)
		);
		$this->assertEqualsWithDelta( 0.5 / 0.8, $partial, 1e-9 );
	}

	public function test_weights_come_from_config(): void {
		$this->weights = array(
			'lane'     => 1.0,
			'category' => 1.0,
			'weekday'  => 0.0,
		);
		$score         = ( new Scoring() )->score(
			array(
				'lane'     => 1.0,
				'category' => 0.0,
				'weekday'  => 1.0,
			)
		);
		$this->assertSame( 0.5, $score );
		$this->weights = array(
			'lane'     => 0.0,
			'category' => 0.0,
			'weekday'  => 0.0,
		);
		$this->assertSame(
			0.0,
			( new Scoring() )->score(
				array(
					'lane'     => 1.0,
					'category' => 1.0,
					'weekday'  => 1.0,
				)
			)
		);
	}

	public function test_score_is_always_between_zero_and_one(): void {
		$grid    = array( 0.0, 0.25, 0.5, 1.0, null );
		$weights = array( 0.0, 0.2, 1.0 );
		foreach ( $weights as $wl ) {
			foreach ( $weights as $wc ) {
				foreach ( $weights as $ww ) {
					$this->weights = array(
						'lane'     => $wl,
						'category' => $wc,
						'weekday'  => $ww,
					);
					foreach ( $grid as $l ) {
						foreach ( $grid as $c ) {
							foreach ( $grid as $w ) {
								$score = ( new Scoring() )->score(
									array(
										'lane'     => $l ?? 0.0,
										'category' => $c,
										'weekday'  => $w,
									)
								);
								$this->assertGreaterThanOrEqual( 0.0, $score );
								$this->assertLessThanOrEqual( 1.0, $score );
							}
						}
					}
				}
			}
		}
	}
}
