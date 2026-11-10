<?php
/**
 * Prompt builder stub for tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Ai;

/**
 * Records calls like the core prompt builder, which uses __call.
 */
final class StubBuilder {

	/**
	 * Recorded calls.
	 *
	 * @var array<int,array{0:string,1:array}>
	 */
	public array $calls = array();

	/**
	 * What generate_text_result returns or throws.
	 *
	 * @var mixed
	 */
	public $result;

	/**
	 * What the support check returns.
	 *
	 * @var bool
	 */
	public bool $supported = true;

	/**
	 * Prompt the factory received.
	 *
	 * @var string|null
	 */
	public ?string $prompt = null;

	/**
	 * Records a call and stays fluent.
	 *
	 * @param string $name Method.
	 * @param array  $args Arguments.
	 * @return mixed
	 * @throws \Throwable When the result is a throwable.
	 */
	public function __call( string $name, array $args ) {
		$this->calls[] = array( $name, $args );
		if ( 'is_supported_for_text_generation' === $name ) {
			return $this->supported;
		}
		if ( 'generate_text_result' === $name ) {
			if ( $this->result instanceof \Throwable ) {
				throw $this->result;
			}
			return $this->result;
		}
		return $this;
	}

	/**
	 * Names of the recorded calls.
	 *
	 * @return string[]
	 */
	public function names(): array {
		return array_column( $this->calls, 0 );
	}

	/**
	 * Arguments of the first call with a name.
	 *
	 * @param string $name Method.
	 * @return array
	 */
	public function args( string $name ): array {
		foreach ( $this->calls as $call ) {
			if ( $call[0] === $name ) {
				return $call[1];
			}
		}
		return array();
	}
}
