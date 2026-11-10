import { __, sprintf } from '@wordpress/i18n';

import { shortDayLabel, weekRange, weekdayMonthDayLabel } from '../data/dates';
import SuggestionCard from './SuggestionCard';

const DAYS_IN_WEEK = 7;

/**
 * The Desk rail: a week summary and the selected day's suggestions.
 *
 * @param {Object}      props              Props.
 * @param {Object[]}    props.days         Plan days.
 * @param {string}      props.today        Today.
 * @param {number}      props.weekStartsOn First weekday.
 * @param {string|null} props.selectedDate Selected date.
 * @param {Object|null} props.result       suggest-for-date result for the selected date.
 * @param {Function}    props.onPlace      Called with a suggestion.
 * @param {Function}    props.onShare      Called with a suggestion.
 * @param {Function}    props.onDismiss    Called with a suggestion.
 * @param {Function}    props.onPlan       Called with a suggestion ("Plan this").
 * @return {Element} The rail content.
 */
export default function DeskRail( {
	days,
	today,
	weekStartsOn,
	selectedDate,
	result,
	onPlan,
	onPlace,
	onShare,
	onDismiss,
} ) {
	const byDate = new Map( days.map( ( day ) => [ day.date, day ] ) );
	const week = weekRange( today, weekStartsOn ).map( ( date ) => ( {
		date,
		day: byDate.get( date ) || null,
	} ) );
	const withPost = week.filter( ( entry ) => entry.day?.posts.length > 0 );
	const nextGap = week.find(
		( entry ) =>
			entry.date >= today && entry.day && entry.day.posts.length === 0
	);

	const selected = selectedDate ? byDate.get( selectedDate ) : null;
	let body;
	if ( ! selectedDate ) {
		body = (
			<p>{ __( 'Select a day to see its suggestions.', 'masthead' ) }</p>
		);
	} else if ( selected && selected.posts.length > 0 ) {
		body = <p>{ __( 'This day has a post.', 'masthead' ) }</p>;
	} else if ( ! result || result.suggestions.length === 0 ) {
		body = <p>{ __( 'No suggestions for this day.', 'masthead' ) }</p>;
	} else {
		body = result.suggestions.map( ( suggestion ) => (
			<SuggestionCard
				key={ suggestion.key }
				suggestion={ suggestion }
				onPlan={ onPlan }
				onPlace={ onPlace }
				onShare={ onShare }
				onDismiss={ onDismiss }
			/>
		) );
	}

	return (
		<section
			className="masthead-desk"
			aria-label={ __( 'Desk', 'masthead' ) }
		>
			<h2>
				{ selectedDate
					? sprintf(
							/* translators: %s: short day label. */
							__( 'Desk · %s', 'masthead' ),
							weekdayMonthDayLabel( selectedDate )
						)
					: __( 'Desk', 'masthead' ) }
			</h2>
			<div className="masthead-desk__week">
				<h3>{ __( 'This week', 'masthead' ) }</h3>
				<div className="masthead-desk__bars" aria-hidden="true">
					{ week.map( ( entry ) => (
						<span
							key={ entry.date }
							className={ `masthead-desk__bar${
								entry.day?.posts.length ? '' : ' is-empty'
							}` }
							style={
								entry.day?.posts.length
									? {
											backgroundColor:
												entry.day.posts[ 0 ].color,
										}
									: undefined
							}
						/>
					) ) }
				</div>
				<p>
					{ sprintf(
						/* translators: 1: number of days with a post, 2: days in a week. */
						__( '%1$d of %2$d days have a post.', 'masthead' ),
						withPost.length,
						DAYS_IN_WEEK
					) }{ ' ' }
					{ nextGap
						? sprintf(
								/* translators: %s: day label, such as "Tue Nov 10". */
								__( 'Next gap: %s.', 'masthead' ),
								shortDayLabel( nextGap.date )
							)
						: __( 'No gap left this week.', 'masthead' ) }
				</p>
			</div>
			{ selectedDate && selected && selected.posts.length === 0 ? (
				<h3 className="masthead-desk__day">
					{ sprintf(
						/* translators: 1: day label, 2: lane label. */
						__( '%1$s · %2$s lane', 'masthead' ),
						weekdayMonthDayLabel( selectedDate ),
						selected.lane.label
					) }
				</h3>
			) : null }
			{ body }
		</section>
	);
}
