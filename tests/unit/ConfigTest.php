<?php
/**
 * Config tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Masthead\Config;

/**
 * Config tests.
 */
final class ConfigTest extends TestCase {

	/**
	 * Stubs the options.
	 *
	 * @param array<mixed> $settings Stored settings.
	 * @param int          $start    start_of_week.
	 * @return void
	 */
	private function options( array $settings = array(), int $start = 0 ): void {
		Functions\when( 'get_option' )->alias(
			static function ( $name, $fallback = false ) use ( $settings, $start ) {
				if ( 'masthead_settings' === $name ) {
					return $settings;
				}
				if ( 'start_of_week' === $name ) {
					return (string) $start;
				}
				return $fallback;
			}
		);
	}

	public function test_defaults_contain_every_spec_key(): void {
		$expected = array(
			'post_types',
			'calendar.week_starts_on',
			'calendar.drag_published',
			'calendar.max_posts_per_range',
			'desk.horizon_days',
			'desk.ghost_days',
			'desk.max_suggestions',
			'desk.ideas_per_request',
			'desk.gap_days',
			'desk.reshare_min_age_days',
			'desk.reshare_cooldown_days',
			'desk.series_window_days',
			'swap.lookahead_days',
			'swap.min_score_delta',
			'swap.candidate_window_days',
			'swap.weights',
			'stats.window_days',
			'stats.cache_ttl',
			'ai.monthly_request_cap',
			'ai.models.fast',
			'ai.models.strong',
			'ai.temperature',
			'ai.max_tokens',
			'ai.context_days',
			'ai.context_max_posts',
			'ai.include_draft_excerpts',
			'abilities.expose_mcp',
			'abilities.expose_share',
			'cron.nightly_hour',
		);
		foreach ( $expected as $key ) {
			$this->assertArrayHasKey( $key, Config::defaults(), $key );
		}
	}

	public function test_assumption_defaults_match_spec(): void {
		$d = Config::defaults();
		$this->assertSame( 500, $d['calendar.max_posts_per_range'] );
		$this->assertSame( 21, $d['desk.horizon_days'] );
		$this->assertSame( 14, $d['desk.ghost_days'] );
		$this->assertSame( 45, $d['desk.gap_days'] );
		$this->assertSame( 90, $d['desk.reshare_min_age_days'] );
		$this->assertSame( 180, $d['desk.reshare_cooldown_days'] );
		$this->assertSame( 3, $d['desk.series_window_days'] );
		$this->assertSame( 60, $d['swap.lookahead_days'] );
		$this->assertSame( 0.25, $d['swap.min_score_delta'] );
		$this->assertSame( 3, $d['swap.candidate_window_days'] );
		$this->assertSame(
			array(
				'lane'     => 0.5,
				'category' => 0.3,
				'weekday'  => 0.2,
			),
			$d['swap.weights']
		);
		$this->assertSame( 21600, $d['stats.cache_ttl'] );
		$this->assertSame( 300, $d['ai.monthly_request_cap'] );
		$this->assertSame( array( 'claude-haiku-5-5' ), $d['ai.models.fast'] );
		$this->assertSame( array( 'claude-sonnet-5-5' ), $d['ai.models.strong'] );
		$this->assertSame( 0.4, $d['ai.temperature'] );
		$this->assertSame( 1200, $d['ai.max_tokens'] );
		$this->assertSame( 60, $d['ai.context_max_posts'] );
	}

	public function test_planner_keys_have_documented_defaults(): void {
		$d = Config::defaults();
		$this->assertSame( 92, $d['calendar.max_range_days'] );
		$this->assertSame( 50, $d['desk.candidate_pool'] );
		$this->assertSame( 10, $d['stats.top_posts_limit'] );
		$this->assertSame( 12, $d['ai.series_max_parts'] );
		$this->assertSame( array( 'journal' ), $d['swap.collision_exempt_categories'] );
	}

	public function test_get_unknown_key_throws(): void {
		$this->options();
		$this->expectException( \InvalidArgumentException::class );
		Config::get( 'nope.nothing' );
	}

