<?php
/**
 * Payloads tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Stats;

use Masthead\Stats\Payloads;
use Masthead\Tests\Unit\TestCase;

/**
 * Payloads tests.
 */
final class PayloadsTest extends TestCase {

	public function test_by_weekday_averages_daily_views_per_weekday(): void {
		$raw = array(
			'fields' => array( 'period', 'views', 'visitors' ),
			'data'   => array(
				array( '2026-11-02', 60, 0 ),
				array( '2026-11-09', 40, 0 ),
				array( '2026-11-03', 30, 0 ),
				array( '2026-11-08', 7, 0 ),
			),
		);
		$this->assertSame(
			array(
				0 => 7.0,
				1 => 50.0,
				2 => 30.0,
			),
			Payloads::by_weekday( $raw )
		);
	}

	public function test_top_posts_orders_by_views_then_id_and_respects_limit(): void {
		$raw = array(
			'days' => array(
				'2026-11-08' => array(
					'postviews' => array(
						array(
							'id'    => 30,
							'views' => 5,
						),
						array(
							'id'    => 10,
							'views' => 5,
						),
						array(
							'id'    => 20,
							'views' => 9,
						),
					),
				),
				'2026-11-09' => array(
					'postviews' => array(
						array(
							'id'    => 30,
							'views' => 4,
						),
					),
				),
			),
		);
		$this->assertSame(
			array(
				20 => 9,
				30 => 9,
				10 => 5,
			),
			Payloads::top_posts( $raw, 10 )
		);
		$this->assertSame( array( 20 => 9 ), Payloads::top_posts( $raw, 1 ) );
		$this->assertSame( array(), Payloads::top_posts( $raw, 0 ) );
		$summary = array(
			'summary' => array(
				'postviews' => array(
					array(
						'id'    => 7,
						'views' => 3,
					),
				),
			),
		);
		$this->assertSame( array( 7 => 3 ), Payloads::top_posts( $summary, 5 ) );
	}

	public function test_by_category_from_top_posts_sums_by_primary_category(): void {
		$primary = static fn( int $id ) => array(
			1 => 'technology',
			2 => 'technology',
			3 => 'writing',
		)[ $id ] ?? null;
		$this->assertSame(
			array(
				'technology' => 15,
				'writing'    => 8,
			),
			Payloads::by_category_from_top_posts(
				array(
					1 => 10,
					2 => 5,
					3 => 8,
					4 => 99,
				),
				$primary
			)
		);
	}

	public function test_malformed_payload_yields_null(): void {
		$this->assertNull( Payloads::by_weekday( array() ) );
		$this->assertNull(
			Payloads::by_weekday(
				array(
					'fields' => array( 'period' ),
					'data' => array( array( '2026-11-02' ) ),
				) 
			) 
		);
		$this->assertNull(
			Payloads::by_weekday(
				array(
					'fields' => array( 'period', 'views' ),
					'data' => array( array( 'junk', 5 ) ),
				) 
			) 
		);
		$this->assertNull(
			Payloads::by_weekday(
				array(
					'fields' => array( 'period', 'views' ),
					'data' => array( array( '2026-11-02', '5' ) ),
				) 
			) 
		);
		$this->assertNull( Payloads::top_posts( array(), 5 ) );
		$this->assertNull(
			Payloads::top_posts(
				array(
					'days' => array(
						'x' => array(
							'postviews' => array(
								array(
									'id' => 'a',
									'views' => 1,
								),
							),
						),
					),
				),
				5 
			) 
		);
		$this->assertNull( Payloads::post_views( array( 'views' => 'many' ) ) );
		$this->assertNull( Payloads::post_views( array( 'views' => -1 ) ) );
		$this->assertSame( 210, Payloads::post_views( array( 'views' => 210 ) ) );
	}

	public function test_sample_fixture_maps_without_loss(): void {
		$file     = json_decode( (string) file_get_contents( dirname( __DIR__, 3 ) . '/docs/fixtures/stats-sample.json' ), true );
		$payloads = $file['payloads'];
		$this->assertTrue( $file['_synthetic'] );

		$weekdays = Payloads::by_weekday( $payloads['get_visits'] );
		$this->assertSame( array( 0, 1, 2, 3, 4, 5, 6 ), array_keys( $weekdays ) );
		$this->assertSame( 52.0, $weekdays[3] );

		$top = Payloads::top_posts( $payloads['get_top_posts'], 10 );
		$this->assertSame(
			array(
				12155 => 210,
				10193 => 160,
				10192 => 95,
				10191 => 70,
			),
			$top
		);
		$this->assertSame( 210, Payloads::post_views( $payloads['get_post_views'] ) );
	}
}
