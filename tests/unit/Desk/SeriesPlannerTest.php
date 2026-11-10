<?php
/**
 * SeriesPlanner tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Desk;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Masthead\Ai\AiClient;
use Masthead\Desk\SeriesPlanner;
use Masthead\Plan\SeriesSource;
use Masthead\Plan\Slots;
use Masthead\Support\Clock;
use Masthead\Tests\Unit\TestCase;

/**
 * SeriesPlanner tests.
 */
final class SeriesPlannerTest extends TestCase {

	/**
	 * The last stub client.
	 *
	 * @var object
	 */
	private $client;

	/**
	 * Series stub.
	 *
	 * @var SeriesSource|null
	 */
	private ?SeriesSource $series = null;

	/**
	 * Recorded writes.
	 *
	 * @var array
	 */
	private array $log = array();

	protected function setUp(): void {
		parent::setUp();
		$this->series = null;
		Functions\when( 'get_option' )->alias( static fn( $name, $fallback = false ) => 'masthead_settings' === $name ? array() : $fallback );
		Functions\when( 'get_terms' )->justReturn(
			array(
				(object) array(
					'term_id' => 1,
					'slug' => 'technology',
					'name' => 'Technology',
				),
				(object) array(
					'term_id' => 2,
					'slug' => 'security',
					'name' => 'Security',
				),
				(object) array(
					'term_id' => 3,
					'slug' => 'journal',
					'name' => 'Journal',
				),
				(object) array(
					'term_id' => 4,
					'slug' => 'orphan',
					'name' => 'Orphan',
				),
			)
		);
		Filters\expectApplied( 'masthead_now' )->zeroOrMoreTimes()->andReturn( '2026-11-09 14:00:00' );
	}

	private function proposal( string $category = 'security', int $every = 1, int $parts = 4 ): array {
		$list = array();
		for ( $i = 1; $i <= $parts; $i++ ) {
			$list[] = array(
				'title'   => 'Part ' . $i,
				'outline' => array( 'Point ' . $i ),
			);
		}
		return array(
			'name'        => 'Locking things down',
			'category'    => $category,
			'every_weeks' => $every,
			'parts'       => $list,
		);
	}

	private function planner( mixed $reply ): SeriesPlanner {
		$this->client = new class( $reply ) implements AiClient {
			public int $calls = 0;
			public array $seen = array();
			public function __construct( private mixed $reply ) {}
			public function supported(): bool {
				return true;
			}
			public function json( string $system, string $user, array $schema, string $tier ): array|\WP_Error {
				++$this->calls;
				$this->seen = array( $system, $user, $schema, $tier );
				return $this->reply;
			}
			public function text( string $system, string $user, string $tier ): string|\WP_Error {
				return '';
			}
		};
		return new SeriesPlanner( $this->client, new Clock(), new Slots( $this->series() ), $this->series() );
	}

	public function test_security_series_starting_2027_01_01_lands_on_thursdays_from_2027_01_07(): void {
		$result = $this->planner( $this->proposal() )->propose( 'a 4-part security series', '2027-01-01' );
		$this->assertSame( array( '2027-01-07', '2027-01-14', '2027-01-21', '2027-01-28' ), array_column( $result['parts'], 'date' ) );
		$this->assertSame( 'strong', $this->client->seen[3] );
		$this->assertSame( 'Locking things down', $result['name'] );
		$this->assertSame( 'security', $result['category'] );
		$this->assertSame(
			array(
				'every_weeks' => 1,
				'weekday'     => 4,
				'time'        => '07:30',
			),
			$result['cadence']
		);
		$this->assertSame( 'technology', $result['lane']['slug'] );
		$this->assertSame( array( 'technology', 'security' ), $result['lane']['categories'] );
		$this->assertSame( array( 'Part 1', 'Part 2', 'Part 3', 'Part 4' ), array_column( $result['parts'], 'title' ) );
		$this->assertSame( array( 'Point 1' ), $result['parts'][0]['outline'] );
		$this->assertStringContainsString( '<untrusted-post-data>a 4-part security series</untrusted-post-data>', $this->client->seen[1] );
	}

	public function test_dates_follow_every_weeks(): void {
		$result = $this->planner( $this->proposal( 'security', 2, 3 ) )->propose( 'x', '2027-01-07' );
		$this->assertSame( array( '2027-01-07', '2027-01-21', '2027-02-04' ), array_column( $result['parts'], 'date' ) );
		$this->assertSame( 2, $result['cadence']['every_weeks'] );
	}

	public function test_category_without_a_lane_starts_on_the_start_date(): void {
		$result = $this->planner( $this->proposal( 'orphan', 1, 2 ) )->propose( 'x', '2027-01-01' );
		$this->assertSame( array( '2027-01-01', '2027-01-08' ), array_column( $result['parts'], 'date' ) );
		$this->assertSame( 5, $result['cadence']['weekday'] );
		$this->assertSame( 'journal', $result['lane']['slug'] );
	}

