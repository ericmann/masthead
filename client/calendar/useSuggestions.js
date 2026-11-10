import { useCallback, useRef, useState } from '@wordpress/element';

import { runAbility } from '../data/useAbility';

/**
 * Session cache of suggest-for-date results. Requests run one at a time and never ask for ideas.
 *
 * @return {Object} { get, load, loadMany, invalidate, isRunning, progress }.
 */
export function useSuggestions() {
	const [ cache, setCache ] = useState( {} );
	const cacheRef = useRef( cache );
	cacheRef.current = cache;
	const [ isRunning, setIsRunning ] = useState( false );
	const [ progress, setProgress ] = useState( { done: 0, total: 0 } );

	const load = useCallback( async ( date ) => {
		if ( cacheRef.current[ date ] ) {
			return cacheRef.current[ date ];
		}
		try {
			const result = await runAbility( 'masthead/suggest-for-date', {
				date,
			} );
			cacheRef.current = { ...cacheRef.current, [ date ]: result };
			setCache( cacheRef.current );
			return result;
		} catch {
			return null;
		}
	}, [] );

	const loadMany = useCallback(
		async ( dates ) => {
			const todo = dates.filter( ( date ) => ! cacheRef.current[ date ] );
			if ( todo.length === 0 ) {
				return;
			}
			setIsRunning( true );
			setProgress( { done: 0, total: todo.length } );
			for ( let index = 0; index < todo.length; index++ ) {
				await load( todo[ index ] );
				setProgress( { done: index + 1, total: todo.length } );
			}
			setIsRunning( false );
		},
		[ load ]
	);

	const get = useCallback( ( date ) => cache[ date ] || null, [ cache ] );

	const invalidate = useCallback( ( date ) => {
		const next = { ...cacheRef.current };
		delete next[ date ];
		cacheRef.current = next;
		setCache( next );
	}, [] );

	return { get, load, loadMany, invalidate, isRunning, progress };
}
