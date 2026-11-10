import { renderHook, act } from '@testing-library/react';

import { runAbility } from '../../../client/data/useAbility';
import { useSuggestions } from '../../../client/calendar/useSuggestions';

jest.mock( '../../../client/data/useAbility', () => ( {
	runAbility: jest.fn(),
} ) );

describe( 'useSuggestions', () => {
	let inFlight;
	let maxInFlight;

	beforeEach( () => {
		inFlight = 0;
		maxInFlight = 0;
		runAbility.mockReset();
		runAbility.mockImplementation( async ( name, input ) => {
			inFlight++;
			maxInFlight = Math.max( maxInFlight, inFlight );
			await new Promise( ( resolve ) => setTimeout( resolve, 1 ) );
			inFlight--;
			return {
				date: input.date,
				suggestions: [ { kind: 'draft', title: input.date } ],
			};
		} );
	} );

	it( 'loadMany requests dates one at a time', async () => {
		const { result } = renderHook( () => useSuggestions() );
		await act( async () => {
			await result.current.loadMany( [
				'2026-11-10',
				'2026-11-11',
				'2026-11-12',
			] );
		} );
		expect( runAbility ).toHaveBeenCalledTimes( 3 );
		expect( maxInFlight ).toBe( 1 );
		expect(
			result.current.get( '2026-11-11' ).suggestions[ 0 ].title
		).toBe( '2026-11-11' );
		expect( result.current.isRunning ).toBe( false );
		expect( result.current.progress ).toEqual( { done: 3, total: 3 } );
	} );

	it( 'results are cached per date', async () => {
		const { result } = renderHook( () => useSuggestions() );
		await act( async () => {
			await result.current.load( '2026-11-18' );
			await result.current.load( '2026-11-18' );
			await result.current.loadMany( [ '2026-11-18', '2026-11-19' ] );
		} );
		expect( runAbility ).toHaveBeenCalledTimes( 2 );
		expect( result.current.get( '2026-11-20' ) ).toBeNull();
	} );

	it( 'invalidate forces a reload', async () => {
		const { result } = renderHook( () => useSuggestions() );
		await act( async () => {
			await result.current.load( '2026-11-18' );
		} );
		act( () => result.current.invalidate( '2026-11-18' ) );
		expect( result.current.get( '2026-11-18' ) ).toBeNull();
		await act( async () => {
			await result.current.load( '2026-11-18' );
		} );
		expect( runAbility ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'never requests ideas', async () => {
		const { result } = renderHook( () => useSuggestions() );
		await act( async () => {
			await result.current.loadMany( [ '2026-11-18', '2026-11-19' ] );
		} );
		runAbility.mock.calls.forEach( ( [ name, input ] ) => {
			expect( name ).toBe( 'masthead/suggest-for-date' );
			expect( Object.keys( input ) ).toEqual( [ 'date' ] );
			expect( input.include_ideas ).toBeUndefined();
		} );
	} );
} );
