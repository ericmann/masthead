<?php
/**
 * AiStatusCommand tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Cli;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Masthead\Cli\AiStatusCommand;
use Masthead\Desk\Ideas;
use Masthead\Tests\Unit\TestCase;

require_once dirname( __DIR__ ) . '/stubs/WP_CLI.php';

/**
 * AiStatusCommand tests.
 */
final class AiStatusCommandTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		\WP_CLI::reset();
		Functions\when( 'get_option' )->justReturn( array() );
		Filters\expectApplied( 'masthead_config' )->zeroOrMoreTimes()->andReturnUsing(
			static function ( $flat ) {
				$flat['ai.models.fast'] = array( 'fast-a', 'fast-b' );
				return $flat;
			}
		);
	}

	private function ideas( bool $supported, int $remaining ): Ideas {
		return new class( $supported, $remaining ) extends Ideas {
			public function __construct( private bool $supported, private int $remaining ) {}

			public function status(): array {
				return array(
					'supported' => $this->supported,
					'remaining' => $this->remaining,
				);
			}
		};
	}

	public function test_prints_support_remaining_and_cap(): void {
		( new AiStatusCommand( $this->ideas( true, 41 ) ) )->__invoke( array(), array() );
		$this->assertSame(
			array(
				'supported: yes',
				'remaining: 41',
				'cap: 300',
				'models.fast: fast-a, fast-b',
				'models.strong: claude-sonnet-5-5',
			),
			\WP_CLI::$lines
		);
	}

	public function test_prints_no_when_no_provider_supports_text_generation(): void {
		( new AiStatusCommand( $this->ideas( false, 0 ) ) )->__invoke( array(), array() );
		$this->assertSame( 'supported: no', \WP_CLI::$lines[0] );
		$this->assertSame( 'remaining: 0', \WP_CLI::$lines[1] );
	}
}
