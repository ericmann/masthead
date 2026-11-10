import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { getBoot } from '../data/boot';
import { today as bootToday } from '../data/clock';
import {
	addDays,
	monthGrid,
	monthLabel,
	rangeLabel,
	weekRange,
} from '../data/dates';
import { usePlan } from '../data/usePlan';
import { runAbility } from '../data/useAbility';
import DayPlan from './DayPlan';
import DeskRail from './DeskRail';
import DraftTray from './DraftTray';
import SharePanel from './SharePanel';
import SwapPanel from './SwapPanel';
import { useFindings } from './useFindings';
import { useReschedule } from './useReschedule';
import { useSuggestions } from './useSuggestions';
import ListView from './ListView';
import MonthGrid from './MonthGrid';
import Toolbar from './Toolbar';
import WeekGrid from './WeekGrid';

const VIEWS = [ 'month', 'week', 'list' ];

function viewFromHash() {
	const view = window.location.hash.replace( '#', '' );
	return VIEWS.includes( view ) ? view : 'month';
}

function shiftMonth( ymd, delta ) {
	const [ year, month ] = ymd.split( '-' ).map( Number );
	return new Date( Date.UTC( year, month - 1 + delta, 1 ) )
		.toISOString()
		.slice( 0, 10 );
}

function rangeFor( view, anchor, weekStartsOn ) {
	if ( view === 'week' ) {
		const week = weekRange( anchor, weekStartsOn );
		return [ week[ 0 ], week[ week.length - 1 ] ];
	}
	if ( view === 'list' ) {
		const span = Number( getBoot().config.maxRangeDays );
		return [ anchor, addDays( anchor, span - 1 ) ];
	}
	const weeks = monthGrid( anchor, weekStartsOn );
	return [
		weeks[ 0 ][ 0 ],
		weeks[ weeks.length - 1 ][ weeks[ 0 ].length - 1 ],
	];
}

