<?php
/**
 * Admin notices.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Admin;

/**
 * Tells the reader when the client build is missing.
 */
final class Notices {

	/**
	 * Constructor.
	 *
	 * @param Assets $assets Assets.
	 */
	public function __construct( private Assets $assets ) {}

	/**
	 * Prints the notice on Masthead screens only.
	 *
	 * @return void
	 */
	public function render(): void {
		$screen = get_current_screen();
		if ( ! is_object( $screen ) || ! $this->assets->is_page_hook( (string) $screen->id ) ) {
			return;
		}
		if ( $this->assets->build_exists() ) {
			return;
		}
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Masthead has no client build. Run npm run build.', 'masthead' ) . '</p></div>';
	}
}
