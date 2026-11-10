<?php
/**
 * Ideas tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Desk;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Masthead\Ai\AiClient;
use Masthead\Desk\Budget;
use Masthead\Desk\Candidates\Candidate;
use Masthead\Desk\Candidates\CategoryGap;
use Masthead\Desk\Ideas;
use Masthead\Plan\PlanReader;
use Masthead\Plan\SeriesSource;
use Masthead\Support\Clock;
use Masthead\Tests\Unit\TestCase;

/**
 * Ideas tests.
 */
final class IdeasTest extends TestCase {

	/**
	 * Config overrides.
	 *
	 * @var array<string,mixed>
	 */
	private array $overrides = array();

	/**
	 * Fields asked of get_post_field.
	 *
	 * @var string[]
	 */
	private array $fields = array();

	/**
	 * Range arguments the reader saw.
	 *
	 * @var array
	 */
	private array $range = array();

	protected function setUp(): void {
		parent::setUp();
		$this->overrides = array();
		$this->fields    = array();
		$this->range     = array();
		Functions\when( 'get_option' )->justReturn( array() );
		Filters\expectApplied( 'masthead_config' )->zeroOrMoreTimes()->andReturnUsing(
			fn( $flat ) => array_merge( $flat, $this->overrides )
		);
		Functions\when( 'get_terms' )->justReturn(
			array(
				(object) array(
					'term_id' => 1,
					'slug' => 'opinion',
					'name' => 'Opinion',
				),
				(object) array(
					'term_id' => 2,
					'slug' => 'technology',
					'name' => 'Technology',
				),
				(object) array(
					'term_id' => 3,
					'slug' => 'security',
					'name' => 'Security',
				),
			)
		);
		Functions\when( 'get_post_field' )->alias(
			function ( $field, $post_id ) {
				$this->fields[] = $field;
				return 'post_content' === $field ? 'SECRET BODY' : 'Excerpt ' . $post_id;
			}
		);
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'wp_strip_all_tags' )->returnArg();
		Functions\when( 'wp_trim_words' )->returnArg();
	}

	private function post( int $id, string $status = 'publish' ): array {
		return array(
			'id'     => $id,
			'title'  => 'Post ' . $id,
			'status' => $status,
		);
	}

	/**
	 * Ideas with a canned AI reply and plan.
	 *
	 * @param mixed $reply     AI reply.
	 * @param array $days      Posts per day, list of lists.
	 * @param array $undated   Undated drafts.
	 * @param int   $remaining Budget remaining.
	 * @return Ideas
	 */
	private function ideas( mixed $reply, array $days = array(), array $undated = array(), int $remaining = 7 ): Ideas {
		$ai     = new class( $reply ) implements AiClient {
			public array $schemas = array();
			public array $users   = array();
			public function __construct( private mixed $reply ) {}
			public function supported(): bool {
				return true;
			}
			public function json( string $system, string $user, array $schema, string $tier ): array|\WP_Error {
				$this->schemas[] = array( $schema, $tier );
				$this->users[]   = $user;
				return $this->reply;
			}
			public function text( string $system, string $user, string $tier ): string|\WP_Error {
				return '';
			}
		};
		$test   = $this;
		$reader = new class( $days, $undated, $test ) extends PlanReader {
			public function __construct( private array $days, private array $undated, private $test ) {}
			public function range( string $from, string $to ): array {
				$this->test->record_range( $from, $to );
				return array(
					'days'        => array_map( fn( $posts ) => array( 'posts' => $posts ), $this->days ),
					'unscheduled' => $this->undated,
					'truncated'   => false,
				);
			}
		};
		$series = new class() extends SeriesSource {
			public function __construct() {}
			public function terms(): array {
				return array(
					(object) array(
						'term_id' => 9,
						'name' => 'Open Series',
					),
					(object) array(
						'term_id' => 10,
						'name' => 'Complete Series',
					),
				);
			}
			public function parts( int $term_id ): array {
				return 9 === $term_id
					? array(
						array(
							'post_id' => 50,
							'part' => 1,
							'status' => 'publish',
							'date' => 'x',
							'dated' => true,
						),
						array(
							'post_id' => 51,
							'part' => 2,
							'status' => 'draft',
							'date' => null,
							'dated' => false,
						),
					)
					: array(
						array(
							'post_id' => 60,
							'part' => 1,
							'status' => 'publish',
							'date' => 'x',
							'dated' => true,
						),
					);
			}
			public function for_post( int $post_id ): ?array {
				return array( 'total' => 50 === $post_id ? 3 : 1 );
			}
		};
		$gaps   = new class() extends CategoryGap {
			public function __construct() {}
			public function gaps(): array {
				return array( Candidate::make( 'gap', 'gap:3', 'x', 1.0, array( 'data' => array( 'category_name' => 'Security' ) ) ) );
			}
		};
		$budget = new class( new Clock(), $remaining ) extends Budget {
			public function __construct( Clock $clock, private int $left ) {
				parent::__construct( $clock );
			}
			public function remaining(): int {
				return $this->left;
			}
		};
		$this->client = $ai;
		return new Ideas( $ai, $reader, $series, $gaps, $budget );
	}

	/**
	 * The last AI client built.
	 *
	 * @var object
	 */
	public $client;

	public function record_range( string $from, string $to ): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->range = array( $from, $to );
	}

	private function reply( array $items ): array {
		return array( 'ideas' => $items );
	}

	private function item( string $title, string $category ): array {
		return array(
			'title'    => $title,
			'angle'    => 'Angle of ' . $title,
			'category' => $category,
		);
	}

	private function lane( array $categories = array( 'technology' ) ): array {
		return array(
			'slug'       => 'standalone',
			'label'      => 'Standalone',
			'categories' => $categories,
		);
	}

	public function test_valid_ideas_become_idea_candidates_with_sha1_keys(): void {
		$ideas = $this->ideas( $this->reply( array( $this->item( 'First', 'technology' ), $this->item( 'First', 'technology' ), $this->item( 'Second', 'technology' ) ) ) );
		$out   = $ideas->ideas( '2026-11-18', $this->lane(), array( 'idea:' . sha1( 'Second' ) ) );
		$this->assertCount( 1, $out );
		$first = $out[0]->to_array();
		$this->assertSame( 'idea', $first['kind'] );
		$this->assertSame( 'idea:' . sha1( 'First' ), $first['key'] );
		$this->assertSame( 'First', $first['title'] );
		$this->assertSame( 'technology', $first['category'] );
		$this->assertSame( 'Angle of First', $first['data']['angle'] );
		$this->assertSame( 'fast', $this->client->schemas[0][1] );
		$this->assertSame( array( 'technology' ), $this->client->schemas[0][0]['properties']['ideas']['items']['properties']['category']['enum'] );
	}

	public function test_any_category_lane_prompt_lists_every_category(): void {
		$ideas = $this->ideas( $this->reply( array() ) );
		$ideas->ideas( '2026-11-18', $this->lane( array() ), array() );
		$user = $this->client->users[0];
		$this->assertStringNotContainsString( 'Allowed categories: none', $user );
		foreach ( array( 'opinion', 'technology', 'security' ) as $slug ) {
			$this->assertMatchesRegularExpression( '#<untrusted-post-data>\s*' . $slug . '\s*</untrusted-post-data>#', $user );
		}
	}

	public function test_idea_outside_the_lane_categories_is_dropped_not_coerced(): void {
		$ideas = $this->ideas( $this->reply( array( $this->item( 'In lane', 'technology' ), $this->item( 'Out of lane', 'security' ) ) ) );
		$out   = $ideas->ideas( '2026-11-18', $this->lane(), array() );
		$this->assertSame( array( 'In lane' ), array_map( fn( $c ) => $c->to_array()['title'], $out ) );

		$open = $this->ideas( $this->reply( array( $this->item( 'Anywhere', 'security' ) ) ) );
		$this->assertCount( 1, $open->ideas( '2026-11-18', $this->lane( array() ), array() ) );
		$this->assertSame( array( 'opinion', 'technology', 'security' ), $this->client->schemas[0][0]['properties']['ideas']['items']['properties']['category']['enum'] );

		$unknown = $this->ideas( $this->reply( array( $this->item( 'Nowhere', 'sports' ) ) ) );
		$this->assertSame( 'masthead_ai_invalid', $unknown->ideas( '2026-11-18', $this->lane( array() ), array() )->get_error_code() );
	}

	public function test_invalid_json_returns_wp_error_and_no_candidates(): void {
		foreach ( array( array( 'ideas' => array( array( 'title' => 'x' ) ) ), array( 'other' => 1 ), $this->reply( array_fill( 0, 4, $this->item( 'A', 'technology' ) ) ) ) as $reply ) {
			$result = $this->ideas( $reply )->ideas( '2026-11-18', $this->lane(), array() );
			$this->assertInstanceOf( \WP_Error::class, $result );
			$this->assertSame( 'masthead_ai_invalid', $result->get_error_code() );
		}
	}

	public function test_budget_exhaustion_returns_the_budget_error(): void {
		$error  = new \WP_Error( 'masthead_ai_budget', 'Limit.', array( 'status' => 429 ) );
		$result = $this->ideas( $error )->ideas( '2026-11-18', $this->lane(), array() );
		$this->assertSame( $error, $result );
	}

	public function test_context_never_contains_post_content(): void {
		$ideas   = $this->ideas( $this->reply( array() ), array( array( $this->post( 1 ), $this->post( 2, 'future' ) ) ), array( $this->post( 3, 'draft' ) ) );
		$context = $ideas->context( '2026-11-18' );
		$this->assertStringNotContainsString( 'SECRET BODY', (string) wp_json_encode( $context ) );
		$this->assertSame( array( 'post_excerpt' ), array_values( array_unique( $this->fields ) ) );
		$titles = array_column( $context['posts'], 'title' );
		$this->assertSame( array( 'Post 2', 'Post 1', 'Post 3' ), $titles );
		$this->assertSame( 'Excerpt 2', $context['posts'][0]['excerpt'] );
		$this->assertSame( array( 'Open Series' ), $context['series'] );
		$this->assertSame( array( 'Security' ), $context['gaps'] );
		$this->assertSame( array( '2026-08-20', '2026-11-18' ), $this->range );
	}

	public function test_context_omits_drafts_when_draft_excerpts_are_off(): void {
		$this->overrides = array( 'ai.include_draft_excerpts' => false );
		$ideas           = $this->ideas( $this->reply( array() ), array( array( $this->post( 1 ), $this->post( 2, 'draft' ) ) ), array( $this->post( 3, 'draft' ) ) );
		$this->assertSame( array( 'Post 1' ), array_column( $ideas->context( '2026-11-18' )['posts'], 'title' ) );
	}

	public function test_context_size_is_bounded_by_config(): void {
		$this->overrides = array(
			'ai.context_max_posts' => 2,
			'ai.context_days'      => 10,
		);
		$ideas           = $this->ideas( $this->reply( array() ), array( array( $this->post( 1 ), $this->post( 2 ), $this->post( 3 ) ) ) );
		$this->assertCount( 2, $ideas->context( '2026-11-18' )['posts'] );
		$this->assertSame( '2026-11-08', $this->range[0] );
	}

	public function test_status_reports_support_and_remaining_budget(): void {
		$this->assertSame(
			array(
				'supported' => true,
				'remaining' => 7,
			),
			$this->ideas( array() )->status()
		);
	}

	public function test_outline_uses_the_strong_tier_and_outline_schema(): void {
		$ideas   = $this->ideas( array( 'outline' => array( 'One', 'Two' ) ), array( array( $this->post( 1 ) ) ) );
		$outline = $ideas->outline( 'A title', 'security', '2026-11-18' );
		$this->assertSame( array( 'One', 'Two' ), $outline );
		$this->assertSame( 'strong', $this->client->schemas[0][1] );
		$this->assertSame( 'outline', $this->client->schemas[0][0]['title'] );
		$this->assertStringContainsString( '<untrusted-post-data>A title</untrusted-post-data>', $this->client->users[0] );
		$this->assertStringContainsString( '<untrusted-post-data>Security</untrusted-post-data>', $this->client->users[0] );
		$this->assertStringContainsString( 'Post 1', $this->client->users[0] );

		$this->assertSame( array( 'One', 'Two' ), $ideas->outline( 'A title', null, null ) );
		$this->assertSame( array(), $this->context_posts_of_last_call() );
	}

	/**
	 * Whether the last outline prompt listed related posts.
	 *
	 * @return array
	 */
	private function context_posts_of_last_call(): array {
		return str_contains( end( $this->client->users ), 'Post 1' ) ? array( 'leaked' ) : array();
	}

	public function test_invalid_outline_returns_wp_error(): void {
		foreach ( array( array( 'outline' => array() ), array( 'outline' => array( '' ) ), array( 'outline' => 'text' ), array( 'extra' => 1 ) ) as $reply ) {
			$result = $this->ideas( $reply )->outline( 'A title', null, null );
			$this->assertInstanceOf( \WP_Error::class, $result );
			$this->assertSame( 'masthead_ai_invalid', $result->get_error_code() );
		}
		$error = new \WP_Error( 'masthead_ai_budget', 'Limit.' );
		$this->assertSame( $error, $this->ideas( $error )->outline( 'A title', null, null ) );
	}
}
