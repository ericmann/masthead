import { useCallback, useEffect, useState } from '@wordpress/element';

import { runAbility } from '../data/useAbility';

/**
 * The stored swap-check findings, loaded once per page load.
 *
 * @return {{generatedAt: string|null, findings: Object[], byPost: Function, refresh: Function}} Findings state.
 */
export function useFindings() {
	const [ state, setState ] = useState( {
		generatedAt: null,
		findings: [],
		loaded: false,
	} );

	const refresh = useCallback( async () => {
		try {
			const result = await runAbility( 'masthead/swap-check', {} );
			setState( {
				generatedAt: result.generated_at,
				findings: result.findings,
				loaded: true,
			} );
		} catch {
			setState( { generatedAt: null, findings: [], loaded: true } );
		}
	}, [] );

	useEffect( () => {
		refresh();
	}, [ refresh ] );

	const byPost = useCallback(
		( id ) =>
			state.findings.filter( ( finding ) => finding.post_id === id ),
		[ state.findings ]
	);

	return {
		generatedAt: state.generatedAt,
		findings: state.findings,
		loaded: state.loaded,
		byPost,
		refresh,
	};
}
