import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

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
import DraftTray from './DraftTray';
import SharePanel from './SharePanel';
import { useReschedule } from './useReschedule';
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
						/>
					) : null }
					{ view === 'list' ? (
						<ListView
							days={ plan.days }
							hidden={ hidden }
							onSelectPost={ ( post ) =>
								setSelection( { type: 'post', post } )
							}
							onMovePost={ move }
							onDropPost={ onDropPost }
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
					<DraftTray
						unscheduled={ plan.unscheduled }
						onMove={ move }
					/>
				</aside>
			</div>
		</div>
	);
}
