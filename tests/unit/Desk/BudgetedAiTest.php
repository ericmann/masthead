<?php
/**
 * BudgetedAi tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Desk;

use Masthead\Ai\AiClient;
use Masthead\Desk\Budget;
use Masthead\Desk\BudgetedAi;
use Masthead\Support\Clock;
use Masthead\Tests\Unit\TestCase;

/**
 * BudgetedAi tests.
 */
final class BudgetedAiTest extends TestCase {

	private function inner(): object {
		return new class() implements AiClient {
			public int $calls = 0;
			public function supported(): bool {
				return true;
			}
			public function json( string $system, string $user, array $schema, string $tier ): array|\WP_Error {
				++$this->calls;
				return array( 'ok' => true );
			}
			public function text( string $system, string $user, string $tier ): string|\WP_Error {
				++$this->calls;
				return 'text';
			}
		};
	}

	private function budget( bool $allow ): Budget {
		return new class( new Clock(), $allow ) extends Budget {
			public int $reserved = 0;
			public function __construct( Clock $clock, private bool $allow ) {
				parent::__construct( $clock );
			}
			public function reserve(): bool {
				++$this->reserved;
				return $this->allow;
			}
		};
	}

	public function test_exhausted_budget_returns_error_without_calling_the_inner_client(): void {
		$inner  = $this->inner();
		$client = new BudgetedAi( $inner, $this->budget( false ) );
		foreach ( array( $client->json( 's', 'u', array(), 'fast' ), $client->text( 's', 'u', 'fast' ) ) as $error ) {
			$this->assertInstanceOf( \WP_Error::class, $error );
			$this->assertSame( 'masthead_ai_budget', $error->get_error_code() );
			$this->assertSame( array( 'status' => 429 ), $error->get_error_data() );
		}
		$this->assertSame( 0, $inner->calls );
	}

	public function test_each_call_reserves_exactly_once(): void {
		$inner  = $this->inner();
		$budget = $this->budget( true );
		$client = new BudgetedAi( $inner, $budget );
		$this->assertSame( array( 'ok' => true ), $client->json( 's', 'u', array(), 'fast' ) );
		$this->assertSame( 1, $budget->reserved );
		$this->assertSame( 'text', $client->text( 's', 'u', 'strong' ) );
		$this->assertSame( 2, $budget->reserved );
		$this->assertSame( 2, $inner->calls );
	}

	public function test_supported_does_not_reserve(): void {
		$budget = $this->budget( false );
		$this->assertTrue( ( new BudgetedAi( $this->inner(), $budget ) )->supported() );
		$this->assertSame( 0, $budget->reserved );
	}
}
