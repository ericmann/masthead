<?php
/**
 * NullShare tests.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Tests\Unit\Social;

use Masthead\Social\NullShare;
use Masthead\Tests\Unit\TestCase;

/**
 * NullShare tests.
 */
final class NullShareTest extends TestCase {

	public function test_is_unavailable_with_a_reason(): void {
		$share = new NullShare();
		$this->assertFalse( $share->available() );
		$this->assertSame( array(), $share->connections() );
		foreach ( array( $share->share_now( 5, 'Hi', array( 1 ) ), $share->share_at( 5, 'Hi', array( 1 ), '2026-11-12T09:00:00-08:00' ) ) as $result ) {
			$this->assertSame( 'unavailable', $result['status'] );
			$this->assertFalse( $result['results'][0]['ok'] );
			$this->assertSame( 'Jetpack Social is not connected on this site.', $result['results'][0]['message'] );
		}
	}
}
