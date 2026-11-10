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

	protected function setUp(): void {
		parent::setUp();
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
		return new SeriesPlanner( $this->client, new Clock() );
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
}