export default function App() {
	const weekStartsOn = Number( getBoot().weekStartsOn ?? 0 );
	const [ view, setView ] = useState( viewFromHash );
	const [ anchor, setAnchor ] = useState( bootToday );
	const [ selection, setSelection ] = useState( null );
	const [ hidden, setHidden ] = useState( () => new Set() );
	const toggleCategory = useCallback( ( slug ) => {
		setHidden( ( current ) => {
			const next = new Set( current );
			if ( next.has( slug ) ) {
				next.delete( slug );
			} else {
				next.add( slug );
			}
			return next;
		} );
	}, [] );

	useEffect( () => {
		const onHash = () => setView( viewFromHash() );
		window.addEventListener( 'hashchange', onHash );
		return () => window.removeEventListener( 'hashchange', onHash );
	}, [] );

	const [ from, to ] = useMemo(
		() => rangeFor( view, anchor, weekStartsOn ),
		[ view, anchor, weekStartsOn ]
	);
	const plan = usePlan( from, to );
	const suggestions = useSuggestions();
	const findings = useFindings();
	const boot = getBoot();
	const today = bootToday();
	const ghostEnd = addDays( today, Number( boot.config?.ghostDays ) );
	const horizonEnd = addDays( today, Number( boot.config?.horizonDays ) );
	const ghostDates = plan.days
		.filter(
			( day ) => day.open && day.date >= today && day.date <= ghostEnd
		)
		.map( ( day ) => day.date );
	const ghostKey = ghostDates.join( ',' );
	const { loadMany } = suggestions;
	useEffect( () => {
		if ( ghostKey ) {
			loadMany( ghostKey.split( ',' ) );
		}
	}, [ ghostKey, loadMany ] );

	const ghosts = {};
	plan.days.forEach( ( day ) => {
		const first = suggestions.get( day.date )?.suggestions?.[ 0 ];
		if ( first ) {
			const slug = day.lane?.categories?.[ 0 ];
			const color =
				( boot.categories || [] ).find(
					( category ) => category.slug === slug
				)?.color || '#646970';
			ghosts[ day.date ] = { suggestion: first, color };
		}
	} );
	const selectedDay = selection?.type === 'day' ? selection.date : null;
	const { load } = suggestions;
	useEffect( () => {
		if ( selectedDay ) {
			load( selectedDay );
		}
	}, [ selectedDay, load ] );
	const [ planning, setPlanning ] = useState( null );
	useEffect( () => {
		setPlanning( null );
	}, [ selectedDay ] );
	const dismissSuggestion = async ( suggestion ) => {
		await runAbility( 'masthead/dismiss-suggestion', {
			date: selectedDay,
			key: suggestion.key,
		} );
		suggestions.invalidate( selectedDay );
		await suggestions.load( selectedDay );
	};
	const placeSuggestion = async ( suggestion ) => {
		await move( { id: suggestion.post_id, date: null }, selectedDay );
		suggestions.invalidate( selectedDay );
	};
	const shareSuggestion = ( suggestion ) =>
		setSelection( {
			type: 'post',
			post: {
				id: suggestion.post_id,
				title: suggestion.title,
				status: 'publish',
				date: null,
			},
		} );
	const fillOpenDays = () =>
		suggestions.loadMany(
			plan.days
				.filter( ( day ) => day.open && day.date <= horizonEnd )
				.map( ( day ) => day.date )
		);
	const fillStatus = suggestions.isRunning
		? sprintf(
				/* translators: 1: number done, 2: total. */
				__( 'Suggesting for open days: %1$d of %2$d', 'masthead' ),
				suggestions.progress.done,
				suggestions.progress.total
			)
		: '';
	const { move, error: moveError, clearError } = useReschedule( plan );
	const onDropPost = useCallback(
		( postId, date ) => {
			const post =
				plan.unscheduled.find( ( item ) => item.id === postId ) ||
				plan.days
					.flatMap( ( day ) => day.posts )
					.find( ( item ) => item.id === postId );
			if ( post ) {
				move( post, date );
			}
		},
		[ plan, move ]
	);

	const step = useCallback(
		( direction ) => {
			setAnchor( ( current ) => {
				if ( view === 'week' ) {
					return addDays( current, direction * 7 );
				}
				if ( view === 'list' ) {
					return addDays(
						current,
						direction * Number( getBoot().config.maxRangeDays )
					);
				}
				return shiftMonth( current, direction );
			} );
		},
		[ view ]
	);

	const onView = useCallback( ( next ) => {
		window.location.hash = next;
		setView( next );
	}, [] );

	const selectedDate = selection?.type === 'day' ? selection.date : null;

	return (
		<div className="masthead-calendar">
			<h1>{ __( 'Calendar', 'masthead' ) }</h1>
			<Toolbar
				view={ view }
				title={
					view === 'month'
						? monthLabel( anchor )
						: rangeLabel( from, to )
				}
				onPrev={ () => step( -1 ) }
				onNext={ () => step( 1 ) }
				onToday={ () => setAnchor( bootToday() ) }
				onView={ onView }
				categories={ getBoot().categories || [] }
				hidden={ hidden }
				onToggleCategory={ toggleCategory }
				onFillOpenDays={ fillOpenDays }
				fillStatus={ fillStatus }
				filling={ suggestions.isRunning }
			/>
			{ plan.error ? (
				<div className="notice notice-error inline" role="alert">
					<p>{ plan.error.message }</p>
				</div>
			) : null }
			{ plan.truncated ? (
				<div className="notice notice-warning inline" role="status">
					<p>
						{ __(
							'This range has more posts than can be shown. Narrow the range.',
							'masthead'
						) }
					</p>
				</div>
			) : null }
			{ moveError ? (
				<div className="notice notice-error inline" role="alert">
					<p>{ moveError.message }</p>
					<button
						type="button"
						className="notice-dismiss"
						onClick={ clearError }
					>
						<span className="screen-reader-text">
							{ __( 'Dismiss this notice.', 'masthead' ) }
						</span>
					</button>
				</div>
			) : null }
			<div className="masthead-layout">
				<div className="masthead-main">
					{ view === 'month' ? (
						<MonthGrid
							anchor={ anchor }
							weekStartsOn={ weekStartsOn }
							days={ plan.days }
							today={ bootToday() }
							selectedDate={ selectedDate }
							hidden={ hidden }
							onSelectDay={ ( date ) =>
								setSelection( { type: 'day', date } )
							}
							onSelectPost={ ( post ) =>
								setSelection( { type: 'post', post } )
							}
							onMovePost={ move }
							onDropPost={ onDropPost }
							ghosts={ ghosts }
						/>
					) : null }
					{ view === 'week' ? (
						<WeekGrid
							anchor={ anchor }
							weekStartsOn={ weekStartsOn }
							days={ plan.days }
							today={ bootToday() }
							selectedDate={ selectedDate }
							hidden={ hidden }
							onSelectDay={ ( date ) =>
								setSelection( { type: 'day', date } )
							}
							onSelectPost={ ( post ) =>
								setSelection( { type: 'post', post } )
							}
							onMovePost={ move }
							onDropPost={ onDropPost }
							ghosts={ ghosts }
						/>
					) : null }
					{ view === 'list' ? (
						<ListView
							findings={ findings }
							days={ plan.days }
							hidden={ hidden }
							onSelectPost={ ( post ) =>
								setSelection( { type: 'post', post } )
							}
							onMovePost={ move }
							onDropPost={ onDropPost }
							ghosts={ ghosts }
						/>
					) : null }
				</div>
				<aside
					className="masthead-rail"
					aria-label={ __( 'Desk', 'masthead' ) }
				>
					{ selection?.type === 'post' &&
					selection.post.status === 'publish' &&
					getBoot().caps?.publishPosts ? (
						<SharePanel
							key={ selection.post.id }
							post={ selection.post }
						/>
					) : null }
					{ selection?.type === 'post' &&
					( selection.post.status === 'future' ||
						selection.post.status === 'draft' ) ? (
						<SwapPanel
							key={ selection.post.id }
							post={ selection.post }
							findings={ findings.byPost( selection.post.id ) }
							onApplied={ async () => {
								await plan.refresh();
								await findings.refresh();
							} }
							onClose={ () => setSelection( null ) }
						/>
					) : null }
					{ selection?.type === 'post' ? null : (
						<>
							{ planning && selectedDay ? (
								<DayPlan
									key={ planning.key }
									suggestion={ planning }
									date={ selectedDay }
									laneLabel={
										plan.days.find(
											( day ) => day.date === selectedDay
										)?.lane.label || ''
									}
									others={ (
										suggestions.get( selectedDay )
											?.suggestions || []
									).filter(
										( other ) => other.key !== planning.key
									) }
									onPick={ setPlanning }
									onCreated={ async () => {
										suggestions.invalidate( selectedDay );
										await plan.refresh();
									} }
									onBack={ () => setPlanning( null ) }
								/>
							) : (
								<DeskRail
									days={ plan.days }
									today={ today }
									weekStartsOn={ weekStartsOn }
									selectedDate={ selectedDay }
									result={
										selectedDay
											? suggestions.get( selectedDay )
											: null
									}
									onPlan={ setPlanning }
									onPlace={ placeSuggestion }
									onShare={ shareSuggestion }
									onDismiss={ dismissSuggestion }
								/>
							) }
						</>
					) }
					<DraftTray
						unscheduled={ plan.unscheduled }
						onMove={ move }
					/>
				</aside>
			</div>
		</div>
	);
}
