<?php
/**
 * Fake AI client for tests and e2e. Never packaged.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Support;

use Masthead\Ai\AiClient;

/**
 * Returns fixtures from tests/fixtures/ai.
 */
class FakeAiClient implements AiClient {

	/**
	 * Whether every call fails.
	 *
	 * @var bool
	 */
	private bool $fail;

	/**
	 * Fixture name returned by every json() call, or null for the schema's own.
	 *
	 * @var string|null
	 */
	private ?string $alternative;

	/**
	 * Constructor.
	 *
	 * @param bool        $fail        Make every call return a WP_Error.
	 * @param string|null $alternative Name of a fixture to return from json() instead.
	 */
	public function __construct( bool $fail = false, ?string $alternative = null ) {
		$this->fail        = $fail;
		$this->alternative = $alternative;
	}

	/**
	 * Always supported.
	 *
	 * @return bool
	 */
	public function supported(): bool {
		return true;
	}

	/**
	 * Returns the fixture for the schema title.
	 *
	 * @param string $system System instruction.
	 * @param string $user   User prompt.
	 * @param array  $schema JSON schema.
	 * @param string $tier   Tier.
	 * @return array|\WP_Error
	 */
	public function json( string $system, string $user, array $schema, string $tier ): array|\WP_Error {
		if ( $this->fail ) {
			return new \WP_Error( 'masthead_ai_invalid', 'Fake failure.' );
		}
		$name = $this->alternative ?? (string) ( $schema['title'] ?? '' );
		$path = __DIR__ . '/../fixtures/ai/' . basename( $name ) . '.json';
		$text = is_readable( $path ) ? file_get_contents( $path ) : false; // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown,WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$data = is_string( $text ) ? json_decode( $text, true ) : null;
		return is_array( $data ) ? $data : new \WP_Error( 'masthead_ai_invalid', 'No fixture.' );
	}

	/**
	 * Returns a fixed sentence.
	 *
	 * @param string $system System instruction.
	 * @param string $user   User prompt.
	 * @param string $tier   Tier.
	 * @return string|\WP_Error
	 */
	public function text( string $system, string $user, string $tier ): string|\WP_Error {
		return $this->fail ? new \WP_Error( 'masthead_ai_invalid', 'Fake failure.' ) : 'A fixed sentence from the fake client.';
	}
}
