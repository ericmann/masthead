<?php
/**
 * Settings > Calendar page and registered settings.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Admin;

use Masthead\Config;
use Masthead\Plan\Rhythm;
use Masthead\Support\Dates;
use const Masthead\SLUG;

/**
 * Registers the settings page and the two settings, exposed through wp/v2/settings.
 */
final class SettingsPage {

	/**
	 * Settings group.
	 */
	public const GROUP = 'masthead';

	/**
	 * Constructor.
	 *
	 * @param Assets $assets Assets.
	 */
	public function __construct( private Assets $assets ) {}

	/**
	 * Adds Settings > Calendar.
	 *
	 * @return void
	 */
	public function register(): void {
		$hook = add_options_page(
			__( 'Masthead calendar settings', 'masthead' ),
			__( 'Calendar', 'masthead' ),
			'manage_options',
			SLUG . '-settings',
			array( $this, 'render' )
		);
		if ( is_string( $hook ) ) {
			$this->assets->add_page_hook( $hook );
		}
	}

	/**
	 * Prints the React root.
	 *
	 * @return void
	 */
	public function render(): void {
		echo '<div class="wrap"><div id="' . esc_attr( 'masthead-settings-root' ) . '"></div></div>';
	}

	/**
	 * Registers masthead_rhythm and masthead_settings. Runs on admin_init and rest_api_init.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			self::GROUP,
			Rhythm::OPTION,
			array(
				'type'              => 'object',
				'sanitize_callback' => array( Rhythm::class, 'sanitize' ),
				'show_in_rest'      => array( 'schema' => $this->rhythm_schema() ),
			)
		);
		register_setting(
			self::GROUP,
			Config::OPTION,
			array(
				'type'              => 'object',
				'sanitize_callback' => array( Config::class, 'sanitize_overrides' ),
				'show_in_rest'      => array( 'schema' => $this->settings_schema() ),
			)
		);
	}

	/**
	 * REST schema of the rhythm: an object with a property per weekday.
	 *
	 * @return array<string,mixed>
	 */
	private function rhythm_schema(): array {
		$day        = array(
			'type'       => 'object',
			'properties' => array(
				'lane'       => array( 'type' => 'string' ),
				'label'      => array( 'type' => 'string' ),
				'categories' => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
				'time'       => array( 'type' => 'string' ),
			),
		);
		$properties = array();
		for ( $weekday = 0; $weekday < Dates::DAYS_IN_WEEK; $weekday++ ) {
			$properties[ (string) $weekday ] = $day;
		}
		return array(
			'type'       => 'object',
			'properties' => $properties,
		);
	}

	/**
	 * REST schema of masthead_settings, built from Config::settable() and the default types.
	 *
	 * @return array<string,mixed>
	 */
	private function settings_schema(): array {
		$defaults   = Config::defaults();
		$properties = array();
		foreach ( Config::settable() as $key ) {
			$parts = explode( '.', $key );
			$node  = &$properties;
			$last  = array_pop( $parts );
			foreach ( $parts as $part ) {
				if ( ! isset( $node[ $part ] ) ) {
					$node[ $part ] = array(
						'type'       => 'object',
						'properties' => array(),
					);
				}
				$node = &$node[ $part ]['properties'];
			}
			$node[ $last ] = $this->schema_for( $defaults[ $key ] );
			unset( $node );
		}
		return array(
			'type'       => 'object',
			'properties' => $properties,
		);
	}

	/**
	 * Schema fragment for a default value.
	 *
	 * @param mixed $default_value Default.
	 * @return array<string,mixed>
	 */
	private function schema_for( $default_value ): array {
		if ( is_bool( $default_value ) ) {
			return array( 'type' => 'boolean' );
		}
		if ( is_int( $default_value ) ) {
			return array( 'type' => 'integer' );
		}
		if ( is_float( $default_value ) ) {
			return array( 'type' => 'number' );
		}
		if ( array_is_list( $default_value ) ) {
			return array(
				'type'  => 'array',
				'items' => array( 'type' => 'string' ),
			);
		}
		$properties = array();
		foreach ( array_keys( $default_value ) as $name ) {
			$properties[ $name ] = array( 'type' => 'number' );
		}
		return array(
			'type'       => 'object',
			'properties' => $properties,
		);
	}
}
