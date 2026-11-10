<?php
/**
 * PlanCommand tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Cli;

use Brain\Monkey\Functions;
use Masthead\Abilities\Ability;
use Masthead\Cli\PlanCommand;
use Masthead\Support\Clock;
use Masthead\Tests\Unit\TestCase;

require_once dirname( __DIR__ ) . '/stubs/WP_CLI.php';

/**
 * PlanCommand tests.
 */
final class PlanCommandTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		\WP_CLI::reset();
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'is_wp_error' )->alias( static fn( $v ) => $v instanceof \WP_Error );
		Functions\when( 'wp_date' )->alias(
			static fn( $format, $timestamp, $zone ) => ( new \DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $zone )->format( $format )
		);
	}

	private function plan(): array {
		return array(
			'days'        => array(
				array(
					'date'  => '2026-11-09',
					'lane'  => array( 'slug' => 'business' ),
					'posts' => array(
						array(
							'id'     => 12155,
							'status' => 'publish',
							'title'  => 'The Mantle',
						),
						array(
							'id'     => 10193,
							'status' => 'publish',
							'title'  => 'DailyMedToday',
						),
					),
					'open'  => false,
				),
				array(
					'date'  => '2026-11-10',
					'lane'  => array( 'slug' => 'technology' ),
					'posts' => array(),
					'open'  => true,
				),
			),
			'unscheduled' => array(),
			'truncated'   => false,
		);
	}

	private function command( $result ): PlanCommand {
		$ability = new class( $result ) implements Ability {
			public function __construct( private $result ) {}

			public function name(): string {
				return 'masthead/get-plan';
			}

			public function label(): string {
				return '';
			}

			public function description(): string {
				return '';
			}

			public function input_schema(): array {
				return array();
			}

			public function output_schema(): array {
				return array();
			}

			public function annotations(): array {
				return array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				);
			}

			public function permission( $input ) {
				return false;
			}

			public function execute( $input ) {
				return $this->result;
			}
		};
		return new PlanCommand( $ability, new Clock() );
	}

	public function test_rows_have_one_row_per_post_and_one_per_open_day(): void {
		$rows = $this->command( $this->plan() )->rows( $this->plan() );
		$this->assertCount( 3, $rows );
		$this->assertSame( array( '2026-11-09', 'Mon', 'business', 'publish', 12155, 'The Mantle' ), array_values( $rows[0] ) );
		$this->assertSame( 10193, $rows[1]['id'] );
		$this->assertSame( array( '2026-11-10', 'Tue', 'technology', 'open', '', '' ), array_values( $rows[2] ) );
		$this->assertSame( array( 'date', 'weekday', 'lane', 'status', 'id', 'title' ), array_keys( $rows[0] ) );
	}

	public function test_json_format_prints_ability_output_unchanged(): void {
		$this->command( $this->plan() )->__invoke( array( '2026-11-09', '2026-11-10' ), array( 'format' => 'json' ) );
		$this->assertSame( array( json_encode( $this->plan() ) ), \WP_CLI::$lines );

		\WP_CLI::reset();
		$this->command( $this->plan() )->__invoke( array( '2026-11-09', '2026-11-10' ), array() );
		$this->assertSame( 'table', \WP_CLI::$lines[0][0] );
		$this->assertCount( 3, \WP_CLI::$lines[0][1] );
	}

	public function test_ability_error_is_reported_as_cli_error(): void {
		$this->command( new \WP_Error( 'masthead_range_too_long', 'Too long.' ) )->__invoke( array( '2026-01-01', '2027-01-01' ), array() );
		$this->assertSame( array( 'Too long.' ), \WP_CLI::$errors );
		$this->assertSame( array(), \WP_CLI::$lines );
	}
}
