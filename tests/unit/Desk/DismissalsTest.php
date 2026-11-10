<?php
/**
 * Dismissals tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Desk;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Masthead\Desk\Dismissals;
use Masthead\Support\Clock;
use Masthead\Tests\Unit\TestCase;

/**
 * Dismissals tests.
 */
final class DismissalsTest extends TestCase {

	/**
	 * User meta store: user => meta.
	 *
	 * @var array<int,mixed>
	 */
	private array $meta = array();

	protected function setUp(): void {
		parent::setUp();
		$this->meta = array();
		Functions\when( 'get_user_meta' )->alias( fn( $user ) => $this->meta[ $user ] ?? '' );
		Functions\when( 'update_user_meta' )->alias(
			function ( $user, $name, $value ) {
				$this->meta[ $user ] = $value;
				return true;
			}
		);
		Filters\expectApplied( 'masthead_now' )->zeroOrMoreTimes()->andReturn( '2026-11-09 14:00:00' );
	}

	public function test_dismiss_is_idempotent(): void {
		$dismissals = new Dismissals( new Clock() );
		$dismissals->dismiss( 5, '2026-11-18', 'draft:10178' );
		$dismissals->dismiss( 5, '2026-11-18', 'draft:10178' );
		$this->assertSame( array( 'draft:10178' ), $dismissals->for_date( 5, '2026-11-18' ) );
		$this->assertSame( array( '2026-11-18' => array( 'draft:10178' ) ), $this->meta[5] );
	}

	public function test_dismissals_are_per_date(): void {
		$dismissals = new Dismissals( new Clock() );
		$dismissals->dismiss( 5, '2026-11-18', 'draft:1' );
		$dismissals->dismiss( 5, '2026-11-19', 'gap:8' );
		$dismissals->dismiss( 5, '2026-11-18', 'gap:8' );
		$this->assertSame( array( 'draft:1', 'gap:8' ), $dismissals->for_date( 5, '2026-11-18' ) );
		$this->assertSame( array( 'gap:8' ), $dismissals->for_date( 5, '2026-11-19' ) );
		$this->assertSame( array(), $dismissals->for_date( 5, '2026-11-20' ) );
		$this->assertSame( array(), $dismissals->for_date( 6, '2026-11-18' ), 'another user sees nothing' );
	}

	public function test_past_dates_are_pruned_on_write(): void {
		$this->meta[5] = array(
			'2026-11-01' => array( 'draft:1' ),
			'2026-11-08' => array( 'draft:2' ),
			'2026-11-09' => array( 'draft:3' ),
			'bad'        => 'junk',
		);
		( new Dismissals( new Clock() ) )->dismiss( 5, '2026-11-10', 'gap:8' );
		$this->assertSame(
			array(
				'2026-11-09' => array( 'draft:3' ),
				'2026-11-10' => array( 'gap:8' ),
			),
			$this->meta[5]
		);
	}
}
