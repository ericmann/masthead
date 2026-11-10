import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { useCallback, useState } from '@wordpress/element';

import { getBoot } from './boot';

const FALLBACK_RUN = {
	path: '/wp-abilities/v1/abilities/%s/run',
	readonly: { method: 'GET', input: 'query' },
	write: { method: 'POST', input: 'body' },
};

/**
 * Runs an ability through the core Abilities run endpoint.
 *
 * @param {string} name  Ability name, such as masthead/get-plan.
 * @param {Object} input Ability input.
 * @return {Promise<*>} The ability output.
 */
export async function runAbility( name, input = {} ) {
	const boot = getBoot();
	const run = boot.run || FALLBACK_RUN;
	const readonly = Boolean( boot.abilities?.[ name ]?.readonly );
	const spec = readonly ? run.readonly : run.write;
	const path = run.path.replace( '%s', name );

	const request =
		spec.input === 'query'
			? { path: addQueryArgs( path, { input } ), method: spec.method }
			: { path, method: spec.method, data: { input } };

	try {
		return await apiFetch( request );
	} catch ( error ) {
		throw { code: error?.code, message: error?.message };
	}
}

/**
 * Hook around runAbility.
 *
 * @param {string} name Ability name.
 * @return {{run: Function, isRunning: boolean, error: Object|null}} Runner state.
 */
export function useAbility( name ) {
	const [ isRunning, setIsRunning ] = useState( false );
	const [ error, setError ] = useState( null );

	const run = useCallback(
		async ( input ) => {
			setIsRunning( true );
			setError( null );
			try {
				return await runAbility( name, input );
			} catch ( caught ) {
				setError( caught );
				throw caught;
			} finally {
				setIsRunning( false );
			}
		},
		[ name ]
	);

	return { run, isRunning, error };
}
