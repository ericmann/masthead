<?php
/**
 * WpAiClient tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Ai;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Masthead\Ai\Schemas;
use Masthead\Ai\WpAiClient;
use Masthead\Tests\Unit\TestCase;

/**
 * WpAiClient tests.
 */
final class WpAiClientTest extends TestCase {

	private array $overrides = array();

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_option' )->justReturn( array() );
		Filters\expectApplied( 'masthead_config' )->zeroOrMoreTimes()->andReturnUsing(
			function ( $flat ) {
				return array_merge( $flat, $this->overrides );
			}
		);
	}

	// phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- mirrors the AI Client's camelCase result API.
	private function result( string $text, string $model = 'claude-haiku-5-5' ): object {
		return new class( $text, $model ) {
			public function __construct( private string $text, private string $model ) {}
			public function toText(): string {
				return $this->text;
			}
			public function getModelMetadata(): object {
				$model = $this->model;
				return new class( $model ) {
					public function __construct( private string $id ) {}
					public function getId(): string {
						return $this->id;
					}
				};
			}
		};
	}

	// phpcs:enable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid

	private function client( StubBuilder $builder, ?callable $on_model = null ): WpAiClient {
		return new WpAiClient(
			function ( $prompt ) use ( $builder ) {
				$builder->prompt = null === $prompt ? null : (string) $prompt;
				return $builder;
			},
			$on_model
		);
	}

	public function test_unsupported_when_the_core_function_is_missing(): void {
		$this->assertFalse( ( new WpAiClient() )->supported() );
		$this->assertInstanceOf( \WP_Error::class, ( new WpAiClient() )->text( 's', 'u', 'fast' ) );
		$builder = new StubBuilder();
		$this->assertTrue( $this->client( $builder )->supported() );
		$builder->supported = false;
		$this->assertFalse( $this->client( $builder )->supported() );
	}

	public function test_supported_asks_for_a_system_instruction_and_a_json_schema(): void {
		$builder = new StubBuilder();
		$this->assertTrue( $this->client( $builder )->supported() );
		$this->assertNull( $builder->prompt );
		$this->assertSame( array( 'Masthead' ), $builder->args( 'using_system_instruction' ) );
		$this->assertSame( array( array( 'type' => 'object' ) ), $builder->args( 'as_json_response' ) );
		$this->assertSame( 'is_supported_for_text_generation', $builder->names()[ count( $builder->names() ) - 1 ] );
	}

	public function test_a_throwing_support_check_is_unsupported(): void {
		$client = new WpAiClient(
			static function () {
				throw new \RuntimeException( 'registry down' );
			}
		);
		$this->assertFalse( $client->supported() );
	}

	public function test_json_passes_system_models_tokens_and_the_wire_schema_to_the_builder(): void {
		$builder         = new StubBuilder();
		$builder->result = $this->result( '{"message":"Hi"}' );
		$schema          = Schemas::share_copy();
		$data            = $this->client( $builder )->json( 'SYS', 'USER', $schema, 'fast' );
		$this->assertSame( array( 'message' => 'Hi' ), $data );
		$this->assertSame( 'USER', $builder->prompt );
		$this->assertSame( array( 'SYS' ), $builder->args( 'using_system_instruction' ) );
		$this->assertSame( array( 'claude-haiku-5-5' ), $builder->args( 'using_model_preference' ) );
		$this->assertNotContains( 'using_temperature', $builder->names() );
		$this->assertSame( array( 1200 ), $builder->args( 'using_max_tokens' ) );
		$this->assertSame( array( Schemas::wire( $schema ) ), $builder->args( 'as_json_response' ) );
		$this->assertArrayNotHasKey( 'minLength', $builder->args( 'as_json_response' )[0]['properties']['message'] );
	}

	public function test_a_filtered_temperature_is_sent(): void {
		$this->overrides = array( 'ai.temperature' => 0.7 );
		$builder         = new StubBuilder();
		$builder->result = $this->result( '{}' );
		$this->client( $builder )->json( 's', 'u', Schemas::outline(), 'strong' );
		$this->assertSame( array( 0.7 ), $builder->args( 'using_temperature' ) );
	}

	public function test_max_tokens_follow_the_request_kind(): void {
		$schemas = array(
			array( Schemas::ideas( array( 'technology' ), 3 ), 1200 ),
			array( Schemas::outline(), 1200 ),
			array( Schemas::share_copy(), 1200 ),
			array( Schemas::series_proposal( array( 'technology' ), 12 ), 4000 ),
			array( array( 'title' => 'not_a_kind' ), 1200 ),
			array( array(), 1200 ),
		);
		foreach ( $schemas as $pair ) {
			$builder         = new StubBuilder();
			$builder->result = $this->result( '{}' );
			$this->client( $builder )->json( 's', 'u', $pair[0], 'strong' );
			$this->assertSame( array( $pair[1] ), $builder->args( 'using_max_tokens' ), (string) ( $pair[0]['title'] ?? 'untitled' ) );
		}
		$builder         = new StubBuilder();
		$builder->result = $this->result( 'plain' );
		$this->client( $builder )->text( 's', 'u', 'fast' );
		$this->assertSame( array( 1200 ), $builder->args( 'using_max_tokens' ) );
	}

	public function test_a_single_filtered_max_tokens_applies_to_every_kind(): void {
		$this->overrides = array( 'ai.max_tokens' => 900 );
		foreach ( array( Schemas::series_proposal( array( 'technology' ), 12 ), Schemas::ideas( array( 'technology' ), 3 ) ) as $schema ) {
			$builder         = new StubBuilder();
			$builder->result = $this->result( '{}' );
			$this->client( $builder )->json( 's', 'u', $schema, 'strong' );
			$this->assertSame( array( 900 ), $builder->args( 'using_max_tokens' ), $schema['title'] );
		}
	}

	public function test_a_kind_without_a_positive_limit_leaves_the_provider_default(): void {
		$this->overrides = array( 'ai.max_tokens' => array( 'ideas' => 1200 ) );
		$builder         = new StubBuilder();
		$builder->result = $this->result( '{}' );
		$this->client( $builder )->json( 's', 'u', Schemas::series_proposal( array( 'technology' ), 12 ), 'strong' );
		$this->assertNotContains( 'using_max_tokens', $builder->names() );
		$this->assertNotContains( 'using_temperature', $builder->names() );
	}

	public function test_tier_selects_the_model_list_from_config(): void {
		$this->overrides = array(
			'ai.models.fast'   => array( 'f1', 'f2' ),
			'ai.models.strong' => array( 's1' ),
		);
		$seen            = array();
		$builder         = new StubBuilder();
		$builder->result = $this->result( 'plain', 'f1' );
		$client          = $this->client(
			$builder,
			function ( string $tier, string $model ) use ( &$seen ) {
				$seen[] = array( $tier, $model );
			}
		);
		$this->assertSame( 'plain', $client->text( 's', 'u', 'fast' ) );
		$this->assertSame( array( 'f1', 'f2' ), $builder->args( 'using_model_preference' ) );
		$this->assertNotContains( 'as_json_response', $builder->names() );
		$builder->calls = array();
		$client->text( 's', 'u', 'strong' );
		$this->assertSame( array( 's1' ), $builder->args( 'using_model_preference' ) );
		$this->assertSame( array( array( 'fast', 'f1' ), array( 'strong', 'f1' ) ), $seen );
	}

	public function test_non_json_text_becomes_wp_error(): void {
		foreach ( array( 'Sure! Here you go', '[1,2]', '```json {} ```', '' ) as $text ) {
			$builder         = new StubBuilder();
			$builder->result = $this->result( $text );
			$error           = $this->client( $builder )->json( 's', 'u', array(), 'fast' );
			$this->assertInstanceOf( \WP_Error::class, $error, $text );
			$this->assertSame( 'masthead_ai_invalid', $error->get_error_code() );
		}
	}

	public function test_provider_error_becomes_wp_error(): void {
		$builder         = new StubBuilder();
		$builder->result = new \WP_Error( 'prompt_network_error', 'Down.' );
		$called          = false;
		$error           = $this->client(
			$builder,
			function () use ( &$called ) {
				$called = true;
			} 
		)->json( 's', 'u', array(), 'fast' );
		$this->assertSame( 'masthead_ai_invalid', $error->get_error_code() );
		$this->assertSame( array( 'cause' => 'prompt_network_error' ), $error->get_error_data() );
		$this->assertFalse( $called );

		$builder->result = new \RuntimeException( 'boom' );
		$this->assertSame( 'masthead_ai_invalid', $this->client( $builder )->text( 's', 'u', 'fast' )->get_error_code() );
	}
}
