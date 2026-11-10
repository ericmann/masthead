<?php
/**
 * Posts > Calendar page.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Admin;

use const Masthead\SLUG;

/**
 * Registers and renders the calendar page.
 */
final class CalendarPage {

	/**
	 * Constructor.
	 *
	 * @param Assets $assets Assets.
	 */
	public function __construct( private Assets $assets ) {}

	/**
	 * Adds the submenu page under Posts.
	 *
	 * @return void
	 */
	public function register(): void {
		$hook = add_submenu_page(
			'edit.php',
			__( 'Calendar', 'masthead' ),
			__( 'Calendar', 'masthead' ),
			'edit_posts',
			SLUG,
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
		echo '<div class="wrap"><div id="' . esc_attr( 'masthead-calendar-root' ) . '"></div></div>';
	}
}
