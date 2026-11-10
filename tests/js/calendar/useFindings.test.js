import { renderHook, act, waitFor } from '@testing-library/react';

import { runAbility } from '../../../client/data/useAbility';
import { useFindings } from '../../../client/calendar/useFindings';

jest.mock( '../../../client/data/useAbility', () => ( {
	runAbility: jest.fn(),
} ) );

const result = ( findings ) => ( {
	generated_at: '2026-11-09T14:00:00-08:00',
	findings,
} );

describe( 'useFindings', () => {
	beforeEach( () => {
		runAbility.mockReset();
	} );

	it( 'groups findings by post', async () => {
		runAbility.mockResolvedValue(
			result( [
				{ key: 'lane_mismatch:1', post_id: 1 },
				{ key: 'cadence_break:1', post_id: 1 },
				{ key: 'lane_mismatch:2', post_id: 2 },
			] )
		);
		const { result: hook } = renderHook( () => useFindings() );
		await waitFor( () => expect( hook.current.loaded ).toBe( true ) );
		expect( runAbility ).toHaveBeenCalledWith( 'masthead/swap-check', {} );
		expect( hook.current.generatedAt ).toBe( '2026-11-09T14:00:00-08:00' );
		expect( hook.current.byPost( 1 ).map( ( f ) => f.key ) ).toEqual( [
			'lane_mismatch:1',
			'cadence_break:1',
		] );
		expect( hook.current.byPost( 3 ) ).toEqual( [] );
	} );

	it( 'refresh reloads the findings', async () => {
		runAbility.mockResolvedValueOnce(
			result( [ { key: 'a', post_id: 1 } ] )
		);
		const { result: hook } = renderHook( () => useFindings() );
		await waitFor( () =>
			expect( hook.current.findings ).toHaveLength( 1 )
		);
		runAbility.mockResolvedValueOnce( result( [] ) );
		await act( async () => {
			await hook.current.refresh();
		} );
		expect( hook.current.findings ).toHaveLength( 0 );
		expect( runAbility ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'treats a failed request as no findings', async () => {
		runAbility.mockRejectedValue( { code: 'x', message: 'no' } );
		const { result: hook } = renderHook( () => useFindings() );
		await waitFor( () => expect( hook.current.loaded ).toBe( true ) );
		expect( hook.current.generatedAt ).toBeNull();
		expect( hook.current.findings ).toEqual( [] );
	} );
} );
