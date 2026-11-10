import { gmdateI18n } from '@wordpress/date';

const MS_PER_DAY = 86400000;
const DAYS_IN_WEEK = 7;

function parse( ymd ) {
	const [ year, month, day ] = ymd.split( '-' ).map( Number );
	return Date.UTC( year, month - 1, day );
}

function format( ms ) {
	const date = new Date( ms );
	const pad = ( n ) => String( n ).padStart( 2, '0' );
	return `${ date.getUTCFullYear() }-${ pad( date.getUTCMonth() + 1 ) }-${ pad(
		date.getUTCDate()
	) }`;
}

/**
 * Adds calendar days.
 *
 * @param {string} ymd  Date, YYYY-MM-DD.
 * @param {number} days Signed day count.
 * @return {string} Date.
 */
export function addDays( ymd, days ) {
	return format( parse( ymd ) + days * MS_PER_DAY );
}

/**
 * Signed days from one date to another.
 *
 * @param {string} from Date.
 * @param {string} to   Date.
 * @return {number} Days.
 */
export function diffDays( from, to ) {
	return Math.round( ( parse( to ) - parse( from ) ) / MS_PER_DAY );
}

/**
 * Weekday, 0 = Sunday.
 *
 * @param {string} ymd Date.
 * @return {number} Weekday.
 */
export function weekday( ymd ) {
	return new Date( parse( ymd ) ).getUTCDay();
}

function startOfWeek( ymd, weekStartsOn ) {
	const back =
		( weekday( ymd ) - weekStartsOn + DAYS_IN_WEEK ) % DAYS_IN_WEEK;
	return addDays( ymd, -back );
}

/**
 * Seven consecutive dates of the week holding a date.
 *
 * @param {string} ymd          Date.
 * @param {number} weekStartsOn Weekday the week starts on.
 * @return {string[]} Dates.
 */
export function weekRange( ymd, weekStartsOn ) {
	const start = startOfWeek( ymd, weekStartsOn );
	return Array.from( { length: DAYS_IN_WEEK }, ( _, i ) =>
		addDays( start, i )
	);
}

/**
 * Whole weeks covering the month of a date.
 *
 * @param {string} ymd          Date inside the month.
 * @param {number} weekStartsOn Weekday the week starts on.
 * @return {string[][]} Weeks of seven dates.
 */
export function monthGrid( ymd, weekStartsOn ) {
	const first = `${ ymd.slice( 0, 7 ) }-01`;
	const nextMonth = new Date( parse( first ) );
	nextMonth.setUTCMonth( nextMonth.getUTCMonth() + 1 );
	const last = addDays( format( nextMonth.getTime() ), -1 );
	const start = startOfWeek( first, weekStartsOn );
	const total = diffDays( start, last ) + 1;
	const weeks = Math.ceil( total / DAYS_IN_WEEK );
	return Array.from( { length: weeks }, ( _, w ) =>
		Array.from( { length: DAYS_IN_WEEK }, ( __, d ) =>
			addDays( start, w * DAYS_IN_WEEK + d )
		)
	);
}

/**
 * Month and year, localised.
 *
 * @param {string} ymd Date.
 * @return {string} Label such as "November 2026".
 */
export function monthLabel( ymd ) {
	return gmdateI18n( 'F Y', new Date( parse( ymd ) ) );
}

/**
 * Long weekday, month and day, localised.
 *
 * @param {string} ymd Date.
 * @return {string} Label such as "Monday, November 9".
 */
export function dayLabel( ymd ) {
	return gmdateI18n( 'l, F j', new Date( parse( ymd ) ) );
}

/**
 * Time of day as written in an ISO 8601 string (site time), localised.
 *
 * @param {string|null} iso Datetime with offset.
 * @return {string} Label such as "7:30 am", or an empty string.
 */
export function timeLabel( iso ) {
	const match = /T(\d{2}):(\d{2})/.exec( iso || '' );
	if ( ! match ) {
		return '';
	}
	return gmdateI18n(
		'g:i a',
		new Date(
			Date.UTC( 1970, 0, 1, Number( match[ 1 ] ), Number( match[ 2 ] ) )
		)
	);
}

/**
 * Short weekday, month and day, localised.
 *
 * @param {string} ymd Date.
 * @return {string} Label such as "Wed Nov 11".
 */
export function shortDayLabel( ymd ) {
	return gmdateI18n( 'D M j', new Date( parse( ymd ) ) );
}

/**
 * A date range as a title.
 *
 * @param {string} from First date.
 * @param {string} to   Last date.
 * @return {string} Label such as "Nov 8 – Nov 14, 2026".
 */
export function rangeLabel( from, to ) {
	return `${ gmdateI18n( 'M j', new Date( parse( from ) ) ) } – ${ gmdateI18n(
		'M j, Y',
		new Date( parse( to ) )
	) }`;
}
