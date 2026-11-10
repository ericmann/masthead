import { renderHook, act, waitFor } from '@testing-library/react';

import { resetBoot } from '../../../client/data/boot';
import { runAbility } from '../../../client/data/useAbility';
import { usePlan } from '../../../client/data/usePlan';

jest.mock( '../../../client/data/useAbility', () => ( {
	runAbility: jest.fn(),
} ) );
jest.mock( '@wordpress/core-data', () => ( { store: 'core' } ) );
const mockInvalidate = jest.fn();
jest.mock( '@wordpress/data', () => ( {
	useDispatch: () => ( {
		invalidateResolutionForStoreSelector: mockInvalidate,
	} ),
} ) );

const plan = () => ( {
	days: [
		{
			date: '2026-11-10',
			lane: {
				slug: 'technology',
				label: 'Technical series',
				categories: [],
			},
			posts: [
				{
					id: 12177,
					title: 'Nineteen Years',
					date: '2026-11-10T08:00:00-08:00',
				},
			],
			open: false,
		},
		{
			date: '2026-11-11',
			lane: { slug: 'standalone', label: 'Standalone', categories: [] },
			posts: [],
			open: true,
		},
	],
	unscheduled: [ { id: 10178, title: 'Swarms', date: null } ],
	truncated: false,
} );

describe( 'usePlan', () => {
	beforeEach( () => {
		window.mastheadBoot = { today: '2026-11-09' };
		resetBoot();
		runAbility.mockReset();
		runAbility.mockResolvedValue( plan() );
		mockInvalidate.mockReset();
	} );

	it( 'loads days and unscheduled drafts for the range', async () => {
		const { result } = renderHook( () =>
			usePlan( '2026-11-10', '2026-11-11' )
		);
		expect( result.current.isLoading ).toBe( true );
		await waitFor( () => expect( result.current.isLoading ).toBe( false ) );
		expect( runAbility ).toHaveBeenCalledWith( 'masthead/get-plan', {
			from: '2026-11-10',
			to: '2026-11-11',
		} );
		expect( result.current.days ).toHaveLength( 2 );
		expect( result.current.unscheduled[ 0 ].id ).toBe( 10178 );
		expect( result.current.truncated ).toBe( false );
		expect( result.current.error ).toBeNull();
	} );

	it( 'applyMove moves a post between days and rollback restores it', async () => {
		const { result } = renderHook( () =>
			usePlan( '2026-11-10', '2026-11-11' )
		);
		await waitFor( () => expect( result.current.isLoading ).toBe( false ) );

		let token;
		act( () => {
			token = result.current.applyMove( 12177, '2026-11-11' );
		} );
		expect( result.current.days[ 0 ].posts ).toHaveLength( 0 );
		expect( result.current.days[ 0 ].open ).toBe( true );
		expect( result.current.days[ 1 ].posts[ 0 ].date ).toBe(
			'2026-11-11T08:00:00-08:00'
		);
		expect( result.current.days[ 1 ].open ).toBe( false );

		act( () => {
			token = result.current.applyMove( 10178, '2026-11-10' );
		} );
		expect( result.current.unscheduled ).toHaveLength( 0 );
		expect( result.current.days[ 0 ].posts[ 0 ].id ).toBe( 10178 );

		act( () => {
			result.current.rollback( token );
		} );
		expect( result.current.unscheduled ).toHaveLength( 1 );
		expect( result.current.days[ 0 ].posts ).toHaveLength( 0 );
		expect( result.current.days[ 1 ].posts[ 0 ].id ).toBe( 12177 );
	} );

	it( 'refresh refetches the plan', async () => {
		const { result } = renderHook( () =>
			usePlan( '2026-11-10', '2026-11-11' )
		);
		await waitFor( () => expect( result.current.isLoading ).toBe( false ) );
		runAbility.mockResolvedValue( { ...plan(), truncated: true } );
		await act( async () => {
			await result.current.refresh();
		} );
		expect( runAbility ).toHaveBeenCalledTimes( 2 );
		expect( result.current.truncated ).toBe( true );
		expect( mockInvalidate ).toHaveBeenCalledWith( 'getEntityRecords' );
	} );
} );
