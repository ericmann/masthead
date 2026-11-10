<?php
/**
 * ShareCopy tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Desk;

use Brain\Monkey\Functions;
use Masthead\Ai\AiClient;
use Masthead\Desk\ShareCopy;
use Masthead\Tests\Unit\TestCase;

/**
 * ShareCopy tests.
 */
final class ShareCopyTest extends TestCase {

	/**
	 * The AI client used by the last test helper call.
	 *
	 * @var object
	 */
	private $client;

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'wp_strip_all_tags' )->returnArg();
		Functions\when( 'wp_trim_words' )->returnArg();
	}

	private function post( string $status = 'publish', string $type = 'post' ): object {
		return (object) array(
			'ID'           => 5,
			'post_type'    => $type,
			'post_status'  => $status,
			'post_title'   => 'ZZTITLE',
			'post_excerpt' => 'ZZEXCERPT',
			'post_content' => 'SECRET BODY',
		);
	}

	private function copy( mixed $reply, mixed $post ): ShareCopy {
		Functions\when( 'get_post' )->justReturn( $post );
		$this->client = new class( $reply ) implements AiClient {
			public int $calls = 0;
			public array $seen = array();
			public function __construct( private mixed $reply ) {}
			public function supported(): bool {
				return true;
			}
			public function json( string $system, string $user, array $schema, string $tier ): array|\WP_Error {
				++$this->calls;
				$this->seen = array( $system, $user, $schema, $tier );
				return $this->reply;
			}
			public function text( string $system, string $user, string $tier ): string|\WP_Error {
				return '';
			}
		};
		return new ShareCopy( $this->client );
	}

	public function test_returns_the_validated_message(): void {
		$copy = $this->copy( array( 'message' => 'Read this.' ), $this->post() );
		$this->assertSame( 'Read this.', $copy->draft( 5 ) );
		$this->assertSame( 'fast', $this->client->seen[3] );
		$this->assertSame( 'share_copy', $this->client->seen[2]['title'] );
	}

	public function test_invalid_reply_returns_wp_error(): void {
		foreach ( array(
			array( 'message' => '' ),
			array( 'message' => 5 ),
			array(),
			array(
				'message' => 'x',
				'extra' => 1,
			),
		) as $reply ) {
			$result = $this->copy( $reply, $this->post() )->draft( 5 );
			$this->assertInstanceOf( \WP_Error::class, $result );
			$this->assertSame( 'masthead_ai_invalid', $result->get_error_code() );
		}
		$error = new \WP_Error( 'masthead_ai_budget', 'Limit.' );
		$this->assertSame( $error, $this->copy( $error, $this->post() )->draft( 5 ) );
	}

	public function test_non_published_post_is_refused_without_an_ai_call(): void {
		$copy = $this->copy( array( 'message' => 'x' ), $this->post( 'draft' ) );
		$result = $copy->draft( 5 );
		$this->assertSame( 'masthead_not_published', $result->get_error_code() );
		$this->assertSame( array( 'status' => 409 ), $result->get_error_data() );
		$missing = $this->copy( array( 'message' => 'x' ), null )->draft( 5 );
		$this->assertSame( 'masthead_not_found', $missing->get_error_code() );
		$this->assertSame( array( 'status' => 404 ), $missing->get_error_data() );
		$page = $this->copy( array( 'message' => 'x' ), $this->post( 'publish', 'page' ) )->draft( 5 );
		$this->assertSame( 'masthead_not_found', $page->get_error_code() );
		$this->assertSame( 0, $this->client->calls );
	}

	public function test_prompt_contains_title_and_excerpt_but_not_content(): void {
		$this->copy( array( 'message' => 'x' ), $this->post() )->draft( 5 );
		$user = $this->client->seen[1];
		$this->assertStringContainsString( '<untrusted-post-data>ZZTITLE</untrusted-post-data>', $user );
		$this->assertStringContainsString( '<untrusted-post-data>ZZEXCERPT</untrusted-post-data>', $user );
		$this->assertStringNotContainsString( 'SECRET BODY', $user . $this->client->seen[0] );
	}
}
