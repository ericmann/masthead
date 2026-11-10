<?php
/**
 * JetpackStats tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Stats;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Masthead\Stats\Cache;
use Masthead\Stats\JetpackStats;
use Masthead\Support\Clock;
use Masthead\Tests\Unit\TestCase;

/**
 * JetpackStats tests.
 */
final class JetpackStatsTest extends TestCase {

	/**
	 * Fake transient store.
	 *
	 * @var array<string,mixed>
	 */
	private array $store = array();

	protected function setUp(): void {
		parent::setUp();
		$this->store = array();
		Functions\when( 'get_option' )->alias(
			static fn( $name, $fallback = false ) => 'masthead_settings' === $name ? array() : $fallback
		);
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'is_wp_error' )->alias( static fn( $v ) => $v instanceof \WP_Error );
		Functions\when( 'get_transient' )->alias( fn( $name ) => $this->store[ $name ] ?? false );
		Functions\when( 'set_transient' )->alias(
			function ( $name, $value ) {
				$this->store[ $name ] = $value;
				return true;
			}
		);
		Filters\expectApplied( 'masthead_now' )->zeroOrMoreTimes()->andReturn( '2026-11-09 13:00:00' );
	}

	private function payloads(): array {
		$file = json_decode( (string) file_get_contents( dirname( __DIR__, 3 ) . '/docs/fixtures/stats-sample.json' ), true );
		return $file['payloads'];
	}

	/**
	 * A client double. Public property calls records every call.
	 *
	 * @param array $responses Method name to payload or WP_Error.
	 * @return object
	 */
	private function client( array $responses ): object {
		return new class( $responses ) {
			public array $calls = array();

			public function __construct( private array $responses ) {}

			public function __call( string $method, array $arguments ) {
				$this->calls[] = array( $method, $arguments );
				if ( ! array_key_exists( $method, $this->responses ) ) {
					throw new \RuntimeException( 'unexpected ' . esc_html( $method ) );
				}
				return $this->responses[ $method ];
			}
		};
	}

	private function stats( object $client ): JetpackStats {
		$primary = static fn( int $id ) => array(
			12155 => 'writing',
			10193 => 'technology',
			10192 => 'technology',
			10191 => 'technology',
		)[ $id ] ?? null;
		return new JetpackStats( new Cache( new Clock() ), $primary, $client );
	}

	public function test_top_posts_maps_the_client_payload(): void {
		$client = $this->client( array( 'get_top_posts' => $this->payloads()['get_top_posts'] ) );
		$stats  = $this->stats( $client );
		$this->assertTrue( $stats->available() );
		$this->assertSame(
			array(
				12155 => 210,
				10193 => 160,
			),
			$stats->top_posts( 30, 2 )
		);
		$this->assertSame(
			array(
				'get_top_posts',
				array(
					array(
						'period' => 'day',
						'num'    => 30,
						'max'    => 50,
					),
				),
			),
			$client->calls[0]
		);
		$this->assertSame( '2026-11-09T13:00:00-08:00', $stats->fetched_at() );
	}

	public function test_views_by_weekday_maps_the_client_payload(): void {
		$client = $this->client( array( 'get_visits' => $this->payloads()['get_visits'] ) );
		$stats  = $this->stats( $client );
		$this->assertSame( 52.0, $stats->views_by_weekday( 30 )[3] );
		$this->assertSame(
			array(
				array(
					'unit'     => 'day',
					'quantity' => 30,
				),
			),
			$client->calls[0][1]
		);
	}

	public function test_views_by_category_follows_the_spike_decision(): void {
		$client = $this->client( array( 'get_top_posts' => $this->payloads()['get_top_posts'] ) );
		$this->assertSame(
			array(
				'technology' => 325,
				'writing'    => 210,
			),
			$this->stats( $client )->views_by_category( 30 )
		);
		$this->assertSame( array( 'get_top_posts' ), array_column( $client->calls, 0 ), 'aggregated from top posts, not a separate call' );
	}

	public function test_post_views_sums_the_last_days_of_the_per_day_rows(): void {
		$client = $this->client( array( 'get_post_views' => $this->payloads()['get_post_views'] ) );
		$stats  = $this->stats( $client );
		$this->assertSame( 210, $stats->post_views( 12155, 1 ) );
		$this->assertSame( 210, $stats->post_views( 12155, 30 ) );
		$this->assertNull( $this->stats( $this->client( array( 'get_post_views' => array( 'views' => 5 ) ) ) )->post_views( 1, 30 ) );
	}

	public function test_client_error_yields_null(): void {
		$error = new \WP_Error( 'site_not_connected', 'No.' );
		$stats = $this->stats(
			$this->client(
				array(
					'get_top_posts' => $error,
					'get_visits' => $error,
					'get_post_views' => $error,
				) 
			) 
		);
		$this->assertNull( $stats->top_posts( 30, 5 ) );
		$this->assertNull( $stats->views_by_weekday( 30 ) );
		$this->assertNull( $stats->views_by_category( 30 ) );
		$this->assertNull( $stats->post_views( 1, 30 ) );
		$this->assertNull( $stats->fetched_at() );

		$throwing = $this->stats( $this->client( array() ) );
		$this->assertNull( $throwing->top_posts( 30, 5 ), 'a throwing client is null' );
		$malformed = $this->stats( $this->client( array( 'get_top_posts' => array( 'days' => 'x' ) ) ) );
		$this->assertNull( $malformed->top_posts( 30, 5 ) );
	}

	public function test_results_are_cached_between_calls(): void {
		$client = $this->client( array( 'get_top_posts' => $this->payloads()['get_top_posts'] ) );
		$stats  = $this->stats( $client );
		$stats->top_posts( 30, 5 );
		$stats->top_posts( 30, 2 );
		$stats->views_by_category( 30 );
		$this->assertCount( 1, $client->calls );
		$stats->top_posts( 7, 5 );
		$this->assertCount( 2, $client->calls, 'a different window is a different request' );
	}

	public function test_probe_scrubs_numbers_and_strings(): void {
		Functions\when( 'get_posts' )->justReturn( array( 12155 ) );
		$client = $this->client(
			array(
				'get_visits'     => $this->payloads()['get_visits'],
				'get_top_posts'  => $this->payloads()['get_top_posts'],
				'get_post_views' => $this->payloads()['get_post_views'],
				'get_tags'       => new \WP_Error( 'site_not_connected', 'No.' ),
			)
		);
		$probe = $this->stats( $client )->probe( 30 );
		$this->assertSame( array( 'get_visits', 'get_top_posts', 'get_tags', 'get_post_views' ), array_keys( $probe ) );
		$this->assertSame( array( '', '', '' ), $probe['get_visits']['fields'] );
		$this->assertSame( array( '', 0, 0 ), $probe['get_visits']['data'][0] );
		$post = $probe['get_top_posts']['days']['2026-11-09']['postviews'][0];
		$this->assertSame( 0, $post['id'] );
		$this->assertSame( 0, $post['views'] );
		$this->assertSame( '', $post['title'] );
		$this->assertSame( 0, $probe['get_top_posts']['days']['2026-11-09']['total_views'] );
		$this->assertSame( array( 'error' => 'site_not_connected' ), $probe['get_tags'] );
		$this->assertStringNotContainsString( 'Mantle', (string) json_encode( $probe ) );
		$this->assertSame( array( 'fields' => 'views' ), $client->calls[3][1][1] );
		$this->assertSame( 12155, $client->calls[3][1][0] );
	}
}
