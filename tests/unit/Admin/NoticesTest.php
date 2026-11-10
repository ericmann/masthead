<?php
/**
 * Notices tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use Masthead\Admin\Assets;
use Masthead\Admin\Notices;
use Masthead\Support\Clock;
use Masthead\Tests\Unit\TestCase;

/**
 * Notices tests.
 */
final class NoticesTest extends TestCase {

	public function test_notice_shown_only_on_masthead_screen_when_build_missing(): void {
		$assets = new Assets( new Clock(), '/nonexistent/masthead.php' );
		$assets->add_page_hook( 'posts_page_masthead' );
		$notices = new Notices( $assets );

		Functions\when( 'get_current_screen' )->justReturn( (object) array( 'id' => 'dashboard' ) );
		ob_start();
		$notices->render();
		$this->assertSame( '', ob_get_clean() );

		Functions\when( 'get_current_screen' )->justReturn( (object) array( 'id' => 'posts_page_masthead' ) );
		ob_start();
		$notices->render();
		$this->assertStringContainsString( 'npm run build', (string) ob_get_clean() );

		$present = new Notices( new Assets( new Clock(), dirname( __DIR__ ) . '/fixtures/plugin/masthead.php' ) );
		ob_start();
		$present->render();
		$this->assertSame( '', ob_get_clean() );
	}
}