	public function test_default_start_is_the_day_after_today(): void {
		$result = $this->planner( $this->proposal( 'orphan', 1, 1 ) )->propose( 'x', null );
		$this->assertSame( '2026-11-10', $result['parts'][0]['date'] );
		$today = $this->planner( $this->proposal( 'orphan', 1, 1 ) )->propose( 'x', '2026-11-09' );
		$this->assertSame( '2026-11-09', $today['parts'][0]['date'] );
	}

	public function test_invalid_reply_returns_wp_error(): void {
		$extra         = $this->proposal();
		$extra['when'] = 'Thursday';
		$replies       = array(
			$extra,
			$this->proposal( 'sports' ),
			$this->proposal( 'security', 0 ),
			$this->proposal( 'security', 1, 13 ),
			$this->proposal( 'security', 1, 0 ),
			array(),
		);
		foreach ( $replies as $reply ) {
			$result = $this->planner( $reply )->propose( 'x', '2027-01-01' );
			$this->assertInstanceOf( \WP_Error::class, $result );
			$this->assertSame( 'masthead_ai_invalid', $result->get_error_code() );
		}
		$error = new \WP_Error( 'masthead_ai_budget', 'Limit.' );
		$this->assertSame( $error, $this->planner( $error )->propose( 'x', '2027-01-01' ) );
	}

	public function test_past_start_is_refused_before_any_ai_call(): void {
		foreach ( array( '2026-11-08', '2026-02-30', 'soon' ) as $start ) {
			$planner = $this->planner( $this->proposal() );
			$result  = $planner->propose( 'x', $start );
			$this->assertSame( 'masthead_invalid_date', $result->get_error_code() );
			$this->assertSame( array( 'status' => 400 ), $result->get_error_data() );
			$this->assertSame( 0, $this->client->calls );
		}
	}

	public function test_empty_prompt_is_refused_before_any_ai_call(): void {
		$planner = $this->planner( $this->proposal() );
		$result  = $planner->propose( "  \n", '2027-01-01' );
		$this->assertSame( 'masthead_invalid_input', $result->get_error_code() );
		$this->assertSame( array( 'status' => 400 ), $result->get_error_data() );
		$this->assertSame( 0, $this->client->calls );
	}

	/**
	 * A series source that records writes instead of touching WordPress.
	 *
	 * @return SeriesSource
	 */
	private function series(): SeriesSource {
		if ( null === $this->series ) {
			$this->series = new class() extends SeriesSource {
				public array $parts_set = array();
				public function __construct() {}
				public function taxonomy(): string {
					return 'series';
				}
				public function total_meta(): string {
					return 'masthead_total_parts';
				}
				public function set_part( int $post_id, int $term_id, int $part ): void {
					$this->parts_set[] = array( $post_id, $term_id, $part );
				}
			};
		}
		return $this->series;
	}

	/**
	 * Fake WordPress writes for accept().
	 *
	 * @param int $fail_on Insert call number that fails, or zero for none.
	 * @return void
	 */
	private function writes( int $fail_on = 0 ): void {
		$this->log = array(
			'terms'   => array(),
			'inserts' => array(),
			'deleted' => array(),
			'meta'    => array(),
			'removed' => array(),
		);
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( 'get_term_by' )->alias( static fn( $field, $value ) => in_array( $value, array( 'security', 'technology' ), true ) ? (object) array( 'term_id' => 2 ) : false );
		Functions\when( 'term_exists' )->alias( static fn( $name ) => 'Taken' === $name ? array( 'term_id' => 1 ) : 0 );
		Functions\when( 'wp_insert_term' )->alias(
			function ( $name ) {
				$this->log['terms'][] = $name;
				return array( 'term_id' => 77 );
			}
		);
		Functions\when( 'wp_delete_term' )->alias(
			function ( $term_id ) {
				$this->log['removed'][] = $term_id;
				return true;
			}
		);
		Functions\when( 'update_term_meta' )->alias(
			function ( $term_id, $key, $value ) {
				$this->log['meta'][ $key ] = $value;
				return true;
			}
		);
		Functions\when( 'wp_insert_post' )->alias(
			function ( $postarr ) use ( $fail_on ) {
				$this->log['inserts'][] = $postarr;
				$count                  = count( $this->log['inserts'] );
				return $count === $fail_on ? new \WP_Error( 'insert_failed', 'No.' ) : 100 + $count;
			}
		);
		Functions\when( 'wp_delete_post' )->alias(
			function ( $post_id ) {
				$this->log['deleted'][] = $post_id;
				return true;
			}
		);
		Functions\when( 'is_wp_error' )->alias( static fn( $value ) => $value instanceof \WP_Error );
		Functions\when( 'update_post_meta' )->justReturn( true );
	}

