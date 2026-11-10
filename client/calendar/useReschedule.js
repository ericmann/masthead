import { useCallback, useState } from '@wordpress/element';

import { runAbility } from '../data/useAbility';

/**
 * Reschedules posts through the masthead/reschedule ability with an optimistic update.
 *
 * @param {Object} plan The usePlan() result: applyMove, rollback and refresh.
 * @return {{move: Function, error: Object|null, clearError: Function}} Mover and its error.
 */
export function useReschedule( plan ) {
	const [ error, setError ] = useState( null );

	const move = useCallback(
		async ( post, date ) => {
			const current = post.date ? post.date.slice( 0, 10 ) : null;
			if ( current === date ) {
				return;
			}
			setError( null );
			const token = plan.applyMove( post.id, date );
			try {
				await runAbility( 'masthead/reschedule', {
					post_id: post.id,
					date,
				} );
				await plan.refresh();
			} catch ( caught ) {
				plan.rollback( token );
				setError( caught );
			}
		},
		[ plan ]
	);

	const clearError = useCallback( () => setError( null ), [] );

	return { move, error, clearError };
}
