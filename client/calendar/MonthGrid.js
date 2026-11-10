import { gmdateI18n } from '@wordpress/date';
import { __, sprintf } from '@wordpress/i18n';

import { getBoot } from '../data/boot';
import { monthGrid, monthLabel } from '../data/dates';
import DayCell from './DayCell';

// 1970-01-04 was a Sunday: weekday names come from it.
const SUNDAY_1970 = Date.UTC( 1970, 0, 4 );
const MS_PER_DAY = 86400000;
const DAYS_IN_WEEK = 7;

/**
 * Month view: a weekday header with lane labels, then weeks of day cells.
 *
 * @param {Object}      props              Props.
 * @param {string}      props.anchor       Date inside the shown month.
 * @param {number}      props.weekStartsOn First weekday, 0 = Sunday.
 * @param {Object[]}    props.days         Plan days.
 * @param {string}      props.today        Today.
 * @param {string|null} props.selectedDate Selected date.
 * @param {Set<string>} props.hidden       Hidden category slugs.
 * @param {Function}    props.onSelectDay  Called with a date.
 * @param {Function}    props.onSelectPost Called with a post.
 * @param {Function}    props.onMovePost   Called with a post and a date (Move to).
 * @param {Function}    props.onDropPost   Called with a post ID and a date after a drop.
 * @return {Element} The grid.
 */
export default function MonthGrid( {
	anchor,
	weekStartsOn,
	days,
	today,
	selectedDate,
	hidden,
	onSelectDay,
	onSelectPost,
	onMovePost,
	onDropPost,
} ) {
	const rhythm = getBoot().rhythm || {};
	const byDate = new Map( days.map( ( day ) => [ day.date, day ] ) );
	const weeks = monthGrid( anchor, weekStartsOn );
	const month = anchor.slice( 0, 7 );
	const title = monthLabel( anchor );

	const header = Array.from( { length: DAYS_IN_WEEK }, ( _, i ) => {
		const weekday = ( weekStartsOn + i ) % DAYS_IN_WEEK;
		const name = gmdateI18n(
			'D',
			new Date( SUNDAY_1970 + weekday * MS_PER_DAY )
		);
		const lane = rhythm[ weekday ]?.label || '';
		return {
			weekday,
			text: lane
				? sprintf(
						/* translators: 1: short weekday name, 2: lane label. */
						__( '%1$s · %2$s', 'masthead' ),
						name,
						lane
					)
				: name,
		};
	} );

	return (
		<section className="masthead-grid" aria-label={ title }>
			<div className="masthead-grid__head">
				{ header.map( ( item ) => (
					<div
						key={ item.weekday }
						className="masthead-grid__weekday"
					>
						{ item.text }
					</div>
				) ) }
			</div>
			{ weeks.map( ( week ) => (
				<div key={ week[ 0 ] } className="masthead-grid__week">
					{ week.map( ( date ) => {
						const day = byDate.get( date ) || {
							date,
							lane: { slug: '', label: '', categories: [] },
							posts: [],
							open: false,
						};
						return (
							<DayCell
								key={ date }
								day={ day }
								isToday={ date === today }
								isSelected={ date === selectedDate }
								inMonth={ date.slice( 0, 7 ) === month }
								hidden={ hidden }
								onSelectDay={ onSelectDay }
								onSelectPost={ onSelectPost }
								onMovePost={ onMovePost }
								onDropPost={ onDropPost }
							/>
						);
					} ) }
				</div>
			) ) }
		</section>
	);
}
