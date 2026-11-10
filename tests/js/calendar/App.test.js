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
		expect(
			container.querySelector( '[data-view="week"]' )
		).not.toBeNull();
		expect(
			screen
				.getByRole( 'button', { name: 'Week' } )
				.getAttribute( 'aria-pressed' )
		).toBe( 'true' );
		expect(
			screen.getByRole( 'button', { name: 'Next week' } )
		).toBeTruthy();
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
