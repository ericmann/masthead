import { gmdateI18n } from '@wordpress/date';
import { __, sprintf } from '@wordpress/i18n';

const DAYS_IN_WEEK = 7;
// 1970-01-04 was a Sunday: weekday names come from it.
const SUNDAY_1970 = Date.UTC( 1970, 0, 4 );
const MS_PER_DAY = 86400000;

/**
 * One line naming each lane and its weekdays, such as "Sun Faith · Tue/Thu Technical series".
 * Weekdays that share a lane label are joined with a slash, in week order from the site's week start.
 *
 * @param {Object|Array} rhythm       Rhythm keyed by weekday 0..6.
 * @param {number}       weekStartsOn First weekday, 0 = Sunday.
 * @return {string} The summary, or an empty string when there is no rhythm.
 */
export function rhythmSummary( rhythm, weekStartsOn = 0 ) {
	const groups = [];
	for ( let i = 0; i < DAYS_IN_WEEK; i++ ) {
		const weekday = ( weekStartsOn + i ) % DAYS_IN_WEEK;
		const label = rhythm?.[ weekday ]?.label;
		if ( ! label ) {
			continue;
		}
		const name = gmdateI18n(
			'D',
			new Date( SUNDAY_1970 + weekday * MS_PER_DAY )
		);
		const group = groups.find( ( item ) => item.label === label );
		if ( group ) {
			group.days.push( name );
		} else {
			groups.push( { label, days: [ name ] } );
		}
	}
	return groups
		.map( ( group ) =>
			sprintf(
				/* translators: 1: weekday names such as "Tue/Thu", 2: lane label. */
				__( '%1$s %2$s', 'masthead' ),
				group.days.join( '/' ),
				group.label
			)
		)
		.join( ' · ' );
}

/**
 * Page header: the title, Add New Post, and the weekly rhythm on one line.
 *
 * @param {Object}       props              Props.
 * @param {string}       props.newPostUrl   post-new.php URL, or an empty string to hide the button.
 * @param {Object|Array} props.rhythm       Rhythm keyed by weekday.
 * @param {number}       props.weekStartsOn First weekday.
 * @return {Element} The header.
 */
export default function Header( { newPostUrl = '', rhythm, weekStartsOn } ) {
	const summary = rhythmSummary( rhythm, weekStartsOn );
	return (
		<div className="masthead-header">
			<h1 className="wp-heading-inline">
				{ __( 'Calendar', 'masthead' ) }
			</h1>
			{ newPostUrl ? (
				<a className="page-title-action" href={ newPostUrl }>
					{ __( 'Add New Post', 'masthead' ) }
				</a>
			) : null }
			{ summary ? (
				<p className="masthead-header__rhythm">
					{ sprintf(
						/* translators: %s: lanes by weekday, such as "Mon Business · Tue/Thu Technical series". */
						__( 'Rhythm: %s', 'masthead' ),
						summary
					) }
				</p>
			) : null }
		</div>
	);
}