	public function test_option_overrides_only_settable_keys(): void {
		$this->options(
			array(
				'desk'     => array( 'horizon_days' => 30 ),
				'calendar' => array( 'max_posts_per_range' => 5 ),
				'ai'       => array( 'models' => array( 'fast' => array( 'x-model' ) ) ),
			)
		);
		$this->assertSame( 30, Config::get( 'desk.horizon_days' ) );
		$this->assertSame( 500, Config::get( 'calendar.max_posts_per_range' ) );
		$this->assertSame( array( 'x-model' ), Config::get( 'ai.models.fast' ) );
	}

	public function test_option_override_of_wrong_type_is_ignored(): void {
		$this->options(
			array(
				'desk' => array( 'horizon_days' => 'soon' ),
				'swap' => array( 'min_score_delta' => 1 ),
			)
		);
		$this->assertSame( 21, Config::get( 'desk.horizon_days' ) );
		$this->assertSame( 1, Config::get( 'swap.min_score_delta' ) );
	}

	public function test_masthead_config_filter_wins(): void {
		$this->options( array( 'desk' => array( 'horizon_days' => 30 ) ) );
		Filters\expectApplied( 'masthead_config' )->once()->andReturnUsing(
			static function ( array $flat ) {
				$flat['desk.horizon_days'] = 7;
				return $flat;
			}
		);
		$this->assertSame( 7, Config::get( 'desk.horizon_days' ) );
	}

	public function test_week_starts_on_reads_start_of_week_option(): void {
		$this->options( array(), 1 );
		$this->assertSame( 1, Config::get( 'calendar.week_starts_on' ) );
	}

	public function test_sanitize_overrides_drops_non_settable_keys(): void {
		$clean = Config::sanitize_overrides(
			array(
				'desk'     => array(
					'horizon_days' => 30,
					'unknown'      => 5,
				),
				'calendar' => array( 'max_posts_per_range' => 5 ),
				'cron'     => array( 'nightly_hour' => 5 ),
				'rogue'    => true,
			)
		);
		$this->assertSame( array( 'desk' => array( 'horizon_days' => 30 ) ), $clean );
		$this->assertSame( array(), Config::sanitize_overrides( 'nope' ) );
	}

	public function test_sanitize_overrides_enforces_types_and_bounds(): void {
		$clean = Config::sanitize_overrides(
			array(
				'desk'      => array(
					'horizon_days'   => 0,
					'ghost_days'     => '14',
					'max_suggestions' => 1,
					'gap_days'       => -3,
				),
				'swap'      => array(
					'min_score_delta' => 2,
					'weights'         => array(
						'lane'     => 0.6,
						'category' => 0.3,
						'weekday'  => 1,
					),
				),
				'ai'        => array(
					'monthly_request_cap' => 0,
					'models'              => array(
						'fast'   => array( ' a ', 'b' ),
						'strong' => array( '' ),
					),
					'include_draft_excerpts' => 'yes',
				),
				'abilities' => array( 'expose_share' => true ),
				'stats'     => array( 'window_days' => 7 ),
			)
		);
		$this->assertEquals(
			array(
				'desk'      => array( 'max_suggestions' => 1 ),
				'swap'      => array(
					'weights' => array(
						'lane'     => 0.6,
						'category' => 0.3,
						'weekday'  => 1.0,
					),
				),
				'ai'        => array(
					'monthly_request_cap' => 0,
					'models'              => array( 'fast' => array( 'a', 'b' ) ),
				),
				'abilities' => array( 'expose_share' => true ),
				'stats'     => array( 'window_days' => 7 ),
			),
			$clean
		);
		$partial = Config::sanitize_overrides( array( 'swap' => array( 'weights' => array( 'lane' => 0.5 ) ) ) );
		$this->assertSame( array(), $partial );
	}

	public function test_sanitized_overrides_are_read_back_by_get(): void {
		$clean = Config::sanitize_overrides(
			array(
				'desk' => array( 'horizon_days' => 30 ),
				'ai'   => array( 'models' => array( 'fast' => array( 'x-model' ) ) ),
			)
		);
		$this->options( $clean );
		$this->assertSame( 30, Config::get( 'desk.horizon_days' ) );
		$this->assertSame( array( 'x-model' ), Config::get( 'ai.models.fast' ) );
		$this->assertSame( 14, Config::get( 'desk.ghost_days' ) );
	}
}
