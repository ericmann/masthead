<?php
/**
 * The weekly rhythm option.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Plan;

use Masthead\Support\Dates;

/**
 * Stores and sanitises option masthead_rhythm. Keys are PHP "w" weekdays, 0 = Sunday.
 */
final class Rhythm {

	/**
	 * Option name.
	 */
	public const OPTION = 'masthead_rhythm';

	/**
	 * Default rhythm. Equals docs/fixtures/rhythm-defaults.json (the release zip has no docs/).
	 */
	public const DEFAULTS = array(
		'0' => array(
			'lane'       => 'faith',
			'label'      => 'Faith',
			'categories' => array( 'faith' ),
			'time'       => '06:30',
		),
		'1' => array(
			'lane'       => 'business',
			'label'      => 'Business & leadership',
			'categories' => array( 'business' ),
			'time'       => '07:00',
		),
		'2' => array(
			'lane'       => 'technology',
			'label'      => 'Technical series',
			'categories' => array( 'technology' ),
			'time'       => '07:30',
		),
		'3' => array(
			'lane'       => 'standalone',
			'label'      => 'Standalone',
			'categories' => array(),
			'time'       => '08:00',
		),
		'4' => array(
			'lane'       => 'technology',
			'label'      => 'Technical series',
			'categories' => array( 'technology', 'security' ),
			'time'       => '07:30',
		),
		'5' => array(
			'lane'       => 'journal',
			'label'      => 'Journal & build log',
			'categories' => array( 'journal' ),
			'time'       => '08:00',
		),
		'6' => array(
			'lane'       => 'fiction',
			'label'      => 'Fiction serial',
			'categories' => array( 'writing' ),
			'time'       => '06:30',
		),
	);

	/**
	 * The sanitised stored rhythm, or the defaults when the option is missing.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function get(): array {
		$stored = get_option( self::OPTION, false );
		if ( false === $stored ) {
			return self::DEFAULTS;
		}
		return self::sanitize( $stored );
	}

	/**
	 * Always returns the seven weekdays, falling back per field to the defaults.
	 *
	 * @param mixed $raw Raw option value.
	 * @return array<int,array<string,mixed>>
	 */
	public static function sanitize( $raw ): array {
		$raw = is_array( $raw ) ? $raw : array();
		$out = array();
		for ( $weekday = 0; $weekday < Dates::DAYS_IN_WEEK; $weekday++ ) {
			$default = self::DEFAULTS[ $weekday ];
			$day     = $raw[ $weekday ] ?? ( $raw[ (string) $weekday ] ?? array() );
			$day     = is_array( $day ) ? $day : array();

			$lane = isset( $day['lane'] ) && is_string( $day['lane'] ) ? sanitize_title( $day['lane'] ) : '';
			$text = isset( $day['label'] ) && is_string( $day['label'] ) ? sanitize_text_field( $day['label'] ) : '';
			$time = isset( $day['time'] ) && is_string( $day['time'] ) && Dates::is_time( $day['time'] ) ? $day['time'] : $default['time'];

			$categories = $default['categories'];
			if ( isset( $day['categories'] ) && is_array( $day['categories'] ) ) {
				$categories = array();
				foreach ( $day['categories'] as $slug ) {
					$slug = is_string( $slug ) ? sanitize_title( $slug ) : '';
					if ( '' !== $slug ) {
						$categories[] = $slug;
					}
				}
			}

			$out[ $weekday ] = array(
				'lane'       => '' !== $lane ? $lane : $default['lane'],
				'label'      => '' !== $text ? $text : $default['label'],
				'categories' => $categories,
				'time'       => $time,
			);
		}
		return $out;
	}

	/**
	 * Writes the defaults when no rhythm exists, keeping only categories that exist.
	 *
	 * @return void
	 */
	public static function install_defaults(): void {
		if ( false !== get_option( self::OPTION, false ) ) {
			return;
		}
		$rhythm = self::DEFAULTS;
		foreach ( $rhythm as $weekday => $day ) {
			$kept = array();
			foreach ( $day['categories'] as $slug ) {
				if ( term_exists( $slug, 'category' ) ) {
					$kept[] = $slug;
				}
			}
			$rhythm[ $weekday ]['categories'] = $kept;
		}
		add_option( self::OPTION, $rhythm );
	}

	/**
	 * Deletes the option and installs the defaults again.
	 *
	 * @return void
	 */
	public static function reinstall_defaults(): void {
		delete_option( self::OPTION );
		self::install_defaults();
	}
}
