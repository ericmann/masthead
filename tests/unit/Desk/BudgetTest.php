<?php
/**
 * Budget tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Desk;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Masthead\Desk\Budget;
use Masthead\Support\Clock;
use Masthead\Tests\Unit\TestCase;

/**
 * Budget tests.
 */
final class BudgetTest extends TestCase {

	private mixed $usage  = false;
	private int $writes   = 0;
	private int $cap      = 2;
	private string $now   = '2026-11-09 14:00:00';

	protected function setUp(): void {
		parent::setUp();
		$this->usage  = false;
		$this->writes = 0;
		$this->cap    = 2;
		$this->now    = '2026-11-09 14:00:00';
		Functions\when( 'get_option' )->alias(
			fn( $name, $fallback = false ) => Budget::OPTION === $name ? ( false === $this->usage ? $fallback : $this->usage ) : array()
		);
		Functions\when( 'wp_date' )->alias(
			static fn( $format, $timestamp, $zone ) => ( new \DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $zone )->format( $format )
		);
		Functions\when( 'update_option' )->alias(
			function ( $name, $value ) {
				$this->usage = $value;
				++$this->writes;
				return true;
			}
		);
		Filters\expectApplied( 'masthead_now' )->zeroOrMoreTimes()->andReturnUsing( fn() => $this->now );
		Filters\expectApplied( 'masthead_config' )->zeroOrMoreTimes()->andReturnUsing(
			function ( $flat ) {
				$flat['ai.monthly_request_cap'] = $this->cap;
				return $flat;
			}
		);
	}

	public function test_reserve_increments_until_the_cap(): void {
		$budget = new Budget( new Clock() );
		$this->assertSame( 2, $budget->remaining() );
		$this->assertTrue( $budget->reserve() );
		$this->assertTrue( $budget->reserve() );
		$this->assertFalse( $budget->reserve() );
		$this->assertSame( 0, $budget->remaining() );
		$this->assertSame(
			array(
				'month' => '2026-11',
				'requests' => 2,
				'models' => array(),
			),
			$this->usage 
		);
	}

	public function test_cap_of_zero_always_refuses_and_writes_nothing(): void {
		$this->cap = 0;
		$budget    = new Budget( new Clock() );
		$this->assertFalse( $budget->reserve() );
		$this->assertSame( 0, $budget->remaining() );
		$this->assertSame( 0, $this->writes );
	}

	public function test_new_month_resets_the_count(): void {
		$budget = new Budget( new Clock() );
		$budget->reserve();
		$budget->reserve();
		$budget->note_model( 'fast', 'm1' );
		$this->now = '2026-12-01 09:00:00';
		$this->assertSame( 2, $budget->remaining() );
		$this->assertTrue( $budget->reserve() );
		$this->assertSame( '2026-12', $this->usage['month'] );
		$this->assertSame( 1, $this->usage['requests'] );
		$this->assertSame( array( 'fast' => 'm1' ), $budget->models() );
	}

	public function test_remaining_never_goes_negative(): void {
		$this->usage = array(
			'month' => '2026-11',
			'requests' => 9,
		);
		$this->assertSame( 0, ( new Budget( new Clock() ) )->remaining() );
		$this->usage = array(
			'month' => '2026-11',
			'requests' => -5,
		);
		$this->assertSame( 2, ( new Budget( new Clock() ) )->remaining() );
	}

	public function test_cap_comes_from_config(): void {
		$this->cap = 5;
		$budget    = new Budget( new Clock() );
		$this->assertSame( 5, $budget->remaining() );
		$this->cap = 1;
		$budget->reserve();
		$this->assertFalse( $budget->reserve() );
	}

	public function test_note_model_stores_the_last_model_per_tier_once(): void {
		$budget = new Budget( new Clock() );
		$budget->note_model( 'fast', 'a' );
		$budget->note_model( 'fast', 'a' );
		$budget->note_model( 'strong', 'b' );
		$this->assertSame(
			array(
				'fast' => 'a',
				'strong' => 'b',
			),
			$budget->models() 
		);
		$this->assertSame( 2, $this->writes );
	}
}
