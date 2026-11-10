import { useState } from '@wordpress/element';
import { gmdateI18n } from '@wordpress/date';
import { __, _n, sprintf } from '@wordpress/i18n';

import { dayLabel } from '../data/dates';
import GhostCard from './GhostCard';
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
 * @param {Function}    props.onMovePost   Called with a post and a date (Move to).
 * @param {Function}    props.onDropPost   Called with a post ID and this date after a drop.
 * @param {Object|null} props.ghost        { suggestion, color } shown instead of the open slot.
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
	onMovePost,
	onDropPost,
	ghost = null,
} ) {
	const [ isOver, setIsOver ] = useState( false );
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
	const showGhost = day.open && ghost;
	let ariaLabel;
	if ( showGhost ) {
		ariaLabel = sprintf(
			/* translators: 1: weekday, month and day; 2: suggestion kind; 3: suggestion title. */
			__( '%1$s: suggested %2$s, %3$s', 'masthead' ),
			label,
			ghost.suggestion.kind,
			ghost.suggestion.title
		);
	} else if ( day.open ) {
		ariaLabel = sprintf(
			/* translators: 1: weekday, month and day; 2: lane label. */
			__( '%1$s: open %2$s slot', 'masthead' ),
			label,
			day.lane.label
		);
	} else {
		ariaLabel = sprintf(
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
	}

	const classes = [
		'masthead-day',
		isToday ? 'is-today' : '',
		isSelected ? 'is-selected' : '',
		inMonth ? '' : 'is-outside',
		isOver ? 'is-drop-target' : '',
	]
		.filter( Boolean )
		.join( ' ' );

	return (
		<div
			className={ classes }
			data-date={ day.date }
			onDragOver={
				onDropPost ? ( event ) => event.preventDefault() : undefined
			}
			onDragEnter={ onDropPost ? () => setIsOver( true ) : undefined }
			onDragLeave={ onDropPost ? () => setIsOver( false ) : undefined }
			onDrop={
				onDropPost
					? ( event ) => {
							event.preventDefault();
							setIsOver( false );
							const id = Number(
								event.dataTransfer.getData( 'text/plain' )
							);
							if ( id ) {
								onDropPost( id, day.date );
							}
						}
					: undefined
			}
		>
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
			<span className="masthead-day__meta" aria-hidden="true">
				{ [
					gmdateI18n( 'D', new Date( Date.parse( day.date ) ) ),
					day.lane.label,
				]
					.filter( Boolean )
					.join( ' · ' ) }
			</span>
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
					onMove={ onMovePost }
				/>
			) ) }
			{ showGhost ? (
				<GhostCard
					suggestion={ ghost.suggestion }
					color={ ghost.color }
					onSelect={ () => onSelectDay( day.date ) }
				/>
			) : null }
			{ day.open && ! showGhost ? (
				<OpenSlot laneLabel={ day.lane.label } />
			) : null }
		</div>
	);
}
