import { getBoot } from './boot';

/**
 * The pinned or real "now" from boot data, in site time.
 *
 * @return {Date} A date for the boot instant.
 */
export function now() {
	return new Date( getBoot().now );
}

/**
 * Today's date in the site timezone.
 *
 * @return {string} YYYY-MM-DD.
 */
export function today() {
	return getBoot().today;
}
