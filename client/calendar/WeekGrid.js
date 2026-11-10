import { gmdateI18n } from '@wordpress/date';
import { __, sprintf } from '@wordpress/i18n';

import { weekRange } from '../data/dates';
import DayCell from './DayCell';

/**
 * Week view: seven day columns headed by weekday, date and lane label.
 *
 * @param {Object}      props              Props.
 * @param {string}      props.anchor       Date inside the shown week.
 * @param {number}      props.weekStartsOn First weekday, 0 = Sunday.
 * @param {Object[]}    props.days         Plan days.
 * @param {string}      props.today        Today.
 * @param {string|null} props.selectedDate Selected date.
 * @param {Set<string>} props.hidden       Hidden category slugs.
 * @param {Function}    props.onSelectDay  Called with a date.
 * @param {Function}    props.onSelectPost Called with a post.
 * @param {Function}    props.onMovePost   Called with a post and a date (Move to).
 * @param {Function}    props.onDropPost   Called with a post ID and a date after a drop.
 * @param {Object}      props.ghosts       Ghost { suggestion, color } by date.
 * @return {Element} The week.
 */
export default function WeekGrid( {
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
	ghosts = {},
} ) {
	const byDate = new Map( days.map( ( day ) => [ day.date, day ] ) );
	const dates = weekRange( anchor, weekStartsOn );

	return (
		<section
			className="masthead-week"
			aria-label={ __( 'Week', 'masthead' ) }
		>
			{ dates.map( ( date ) => {
				const day = byDate.get( date ) || {
					date,
					lane: { slug: '', label: '', categories: [] },
					posts: [],
					open: false,
				};
				const [ y, m, d ] = date.split( '-' ).map( Number );
				const head = gmdateI18n(
					'D j',
					new Date( Date.UTC( y, m - 1, d ) )
				);
				return (
					<div key={ date } className="masthead-week__column">
						<div
							className="masthead-week__head"
							title={ sprintf(
								/* translators: 1: weekday and day number, such as "Sun 15"; 2: lane label. */
								__( '%1$s · %2$s', 'masthead' ),
								head,
								day.lane.label
							) }
						>
							<span className="masthead-grid__dow">{ head }</span>
							{ day.lane.label ? (
								<span className="masthead-grid__lane">
									{ ' · ' + day.lane.label }
								</span>
							) : null }
						</div>
						<DayCell
							day={ day }
							isToday={ date === today }
							isPast={ date < today }
							isSelected={ date === selectedDate }
							hidden={ hidden }
							onSelectDay={ onSelectDay }
							onSelectPost={ onSelectPost }
							onMovePost={ onMovePost }
							onDropPost={ onDropPost }
							ghost={ ghosts[ date ] || null }
						/>
					</div>
				);
			} ) }
		</section>
	);
}
