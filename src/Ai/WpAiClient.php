<?php
/**
 * The one caller of the WordPress AI Client.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Ai;

use Masthead\Config;
use Masthead\Support\Json;

/**
 * Builds provider calls from Config. Reads and stores no credential.
 */
class WpAiClient implements AiClient {

	/**
	 * Builds a prompt builder from a user prompt.
	 *
	 * @var callable|null
	 */
	private $prompt_factory;

	/**
	 * Receives the tier and model id after each successful call.
	 *
	 * @var callable|null
	 */
	private $on_model;

	/**
	 * Constructor.
	 *
	 * @param callable|null $prompt_factory Builder factory; defaults to the core function when it exists.
	 * @param callable|null $on_model       Called with ( string $tier, string $model_id ).
	 */
	public function __construct( ?callable $prompt_factory = null, ?callable $on_model = null ) {
		$this->prompt_factory = $prompt_factory ?? ( function_exists( 'wp_ai_client_prompt' ) ? 'wp_ai_client_prompt' : null );
		$this->on_model       = $on_model;
	}

	/**
	 * Whether a provider can generate text. No network call.
	 *
	 * @return bool
	 */
	public function supported(): bool {
		if ( null === $this->prompt_factory ) {
			return false;
		}
		try {
			return true === ( $this->prompt_factory )( null )->is_supported_for_text_generation();
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Generates JSON. The caller validates the content against the schema.
	 *
	 * @param string $system System instruction.
	 * @param string $user   User prompt.
	 * @param array  $schema JSON schema.
	 * @param string $tier   'fast' or 'strong'.
	 * @return array|\WP_Error
	 */
	public function json( string $system, string $user, array $schema, string $tier ): array|\WP_Error {
		$text = $this->generate( $system, $user, $tier, $schema );
		if ( $text instanceof \WP_Error ) {
			return $text;
		}
		$decoded = Json::decode_object( $text );
		if ( null === $decoded ) {
			return new \WP_Error( 'masthead_ai_invalid', 'The AI reply was not a JSON object.' );
		}
		return $decoded;
	}

	/**
	 * Generates plain text.
	 *
	 * @param string $system System instruction.
	 * @param string $user   User prompt.
	 * @param string $tier   'fast' or 'strong'.
	 * @return string|\WP_Error
	 */
	public function text( string $system, string $user, string $tier ): string|\WP_Error {
		return $this->generate( $system, $user, $tier, null );
	}

	/**
	 * Runs one generation.
	 *
	 * @param string     $system System instruction.
	 * @param string     $user   User prompt.
	 * @param string     $tier   'fast' or 'strong'.
	 * @param array|null $schema Schema for a JSON reply, or null for text.
	 * @return string|\WP_Error
	 */
	private function generate( string $system, string $user, string $tier, ?array $schema ): string|\WP_Error {
		if ( null === $this->prompt_factory ) {
			return new \WP_Error( 'masthead_ai_invalid', 'No AI provider is available.' );
		}
		$tier   = 'fast' === $tier ? 'fast' : 'strong';
		$models = array_values( array_filter( (array) Config::get( 'ai.models.' . $tier ), 'is_string' ) );
		try {
			$builder = ( $this->prompt_factory )( $user )
				->using_system_instruction( $system )
				->using_temperature( (float) Config::get( 'ai.temperature' ) );
			$limit   = $this->max_tokens( $schema );
			if ( $limit > 0 ) {
				$builder = $builder->using_max_tokens( $limit );
			}
			if ( array() !== $models ) {
				$builder = $builder->using_model_preference( ...$models );
			}
			if ( null !== $schema ) {
				$builder = $builder->as_json_response( $schema );
			}
			$result = $builder->generate_text_result();
			if ( $result instanceof \WP_Error ) {
				return new \WP_Error( 'masthead_ai_invalid', $result->get_error_message(), array( 'cause' => $result->get_error_code() ) );
			}
			$text     = (string) $result->toText();
			$model_id = (string) $result->getModelMetadata()->getId();
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'masthead_ai_invalid', 'The AI request failed.' );
		}
		if ( null !== $this->on_model ) {
			( $this->on_model )( $tier, $model_id );
		}
		return $text;
	}

	/**
	 * Token limit for one request. ai.max_tokens is keyed by request kind, the schema's title (ideas, outline,
	 * share_copy, series_proposal); a plain-text reply or a schema without its own entry uses 'default'. A site that
	 * filters ai.max_tokens to a single number gets that number for every kind.
	 *
	 * @param array|null $schema Schema for a JSON reply, or null for text.
	 * @return int The limit, or 0 to leave the provider's default.
	 */
	private function max_tokens( ?array $schema ): int {
		$limits = Config::get( 'ai.max_tokens' );
		if ( ! is_array( $limits ) ) {
			return is_numeric( $limits ) ? (int) $limits : 0;
		}
		$kind  = is_string( $schema['title'] ?? null ) ? $schema['title'] : 'default';
		$limit = $limits[ $kind ] ?? $limits['default'] ?? 0;
		return is_numeric( $limit ) ? (int) $limit : 0;
	}
}
