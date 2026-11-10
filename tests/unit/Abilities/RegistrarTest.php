<?php
/**
 * Registrar tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Abilities;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Masthead\Abilities\Ability;
use Masthead\Abilities\Registrar;
use Masthead\Tests\Unit\TestCase;

/**
 * Registrar tests.
 */
final class RegistrarTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_option' )->alias(
			static fn( $name, $fallback = false ) => 'masthead_settings' === $name ? array() : $fallback
		);
	}

	private function ability( string $name, bool $readonly ): Ability {
		return new class( $name, $readonly ) implements Ability {
			public function __construct( private string $name, private bool $readonly ) {}

			public function name(): string {
				return $this->name;
			}

			public function label(): string {
				return 'Label';
			}

			public function description(): string {
				return 'Description';
			}

			public function input_schema(): array {
				return array( 'type' => 'object' );
			}

			public function output_schema(): array {
				return array( 'type' => 'object' );
			}

			public function annotations(): array {
				return array(
					'readonly'    => $this->readonly,
					'destructive' => false,
					'idempotent'  => false,
				);
			}

			public function permission( $input ) {
				return true;
			}

			public function execute( $input ) {
				return array();
			}
		};
	}

	/**
	 * Registers and returns the args by ability name.
	 *
	 * @param Registrar $registrar Registrar.
	 * @return array<string,array>
	 */
	private function registered( Registrar $registrar ): array {
		$seen = array();
		Functions\when( 'wp_register_ability' )->alias(
			static function ( $name, $args ) use ( &$seen ) {
				$seen[ $name ] = $args;
			}
		);
		$registrar->register();
		return $seen;
	}

	public function test_registers_category_and_every_ability(): void {
		Functions\expect( 'wp_register_ability_category' )->once()->with( 'masthead', \Mockery::type( 'array' ) );
		$a         = $this->ability( 'masthead/get-plan', true );
		$b         = $this->ability( 'masthead/reschedule', false );
		$registrar = new Registrar( $a, $b );
		$registrar->register_category();
		$seen = $this->registered( $registrar );
		$this->assertSame( array( 'masthead/get-plan', 'masthead/reschedule' ), array_keys( $seen ) );
		$args = $seen['masthead/get-plan'];
		$this->assertSame( 'masthead', $args['category'] );
		$this->assertSame( array( $a, 'execute' ), $args['execute_callback'] );
		$this->assertSame( array( $a, 'permission' ), $args['permission_callback'] );
		$this->assertTrue( $args['meta']['show_in_rest'] );
		$this->assertTrue( $args['meta']['annotations']['readonly'] );
		$this->assertSame( array( 'type' => 'object' ), $args['input_schema'] );
		$this->assertSame( $a, $registrar->get( 'masthead/get-plan' ) );
		$this->assertNull( $registrar->get( 'masthead/missing' ) );
	}

	public function test_share_post_exposure_follows_expose_share(): void {
		$registrar = new Registrar( $this->ability( 'masthead/share-post', false ) );
		$seen      = $this->registered( $registrar );
		$this->assertFalse( $seen['masthead/share-post']['meta']['public'] );
		$this->assertTrue( $seen['masthead/share-post']['meta']['show_in_rest'], 'the admin UI still runs it' );

		Filters\expectApplied( 'masthead_config' )->zeroOrMoreTimes()->andReturnUsing(
			static function ( $flat ) {
				$flat['abilities.expose_share'] = true;
				$flat['abilities.expose_mcp']   = false;
				return $flat;
			}
		);
		$seen = $this->registered( $registrar );
		$this->assertTrue( $seen['masthead/share-post']['meta']['public'] );
	}

	public function test_other_abilities_follow_expose_mcp(): void {
		$registrar = new Registrar( $this->ability( 'masthead/get-plan', true ) );
		$this->assertTrue( $this->registered( $registrar )['masthead/get-plan']['meta']['public'] );
		Filters\expectApplied( 'masthead_config' )->zeroOrMoreTimes()->andReturnUsing(
			static function ( $flat ) {
				$flat['abilities.expose_mcp']   = false;
				$flat['abilities.expose_share'] = true;
				return $flat;
			}
		);
		$this->assertFalse( $this->registered( $registrar )['masthead/get-plan']['meta']['public'] );
	}

	public function test_describe_reports_readonly_flag(): void {
		$registrar = new Registrar( $this->ability( 'masthead/get-plan', true ), $this->ability( 'masthead/reschedule', false ) );
		$this->assertSame(
			array(
				'masthead/get-plan'   => array( 'readonly' => true ),
				'masthead/reschedule' => array( 'readonly' => false ),
			),
			$registrar->describe()
		);
	}
}
