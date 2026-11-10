import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

import { runAbility } from './useAbility';
import { today } from './clock';

const EMPTY = { days: [], unscheduled: [], truncated: false };

function withOpen( day ) {
	return { ...day, open: day.posts.length === 0 && day.date >= today() };
}

/**
 * Moves a post in a plan snapshot.
 *
 * @param {Object} plan   Plan with days and unscheduled.
 * @param {number} postId Post ID.
 * @param {string} date   Target date.
 * @return {Object} New plan.
 */
function movePost( plan, postId, date ) {
	const find = ( posts ) => posts.find( ( post ) => post.id === postId );
	const moved =
		find( plan.unscheduled ) ||
		plan.days.map( ( day ) => find( day.posts ) ).find( Boolean );
	if ( ! moved ) {
		return plan;
	}
	const stamp = moved.date
		? moved.date.replace( /^\d{4}-\d{2}-\d{2}/, date )
		: `${ date }T00:00:00`;
	const updated = { ...moved, date: stamp };
	const without = ( posts ) => posts.filter( ( post ) => post.id !== postId );

	return {
		...plan,
		unscheduled: without( plan.unscheduled ),
		days: plan.days.map( ( day ) => {
			let posts = without( day.posts );
			if ( day.date === date ) {
				posts = [ ...posts, updated ];
			}
			return posts.length === day.posts.length &&
				day.date !== date &&
				posts.every( ( post, i ) => post === day.posts[ i ] )
				? day
				: withOpen( { ...day, posts } );
		} ),
	};
}

/**
 * Plan data for a range, from the masthead/get-plan ability.
 *
 * @param {string} from First date.
 * @param {string} to   Last date.
 * @return {Object} Plan state and actions.
 */
export function usePlan( from, to ) {
	const [ plan, setPlan ] = useState( EMPTY );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const planRef = useRef( plan );
	planRef.current = plan;
	const { invalidateResolutionForStoreSelector } = useDispatch( coreStore );

	const load = useCallback( async () => {
		setIsLoading( true );
		setError( null );
		try {
			const result = await runAbility( 'masthead/get-plan', {
				from,
				to,
			} );
			setPlan( {
				days: result.days,
				unscheduled: result.unscheduled,
				truncated: result.truncated,
			} );
		} catch ( caught ) {
			setError( caught );
		} finally {
			setIsLoading( false );
		}
	}, [ from, to ] );

	useEffect( () => {
		load();
	}, [ load ] );

	const refresh = useCallback( () => {
		if ( invalidateResolutionForStoreSelector ) {
			invalidateResolutionForStoreSelector( 'getEntityRecords' );
		}
		return load();
	}, [ load, invalidateResolutionForStoreSelector ] );

	const applyMove = useCallback( ( postId, date ) => {
		const token = planRef.current;
		setPlan( movePost( token, postId, date ) );
		return token;
	}, [] );

	const rollback = useCallback( ( token ) => {
		setPlan( token );
	}, [] );

	return {
		days: plan.days,
		unscheduled: plan.unscheduled,
		truncated: plan.truncated,
		isLoading,
		error,
		refresh,
		applyMove,
		rollback,
	};
}
