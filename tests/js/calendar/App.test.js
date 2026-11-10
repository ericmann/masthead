import { render, screen, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { resetBoot } from '../../../client/data/boot';
import App from '../../../client/calendar/App';
import { usePlan } from '../../../client/data/usePlan';

jest.mock( '../../../client/data/usePlan', () => ( { usePlan: jest.fn() } ) );

const plan = ( over = {} ) => ( {
	days: [],
	unscheduled: [],
	truncated: false,
	isLoading: false,
	error: null,
	refresh: jest.fn(),
	applyMove: jest.fn(),
	rollback: jest.fn(),
	...over,
} );

describe( 'calendar App', () => {
	beforeEach( () => {
		window.location.hash = '';
		window.mastheadBoot = {
			today: '2026-11-09',
			now: '2026-11-09T14:00:00-08:00',
			weekStartsOn: 0,
			rhythm: [],
			config: { maxRangeDays: 92 },
		};
		resetBoot();
		usePlan.mockReset();
		usePlan.mockReturnValue( plan() );
	} );

	it( 'renders the Calendar heading', () => {
		render( <App /> );
		expect(
			screen.getByRole( 'heading', { level: 1, name: 'Calendar' } )
		).toBeTruthy();
	} );

	it( 'defaults to the month view', () => {
		render( <App /> );
		expect(
			screen.getByText( 'November 2026', { selector: 'h2' } )
		).toBeTruthy();
		expect(
			screen
				.getByRole( 'button', { name: 'Month' } )
				.getAttribute( 'aria-pressed' )
		).toBe( 'true' );
		expect( usePlan ).toHaveBeenCalledWith( '2026-11-01', '2026-12-05' );
	} );

	it( 'switches view when the hash changes', () => {
		const { container } = render( <App /> );
		act( () => {
			window.location.hash = '#week';
			window.dispatchEvent( new window.HashChangeEvent( 'hashchange' ) );
		} );
		expect( container.querySelector( '.masthead-week' ) ).not.toBeNull();
		expect( usePlan ).toHaveBeenLastCalledWith(
			'2026-11-08',
			'2026-11-14'
		);
		expect(
			screen
				.getByRole( 'button', { name: 'Week' } )
				.getAttribute( 'aria-pressed' )
		).toBe( 'true' );
		expect(
			screen.getByRole( 'button', { name: 'Next week' } )
		).toBeTruthy();
	} );

	it( 'loads the list range from the anchor for the configured span', () => {
		window.location.hash = '#list';
		render( <App /> );
		expect( usePlan ).toHaveBeenLastCalledWith(
			'2026-11-09',
			'2027-02-08'
		);
		expect(
			screen.getByRole( 'button', { name: 'Next page' } )
		).toBeTruthy();
	} );

	it( 'toggling a legend category hides its chips', async () => {
		window.mastheadBoot.categories = [
			{ slug: 'technology', name: 'Technology', color: '#2f5fd0' },
			{ slug: 'faith', name: 'Faith', color: '#7a5c2e' },
		];
		resetBoot();
		const post = ( id, category ) => ( {
			id,
			title: `Post ${ id }`,
			status: 'future',
			color: '#2f5fd0',
			date: '2026-11-11T08:00:00-08:00',
			primary_category: category,
		} );
		usePlan.mockReturnValue(
			plan( {
				days: [
					{
						date: '2026-11-11',
						lane: {
							slug: 's',
							label: 'Standalone',
							categories: [],
						},
						posts: [ post( 1, 'technology' ), post( 2, 'faith' ) ],
						open: false,
					},
				],
			} )
		);
		render( <App /> );
		expect( screen.getByText( 'Post 1' ) ).toBeTruthy();
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Technology' } )
		);
		expect( screen.queryByText( 'Post 1' ) ).toBeNull();
		expect( screen.getByText( 'Post 2' ) ).toBeTruthy();
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Technology' } )
		);
		expect( screen.getByText( 'Post 1' ) ).toBeTruthy();
	} );

	it( 'Today returns the anchor to the boot date', async () => {
		render( <App /> );
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Next month' } )
		);
		expect(
			screen.getByText( 'December 2026', { selector: 'h2' } )
		).toBeTruthy();
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Today' } )
		);
		expect(
			screen.getByText( 'November 2026', { selector: 'h2' } )
		).toBeTruthy();
	} );

	it( 'next and previous move the anchor by one month in month view', async () => {
		render( <App /> );
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Previous month' } )
		);
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Previous month' } )
		);
		expect(
			screen.getByText( 'September 2026', { selector: 'h2' } )
		).toBeTruthy();
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Next month' } )
		);
		expect(
			screen.getByText( 'October 2026', { selector: 'h2' } )
		).toBeTruthy();
	} );

	it( 'shows a notice when the range is truncated or the request fails', () => {
		usePlan.mockReturnValue( plan( { truncated: true } ) );
		const { unmount } = render( <App /> );
		expect(
			screen.getByText( /more posts than can be shown/ )
		).toBeTruthy();
		unmount();
		usePlan.mockReturnValue(
			plan( { error: { code: 'x', message: 'Nope.' } } )
		);
		render( <App /> );
		expect( screen.getByText( 'Nope.' ) ).toBeTruthy();
	} );
} );
