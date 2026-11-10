<?php
/**
 * SuggestCommand tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Cli;

use Brain\Monkey\Functions;
use Masthead\Abilities\Ability;
use Masthead\Cli\SuggestCommand;
use Masthead\Tests\Unit\TestCase;

require_once dirname( __DIR__ ) . '/stubs/WP_CLI.php';

/**
 * SuggestCommand tests.
 */
final class SuggestCommandTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		\WP_CLI::reset();
		Functions\when( 'is_wp_error' )->alias( static fn( $v ) => $v instanceof \WP_Error );
	}

	private function ability( $result ): Ability {
		return new class( $result ) implements Ability {
			public array $inputs = array();

			public function __construct( private $result ) {}

			public function name(): string {
				return 'masthead/suggest-for-date';
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
				$this->inputs[] = $input;
				return $this->result;
			}
		};
	}

	public function test_prints_one_line_per_suggestion(): void {
		$ability = $this->ability(
			array(
				'suggestions' => array(
					array(
						'kind'  => 'draft',
						'title' => 'Swarms',
						'why'   => 'Wednesday is your Standalone slot.',
					),
					array(
						'kind'  => 'gap',
						'title' => 'Something under Security',
						'why'   => 'Nothing has been published under Security yet.',
					),
				),
			)
		);
		( new SuggestCommand( $ability ) )->__invoke( array( '2026-11-18' ), array() );
		$this->assertSame(
			array(
				"draft\tSwarms\tWednesday is your Standalone slot.",
				"gap\tSomething under Security\tNothing has been published under Security yet.",
			),
			\WP_CLI::$lines
		);
		$this->assertSame( array( array( 'date' => '2026-11-18' ) ), $ability->inputs );
		$this->assertSame( array(), \WP_CLI::$warnings );

		\WP_CLI::reset();
		$with_ideas = $this->ability(
			array(
				'suggestions' => array(),
				'ai' => array( 'error' => 'masthead_ai_budget' ),
			) 
		);
		( new SuggestCommand( $with_ideas ) )->__invoke( array( '2026-11-18' ), array( 'ideas' => true ) );
		$this->assertSame(
			array(
				array(
					'date' => '2026-11-18',
					'include_ideas' => true,
				),
			),
			$with_ideas->inputs 
		);
		$this->assertSame( array( 'AI ideas unavailable: masthead_ai_budget' ), \WP_CLI::$warnings );

		\WP_CLI::reset();
		( new SuggestCommand( $this->ability( new \WP_Error( 'masthead_invalid_date', 'Bad date.' ) ) ) )->__invoke( array( 'junk' ), array() );
		$this->assertSame( array( 'Bad date.' ), \WP_CLI::$errors );
	}
}
