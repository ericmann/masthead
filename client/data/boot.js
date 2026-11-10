let boot = null;

/**
 * Boot data printed by PHP as window.mastheadBoot. Read once.
 *
 * @return {Object} Boot data.
 */
export function getBoot() {
	if ( boot === null ) {
		boot = window.mastheadBoot || {};
	}
	return boot;
}

/**
 * Forgets the cached boot data. For tests.
 */
export function resetBoot() {
	boot = null;
}
