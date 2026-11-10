<?php
/**
 * CalendarPage tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use Masthead\Admin\Assets;
use Masthead\Admin\CalendarPage;
use Masthead\Support\Clock;
use Masthead\Tests\Unit\TestCase;

/**
 * CalendarPage tests.
 */
final class CalendarPageTest extends TestCase {

	public function test_registers_submenu_under_posts_with_edit_posts(): void {
		$assets = new Assets( new Clock(), '/tmp/masthead.php' );
		$page   = new CalendarPage( $assets );
		Functions\expect( 'add_submenu_page' )->once()->with( 'edit.php', 'Calendar', 'Calendar', 'edit_posts', 'masthead', array( $page, 'render' ) )->andReturn( 'posts_page_masthead' );
		$page->register();
		$this->assertTrue( $assets->is_page_hook( 'posts_page_masthead' ) );
	}

	public function test_render_outputs_the_react_root(): void {
		$page = new CalendarPage( new Assets( new Clock(), '/tmp/masthead.php' ) );
		ob_start();
		$page->render();
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( '<div id="masthead-calendar-root"></div>', $html );
		$this->assertStringContainsString( 'class="wrap"', $html );
	}
}
