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

	public function test_json_passes_system_models_temperature_tokens_and_schema_to_the_builder(): void {
		$builder         = new StubBuilder();
		$builder->result = $this->result( '{"message":"Hi"}' );
		$schema          = array( 'title' => 'share_copy' );
		$data            = $this->client( $builder )->json( 'SYS', 'USER', $schema, 'fast' );
		$this->assertSame( array( 'message' => 'Hi' ), $data );
		$this->assertSame( 'USER', $builder->prompt );
		$this->assertSame( array( 'SYS' ), $builder->args( 'using_system_instruction' ) );
		$this->assertSame( array( 'claude-haiku-5-5' ), $builder->args( 'using_model_preference' ) );
		$this->assertSame( array( 0.4 ), $builder->args( 'using_temperature' ) );
		$this->assertSame( array( 1200 ), $builder->args( 'using_max_tokens' ) );
		$this->assertSame( array( $schema ), $builder->args( 'as_json_response' ) );
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
