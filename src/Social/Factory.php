<?php
/**
 * Share source factory.
 *
 * @package Masthead
 */

declare(strict_types=1);

namespace Masthead\Social;

/**
 * Chooses the share source.
 */
final class Factory {

	/**
	 * Builds the source.
	 *
	 * @param bool|null $usable Whether Jetpack Social is usable; defaults to JetpackSocial::usable(). Tests pass it.
	 * @return ShareSource
	 */
	public static function make( ?bool $usable = null ): ShareSource {
		return ( $usable ?? JetpackSocial::usable() ) ? new JetpackSocial() : new NullShare();
	}
}
