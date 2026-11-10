<?php
/**
 * Assets tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Admin;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Masthead\Admin\Assets;
use Masthead\Plan\SeriesSource;
use Masthead\Support\Clock;
use Masthead\Tests\Unit\TestCase;

/**
 * Assets tests.
 */
final class AssetsTest extends TestCase {

	/**
	 * Fixture plugin dir holding a build.
	 *
	 * @var string
	 */
	private string $dir = '';

	protected function setUp(): void {
		parent::setUp();
		$this->dir = dirname( __DIR__ ) . '/fixtures/plugin';
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'wp_timezone_string' )->justReturn( self::ZONE );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'plugins_url' )->alias(
			static fn( $path ) => 'http://example.test/' . $path
		);
		Filters\expectApplied( 'masthead_now' )->zeroOrMoreTimes()->andReturn( '2026-11-09 14:00:00' );
		Filters\expectApplied( 'masthead_config' )->zeroOrMoreTimes()->andReturnFirstArg();
	}

	public function test_enqueue_returns_early_on_foreign_screen(): void {
		Functions\expect( 'wp_enqueue_script' )->never();
		Functions\expect( 'wp_add_inline_script' )->never();
		$assets = new Assets( new Clock(), $this->dir . '/masthead.php' );
		$assets->add_page_hook( 'posts_page_masthead' );
		$assets->enqueue( 'edit.php' );
		$this->assertTrue( true );
	}

	public function test_enqueue_loads_script_and_boot_data_on_registered_screen(): void {
		Functions\expect( 'wp_enqueue_script' )->once()->with( 'masthead', 'http://example.test/build/index.js', array( 'wp-element' ), 'abc', true );
		Functions\expect( 'wp_enqueue_style' )->atLeast()->once();
		Functions\expect( 'wp_set_script_translations' )->once()->with( 'masthead', 'masthead' );
		Functions\expect( 'wp_add_inline_script' )->once()->andReturnUsing(
			function ( $handle, $code, $position ) {
				$this->assertSame( 'masthead', $handle );
				$this->assertSame( 'before', $position );
				$this->assertStringStartsWith( 'window.mastheadBoot = {', $code );
				$this->assertStringContainsString( '"today":"2026-11-09"', $code );
				$this->assertStringContainsString( '"weekStartsOn":0', $code );
				$this->assertStringContainsString( '"timezone":"America\/Los_Angeles"', $code );
				return true;
			}
		);
		$assets = new Assets( new Clock(), $this->dir . '/masthead.php' );
		$assets->add_page_hook( 'posts_page_masthead' );
		$assets->enqueue( 'posts_page_masthead' );
	}

	public function test_boot_data_merges_registered_providers(): void {
		$assets = new Assets( new Clock(), $this->dir . '/masthead.php' );
		$assets->add_boot_data( 'extra', static fn() => array( 'a' => 1 ) );
		$data = $assets->boot_data();
		$this->assertSame( array( 'a' => 1 ), $data['extra'] );
		$this->assertSame( '2026-11-09', $data['today'] );
		$this->assertSame( \Masthead\VERSION, $data['version'] );
		$this->assertStringStartsWith( '2026-11-09T14:00:00', $data['now'] );
	}

	public function test_boot_data_has_config_caps_urls_series_categories_rhythm_and_ai(): void {
		Functions\when( 'current_user_can' )->alias( static fn( $cap ) => in_array( $cap, array( 'edit_posts', 'publish_posts' ), true ) );
		Functions\when( 'admin_url' )->alias( static fn( $path ) => 'http://example.test/wp-admin/' . $path );
		Functions\when( 'taxonomy_exists' )->justReturn( false );
		Functions\when( 'get_taxonomy' )->justReturn( (object) array( 'rest_base' => 'masthead_series' ) );
		Functions\when( 'get_terms' )->justReturn(
			array(
				(object) array(
					'term_id' => 8,
					'slug'    => 'technology',
					'name'    => 'Technology',
				),
			)
		);
		Functions\when( 'get_term_meta' )->justReturn( '' );
		Functions\when( 'sanitize_hex_color' )->justReturn( null );

		$assets = new Assets( new Clock(), $this->dir . '/masthead.php' );
		$assets->add_planning_boot_data( new SeriesSource() );
		$data = $assets->boot_data();

		$this->assertSame(
			array(
				'ghostDays'        => 14,
				'horizonDays'      => 21,
				'maxRangeDays'     => 92,
				'maxPostsPerRange' => 500,
				'maxSuggestions'   => 3,
				'dragPublished'    => false,
				'statsWindowDays'  => 30,
			),
			$data['config']
		);
		$this->assertSame(
			array(
				'editPosts'       => true,
				'editOthersPosts' => false,
				'publishPosts'    => true,
				'promptAi'        => false,
				'manageOptions'   => false,
			),
			$data['caps']
		);
		$this->assertSame( 'http://example.test/wp-admin/post.php?post=%d&action=edit', $data['urls']['editPost'] );
		$this->assertSame( 'http://example.test/wp-admin/options-general.php?page=masthead-settings', $data['urls']['settings'] );
		$this->assertSame( 'http://example.test/wp-admin/options-connectors.php', $data['urls']['connectors'] );
		$this->assertSame( 'http://example.test/wp-admin/post-new.php', $data['urls']['newPost'] );
		$this->assertSame(
			array(
				'taxonomy' => 'masthead_series',
				'restBase' => 'masthead_series',
			),
			$data['series']
		);
		$this->assertSame(
			array(
				'exposeMcp'   => true,
				'exposeShare' => false,
			),
			$data['exposure']
		);
		$this->assertSame( 21, $data['settingsDefaults']['desk.horizon_days'] );
		$this->assertSame(
			array(
				'lane' => 0.5,
				'category' => 0.3,
				'weekday' => 0.2,
			),
			$data['settingsDefaults']['swap.weights'] 
		);
		$this->assertSame( array( 'claude-haiku-5-5' ), $data['settingsDefaults']['ai.models.fast'] );
		$this->assertArrayNotHasKey( 'calendar.max_range_days', $data['settingsDefaults'] );
		$this->assertArrayNotHasKey( 'ai.temperature', $data['settingsDefaults'] );
		$this->assertSame( 'technology', $data['categories'][0]['slug'] );
		$this->assertSame( '#2f5fd0', $data['categories'][0]['color'] );
		$this->assertSame( 'faith', $data['rhythm'][0]['lane'] );
		$this->assertSame(
			array(
				'supported' => false,
				'remaining' => 0,
			),
			$data['ai']
		);
	}

	public function test_enqueue_does_nothing_when_build_is_missing(): void {
		Functions\expect( 'wp_enqueue_script' )->never();
		$assets = new Assets( new Clock(), '/nonexistent/masthead.php' );
		$assets->add_page_hook( 'posts_page_masthead' );
		$assets->enqueue( 'posts_page_masthead' );
		$this->assertTrue( true );
	}
}