	private function parts( int $count = 4 ): array {
		$parts = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$parts[] = array(
				'date'    => \Masthead\Support\Dates::add_days( '2027-01-07', $i * 7 ),
				'title'   => 'Part ' . ( $i + 1 ),
				'outline' => array( 'Point' ),
			);
		}
		return $parts;
	}

	public function test_accept_creates_the_term_cadence_and_numbered_drafts(): void {
		$this->writes();
		$cadence = array(
			'every_weeks' => 1,
			'weekday'     => 4,
			'time'        => '07:30',
		);
		$result  = $this->planner( array() )->accept( 'Locking things down', $this->parts(), $cadence, 'security' );
		$this->assertSame(
			array(
				'term_id'  => 77,
				'post_ids' => array( 101, 102, 103, 104 ),
			),
			$result
		);
		$this->assertSame( array( 'Locking things down' ), $this->log['terms'] );
		$this->assertSame( 4, $this->log['meta']['masthead_total_parts'] );
		$this->assertSame( $cadence, json_decode( $this->log['meta']['masthead_cadence'], true ) );
		$this->assertSame( '2027-01-07 07:30:00', $this->log['inserts'][0]['post_date'] );
		$this->assertSame( array( array( 101, 77, 1 ), array( 102, 77, 2 ), array( 103, 77, 3 ), array( 104, 77, 4 ) ), $this->series()->parts_set );
	}

	public function test_accept_validates_every_part_before_writing(): void {
		$this->writes();
		$bad_date = $this->parts();
		$bad_date[2]['date'] = '2027-02-30';
		$past     = $this->parts();
		$past[0]['date'] = '2026-11-08';
		$no_title = $this->parts();
		$no_title[3]['title'] = '  ';
		$cases    = array(
			'masthead_invalid_date'     => array( 'Name', $bad_date, null, 'security' ),
			'masthead_invalid_date#2'   => array( 'Name', $past, null, 'security' ),
			'masthead_invalid_input'    => array( 'Name', $no_title, null, 'security' ),
			'masthead_invalid_input#2'  => array( '  ', $this->parts(), null, 'security' ),
			'masthead_invalid_input#3'  => array(
				'Name',
				$this->parts(),
				array(
					'every_weeks' => 0,
					'weekday' => null,
					'time' => '07:30',
				),
				'security',
			),
			'masthead_unknown_category' => array( 'Name', $this->parts(), null, 'sports' ),
			'masthead_series_exists'    => array( 'Taken', $this->parts(), null, 'security' ),
			'masthead_invalid_input#4'  => array( 'Name', array(), null, 'security' ),
		);
		foreach ( $cases as $code => $args ) {
			$result = $this->planner( array() )->accept( ...$args );
			$this->assertInstanceOf( \WP_Error::class, $result, $code );
			$this->assertSame( explode( '#', $code )[0], $result->get_error_code(), $code );
		}
		$this->assertSame( 409, $this->planner( array() )->accept( 'Taken', $this->parts(), null, 'security' )->get_error_data()['status'] );
		$this->assertSame( array(), $this->log['terms'] );
		$this->assertSame( array(), $this->log['inserts'] );
	}

	public function test_accept_uses_the_first_lane_category_and_refuses_an_open_lane(): void {
		$this->writes();
		$thursday = $this->planner( array() )->accept( 'Name', array_slice( $this->parts(), 0, 1 ), null, null );
		$this->assertSame(
			array(
				'term_id' => 77,
				'post_ids' => array( 101 ),
			),
			$thursday 
		);
		$this->assertArrayNotHasKey( 'masthead_cadence', $this->log['meta'] );
		$this->writes();
		$wednesday = array(
			array(
				'date' => '2027-01-06',
				'title' => 'Open',
			),
		);
		$result    = $this->planner( array() )->accept( 'Name', $wednesday, null, null );
		$this->assertSame( 'masthead_invalid_input', $result->get_error_code() );
		$this->assertSame( array(), $this->log['terms'] );
	}

	public function test_accept_rolls_back_drafts_and_term_when_a_part_fails(): void {
		$this->writes( 3 );
		$result = $this->planner( array() )->accept( 'Name', $this->parts(), null, 'security' );
		$this->assertSame( 'insert_failed', $result->get_error_code() );
		$this->assertSame( array( 101, 102 ), $this->log['deleted'] );
		$this->assertSame( array( 77 ), $this->log['removed'] );
		$this->assertCount( 3, $this->log['inserts'] );
	}

	public function test_accept_rejects_more_parts_than_the_maximum(): void {
		$this->writes();
		$result = $this->planner( array() )->accept( 'Name', $this->parts( 13 ), null, 'security' );
		$this->assertSame( 'masthead_invalid_input', $result->get_error_code() );
		$this->assertSame( array(), $this->log['terms'] );
		$this->assertCount( 12, $this->parts( 12 ) );
		$this->assertNotWPError( $this->planner( array() )->accept( 'Name', $this->parts( 12 ), null, 'security' ) );
	}

	private function assertNotWPError( mixed $value ): void {
		$this->assertNotInstanceOf( \WP_Error::class, $value );
	}
}
