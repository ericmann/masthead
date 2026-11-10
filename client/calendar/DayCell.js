import { __, _n, sprintf } from '@wordpress/i18n';

import { dayLabel } from '../data/dates';
import OpenSlot from './OpenSlot';
import PostChip from './PostChip';

/**
 * One day of the calendar grid.
 *
 * @param {Object}      props              Props.
 * @param {Object}      props.day          Day { date, lane, posts, open }.
 * @param {boolean}     props.isToday      Whether the day is today.
 * @param {boolean}     props.isSelected   Whether the day is selected.
 * @param {boolean}     props.inMonth      Whether the day belongs to the shown month.
 * @param {Set<string>} props.hidden       Hidden category slugs.
 * @param {Function}    props.onSelectDay  Called with the date.
 * @param {Function}    props.onSelectPost Called with a post.
 * @return {Element} The cell.
 */
export default function DayCell( {
	day,
	isToday,
	isSelected,
	inMonth = true,
	hidden = new Set(),
	onSelectDay,
	onSelectPost,
} ) {
	const posts = day.posts.filter(
		( post ) =>
			! post.primary_category || ! hidden.has( post.primary_category )
	);
	const label = isToday
		? sprintf(
				/* translators: %s: weekday, month and day. */
				__( '%s, today', 'masthead' ),
				dayLabel( day.date )
			)
		: dayLabel( day.date );
	const ariaLabel = day.open
		? sprintf(
				/* translators: 1: weekday, month and day; 2: lane label. */
				__( '%1$s: open %2$s slot', 'masthead' ),
				label,
				day.lane.label
			)
		: sprintf(
				/* translators: 1: weekday, month and day; 2: number of posts. */
				_n(
					'%1$s: %2$d post',
					'%1$s: %2$d posts',
					posts.length,
					'masthead'
				),
				label,
				posts.length
			);

	const classes = [
		'masthead-day',
		isToday ? 'is-today' : '',
		isSelected ? 'is-selected' : '',
		inMonth ? '' : 'is-outside',
	]
		.filter( Boolean )
		.join( ' ' );

	return (
		<div className={ classes } data-date={ day.date }>
			<button
				type="button"
				className="masthead-day__button"
				aria-label={ ariaLabel }
				aria-pressed={ isSelected }
				onClick={ () => onSelectDay( day.date ) }
			>
				<span className="masthead-day__number">
					{ Number( day.date.slice( -2 ) ) }
				</span>
			</button>
			{ isToday ? (
				<span className="masthead-day__today" aria-hidden="true">
					{ __( 'TODAY', 'masthead' ) }
				</span>
			) : null }
			{ posts.map( ( post ) => (
				<PostChip
					key={ post.id }
					post={ post }
					onSelect={ onSelectPost }
				/>
			) ) }
			{ day.open ? <OpenSlot laneLabel={ day.lane.label } /> : null }
		</div>
	);
}
