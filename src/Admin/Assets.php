<?php
/**
 * Admin assets and boot data.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Admin;

use Masthead\Config;
use Masthead\Plan\Colors;
use Masthead\Plan\Rhythm;
use Masthead\Plan\SeriesSource;
use Masthead\Support\Clock;
use const Masthead\SLUG;
use const Masthead\VERSION;

/**
 * Loads the built client on Masthead's own screens only.
 */
final class Assets {

	/**
	 * Hook suffixes of the registered Masthead pages.
	 *
	 * @var string[]
	 */
	private array $page_hooks = array();

	/**
	 * Boot data providers keyed by name.
	 *
	 * @var array<string,callable>
	 */
	private array $providers = array();

	/**
	 * Constructor.
	 *
	 * @param Clock  $clock       Clock.
	 * @param string $plugin_file Absolute path of masthead.php.
	 */
	public function __construct( private Clock $clock, private string $plugin_file ) {
		$this->add_boot_data( 'version', static fn() => VERSION );
		$this->add_boot_data( 'now', fn() => $this->clock->now()->format( DATE_ATOM ) );
		$this->add_boot_data( 'today', fn() => $this->clock->today() );
		$this->add_boot_data( 'timezone', static fn() => wp_timezone_string() );
		$this->add_boot_data( 'weekStartsOn', static fn() => Config::get( 'calendar.week_starts_on' ) );
	}

	/**
	 * Adds the boot data the calendar client needs to read the plan.
	 *
	 * @param SeriesSource $series Series adapter.
	 * @return void
	 */
	public function add_planning_boot_data( SeriesSource $series ): void {
		$this->add_boot_data(
			'config',
			static fn() => array(
				'ghostDays'        => Config::get( 'desk.ghost_days' ),
				'horizonDays'      => Config::get( 'desk.horizon_days' ),
				'maxRangeDays'     => Config::get( 'calendar.max_range_days' ),
				'maxPostsPerRange' => Config::get( 'calendar.max_posts_per_range' ),
				'maxSuggestions'   => Config::get( 'desk.max_suggestions' ),
				'dragPublished'    => Config::get( 'calendar.drag_published' ),
				'statsWindowDays'  => Config::get( 'stats.window_days' ),
			)
		);
		$this->add_boot_data(
			'caps',
			static fn() => array(
				'editPosts'       => current_user_can( 'edit_posts' ),
				'editOthersPosts' => current_user_can( 'edit_others_posts' ),
				'publishPosts'    => current_user_can( 'publish_posts' ),
				'promptAi'        => current_user_can( 'prompt_ai' ),
				'manageOptions'   => current_user_can( 'manage_options' ),
			)
		);
		$this->add_boot_data(
			'urls',
			static fn() => array(
				'newPost'    => admin_url( 'post-new.php' ),
				'editPost'   => admin_url( 'post.php?post=%d&action=edit' ),
				'settings'   => admin_url( 'options-general.php?page=' . SLUG . '-settings' ),
				'connectors' => admin_url( 'options-connectors.php' ),
			)
		);
		$this->add_boot_data(
			'series',
			static fn() => array(
				'taxonomy' => $series->taxonomy(),
				'restBase' => $series->rest_base(),
			)
		);
		$this->add_boot_data(
			'exposure',
			static fn() => array(
				'exposeMcp'   => Config::get( 'abilities.expose_mcp' ),
				'exposeShare' => Config::get( 'abilities.expose_share' ),
			)
		);
		$this->add_boot_data( 'categories', array( Colors::class, 'legend' ) );
		$this->add_boot_data( 'rhythm', array( Rhythm::class, 'get' ) );
		$this->add_boot_data(
			'ai',
			static fn() => array(
				'supported' => false,
				'remaining' => 0,
			)
		);
	}

	/**
	 * Registers a Masthead page's hook suffix.
	 *
	 * @param string $hook_suffix Hook suffix.
	 * @return void
	 */
	public function add_page_hook( string $hook_suffix ): void {
		$this->page_hooks[] = $hook_suffix;
	}

	/**
	 * Whether a hook suffix belongs to a Masthead page.
	 *
	 * @param string $hook_suffix Hook suffix.
	 * @return bool
	 */
	public function is_page_hook( string $hook_suffix ): bool {
		return in_array( $hook_suffix, $this->page_hooks, true );
	}

	/**
	 * Registers a boot data provider.
	 *
	 * @param string   $name     Key in window.mastheadBoot.
	 * @param callable $provider Returns the value.
	 * @return void
	 */
	public function add_boot_data( string $name, callable $provider ): void {
		$this->providers[ $name ] = $provider;
	}

	/**
	 * Boot data from every provider.
	 *
	 * @return array<string,mixed>
	 */
	public function boot_data(): array {
		$data = array();
		foreach ( $this->providers as $name => $provider ) {
			$data[ $name ] = $provider();
		}
		return $data;
	}

	/**
	 * Whether the client build exists.
	 *
	 * @return bool
	 */
	public function build_exists(): bool {
		return is_readable( $this->path( 'build/index.asset.php' ) );
	}

	/**
	 * Enqueues assets on Masthead screens. Returns early anywhere else.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue( string $hook_suffix ): void {
		if ( ! $this->is_page_hook( $hook_suffix ) ) {
			return;
		}
		if ( ! $this->build_exists() ) {
			return;
		}
		$asset = require $this->path( 'build/index.asset.php' );

		wp_enqueue_script(
			SLUG,
			plugins_url( 'build/index.js', $this->plugin_file ),
			$asset['dependencies'] ?? array(),
			$asset['version'] ?? VERSION,
			true
		);
		foreach ( array( 'build/style-index.css', 'build/index.css' ) as $css ) {
			if ( is_readable( $this->path( $css ) ) ) {
				wp_enqueue_style( SLUG, plugins_url( $css, $this->plugin_file ), array( 'wp-components' ), $asset['version'] ?? VERSION );
				break;
			}
		}
		wp_enqueue_style( 'wp-components' );
		wp_set_script_translations( SLUG, 'masthead' );
		wp_add_inline_script( SLUG, 'window.mastheadBoot = ' . wp_json_encode( $this->boot_data() ) . ';', 'before' );
	}

	/**
	 * Absolute path inside the plugin.
	 *
	 * @param string $relative Relative path.
	 * @return string
	 */
	private function path( string $relative ): string {
		return dirname( $this->plugin_file ) . '/' . $relative;
	}
}
