import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import apiFetch from '@wordpress/api-fetch';

import { runAbility } from '../../../client/data/useAbility';
import SwapPanel from '../../../client/calendar/SwapPanel';

jest.mock( '../../../client/data/useAbility', () => ( {
	runAbility: jest.fn(),
} ) );
jest.mock( '@wordpress/api-fetch' );

const post = { id: 12156, title: 'The Mantle – Part 2 of 4', status: 'future' };

const lane = {
	key: 'lane_mismatch:12156',
	type: 'lane_mismatch',
	why: 'The Mantle – Part 2 of 4 is on a Monday, which your rhythm gives to Business & leadership.',
	options: [
		{
			label: 'Move to Saturdays: 2026-12-12, 2027-01-09, 2027-02-06',
			ability: 'masthead/reschedule',
			input: { post_id: 12156, date: '2026-12-12' },
			also: [
				{
					ability: 'masthead/reschedule',
					input: { post_id: 12157, date: '2027-01-09' },
				},
				{
					ability: 'masthead/reschedule',
					input: { post_id: 12158, date: '2027-02-06' },
				},
			],
		},
		{
			label: 'Keep Mondays and mark this series as a lane exception',
			ability: null,
			input: {
				rest: {
					path: '/wp/v2/masthead_series/3',
					method: 'POST',
					data: { meta: { masthead_lane_exception: true } },
				},
			},
		},
	],
};

const better = {
	key: 'better_candidate:50',
	type: 'better_candidate',
	why: 'Technology draft fits Tuesday’s Technical series lane better than Business post.',
	numbers: {
		source: 'Jetpack Stats',
		fetched_at: '2026-11-09T13:00:00-08:00',
		values: {
			scheduled_category_views: 100,
			candidate_category_views: 400,
			weekday_avg_views: 30,
		},
	},
	options: [
		{
			label: 'Put Technology draft on 2026-11-17',
			ability: 'masthead/reschedule',
			input: { post_id: 60, date: '2026-11-17' },
		},
	],
};

const renderPanel = ( findings, props = {} ) => {
	const handlers = {
		onApplied: jest.fn().mockResolvedValue( undefined ),
		onClose: jest.fn(),
	};
	render(
		<SwapPanel
			post={ post }
			findings={ findings }
			{ ...handlers }
			{ ...props }
		/>
	);
	return handlers;
};

describe( 'SwapPanel', () => {
	beforeEach( () => {
		runAbility.mockReset();
		runAbility.mockResolvedValue( {} );
		apiFetch.mockReset();
		apiFetch.mockResolvedValue( {} );
	} );

	it( 'shows a no-findings message', () => {
		renderPanel( [] );
		expect(
			screen.getByRole( 'heading', {
				name: 'Swap check · The Mantle – Part 2 of 4',
			} )
		).toBeTruthy();
		expect(
			screen.getByText( 'No findings. This post fits its day.' )
		).toBeTruthy();
	} );

	it( 'renders why and options for each finding', () => {
		renderPanel( [ lane ] );
		expect( screen.getByText( 'Lane mismatch' ) ).toBeTruthy();
		expect( screen.getByText( lane.why ) ).toBeTruthy();
		expect(
			screen.getByRole( 'button', { name: lane.options[ 0 ].label } )
		).toBeTruthy();
		expect( screen.getByText( 'Reschedules 3 posts' ) ).toBeTruthy();
		expect(
			screen.getByRole( 'button', { name: 'Keep as is' } )
		).toBeTruthy();
	} );

	it( 'renders numbers only through StatValue', () => {
		const { container } = render(
			<SwapPanel
				post={ post }
				findings={ [ better ] }
				onApplied={ () => {} }
				onClose={ () => {} }
			/>
		);
		expect( container.querySelectorAll( '.masthead-stat' ) ).toHaveLength(
			3
		);
		const text = container.textContent;
		expect( text ).toContain( "Views in this post's category" );
		expect( text ).toContain( 'Jetpack Stats' );
		expect( text.match( /Jetpack Stats/g ) ).toHaveLength( 3 );
		expect(
			container.querySelector( '.masthead-swap__finding p' ).textContent
		).not.toMatch( /\d/ );
		const without = render(
			<SwapPanel
				post={ post }
				findings={ [ { ...better, numbers: undefined } ] }
				onApplied={ () => {} }
				onClose={ () => {} }
			/>
		);
		expect(
			without.container.querySelectorAll( '.masthead-stat' )
		).toHaveLength( 0 );
	} );

	it( 'an option with also runs every call in order and stops at the first error', async () => {
		const { onApplied } = renderPanel( [ lane ] );
		await userEvent.click(
			screen.getByRole( 'button', { name: lane.options[ 0 ].label } )
		);
		expect( runAbility.mock.calls ).toEqual( [
			[ 'masthead/reschedule', { post_id: 12156, date: '2026-12-12' } ],
			[ 'masthead/reschedule', { post_id: 12157, date: '2027-01-09' } ],
			[ 'masthead/reschedule', { post_id: 12158, date: '2027-02-06' } ],
		] );
		expect( await screen.findByText( 'Done.' ) ).toBeTruthy();
		expect( onApplied ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'stops at the first failing call and reports it', async () => {
		runAbility.mockResolvedValueOnce( {} );
		runAbility.mockRejectedValueOnce( {
			code: 'masthead_published_locked',
			message: 'Published posts cannot be rescheduled.',
		} );
		renderPanel( [ lane ] );
		await userEvent.click(
			screen.getByRole( 'button', { name: lane.options[ 0 ].label } )
		);
		expect(
			await screen.findByText( 'Published posts cannot be rescheduled.' )
		).toBeTruthy();
		expect( runAbility ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'the lane exception option posts term meta to the series endpoint', async () => {
		renderPanel( [ lane ] );
		await userEvent.click(
			screen.getByRole( 'button', { name: lane.options[ 1 ].label } )
		);
		expect( apiFetch ).toHaveBeenCalledWith( {
			path: '/wp/v2/masthead_series/3',
			method: 'POST',
			data: { meta: { masthead_lane_exception: true } },
		} );
		expect( runAbility ).not.toHaveBeenCalled();
	} );

	it( 'Keep as is closes the panel', async () => {
		const { onClose } = renderPanel( [] );
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Keep as is' } )
		);
		expect( onClose ).toHaveBeenCalled();
	} );
} );
