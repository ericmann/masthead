import { renderHook, act } from '@testing-library/react';

import { runAbility } from '../../../client/data/useAbility';
import { useReschedule } from '../../../client/calendar/useReschedule';

jest.mock( '../../../client/data/useAbility', () => ( {
	runAbility: jest.fn(),
} ) );

const makePlan = ( calls ) => ( {
	applyMove: jest.fn( () => {
		calls.push( 'applyMove' );
		return 'token';
	} ),
	rollback: jest.fn( () => calls.push( 'rollback' ) ),
	refresh: jest.fn( () => {
		calls.push( 'refresh' );
		return Promise.resolve();
	} ),
} );

const post = {
	id: 12177,
	title: 'Nine Winters',
	date: '2026-11-11T08:00:00-08:00',
};

describe( 'useReschedule', () => {
	beforeEach( () => runAbility.mockReset() );

	it( 'moves optimistically then confirms with the ability', async () => {
		const calls = [];
		runAbility.mockImplementation( () => {
			calls.push( 'ability' );
			return Promise.resolve( {} );
		} );
		const plan = makePlan( calls );
		const { result } = renderHook( () => useReschedule( plan ) );
		await act( async () => {
			await result.current.move( post, '2026-11-10' );
		} );
		expect( calls ).toEqual( [ 'applyMove', 'ability', 'refresh' ] );
		expect( runAbility ).toHaveBeenCalledWith( 'masthead/reschedule', {
			post_id: 12177,
			date: '2026-11-10',
		} );
		expect( plan.applyMove ).toHaveBeenCalledWith( 12177, '2026-11-10' );
		expect( result.current.error ).toBeNull();
	} );

	it( 'rolls back and reports the error when the ability fails', async () => {
		const calls = [];
		runAbility.mockRejectedValue( {
			code: 'masthead_published_locked',
			message: 'Published posts cannot be rescheduled.',
		} );
		const plan = makePlan( calls );
		const { result } = renderHook( () => useReschedule( plan ) );
		await act( async () => {
			await result.current.move( post, '2026-11-10' );
		} );
		expect( calls ).toEqual( [ 'applyMove', 'rollback' ] );
		expect( plan.rollback ).toHaveBeenCalledWith( 'token' );
		expect( result.current.error.message ).toBe(
			'Published posts cannot be rescheduled.'
		);
		act( () => result.current.clearError() );
		expect( result.current.error ).toBeNull();
	} );

	it( 'does nothing when the date is unchanged', async () => {
		const calls = [];
		const plan = makePlan( calls );
		const { result } = renderHook( () => useReschedule( plan ) );
		await act( async () => {
			await result.current.move( post, '2026-11-11' );
		} );
		expect( calls ).toEqual( [] );
		expect( runAbility ).not.toHaveBeenCalled();
	} );
} );
