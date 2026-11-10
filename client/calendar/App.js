import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { getBoot } from '../data/boot';
import { today as bootToday } from '../data/clock';
import { addDays, monthGrid, monthLabel, weekRange } from '../data/dates';
import { usePlan } from '../data/usePlan';
import MonthGrid from './MonthGrid';
import Toolbar from './Toolbar';

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
		return [
			`${ anchor.slice( 0, 7 ) }-01`,
			addDays( shiftMonth( anchor, 1 ), -1 ),
		];
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
	const [ hidden ] = useState( () => new Set() );

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

	const step = useCallback(
		( direction ) => {
			setAnchor( ( current ) =>
				view === 'week'
					? addDays( current, direction * 7 )
					: shiftMonth( current, direction )
			);
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
				title={ monthLabel( anchor ) }
				onPrev={ () => step( -1 ) }
				onNext={ () => step( 1 ) }
				onToday={ () => setAnchor( bootToday() ) }
				onView={ onView }
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
						/>
					) : (
						<div
							className="masthead-placeholder"
							data-view={ view }
						/>
					) }
				</div>
				<aside
					className="masthead-rail"
					aria-label={ __( 'Desk', 'masthead' ) }
				/>
			</div>
		</div>
	);
}
