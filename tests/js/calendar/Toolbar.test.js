import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import Toolbar from '../../../client/calendar/Toolbar';

const renderToolbar = ( props = {} ) =>
	render(
		<Toolbar
			view="month"
			title="November 2026"
			onPrev={ () => {} }
			onNext={ () => {} }
			onToday={ () => {} }
			onView={ () => {} }
			onFillOpenDays={ () => {} }
			{ ...props }
		/>
	);

describe( 'Toolbar', () => {
	it( 'names the icon-only step buttons for the view', async () => {
		const onPrev = jest.fn();
		const { rerender } = renderToolbar( { onPrev } );
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Previous month' } )
		);
		expect( onPrev ).toHaveBeenCalledTimes( 1 );
		expect(
			screen.getByRole( 'button', { name: 'Next month' } )
		).toBeTruthy();
		rerender(
			<Toolbar
				view="week"
				title="Nov 8 – Nov 14, 2026"
				onPrev={ () => {} }
				onNext={ () => {} }
				onToday={ () => {} }
				onView={ () => {} }
			/>
		);
		expect(
			screen.getByRole( 'button', { name: 'Previous week' } )
		).toBeTruthy();
	} );

	it( 'fills the active view button and marks it pressed', () => {
		renderToolbar( { view: 'week' } );
		const week = screen.getByRole( 'button', { name: 'Week' } );
		expect( week.getAttribute( 'aria-pressed' ) ).toBe( 'true' );
		expect( week.className ).toContain( 'button-primary' );
		expect(
			screen.getByRole( 'button', { name: 'Month' } ).className
		).not.toContain( 'button-primary' );
	} );

	it( 'Fill open days is enabled with no note when there is work to do', async () => {
		const onFillOpenDays = jest.fn();
		renderToolbar( { onFillOpenDays } );
		const fill = screen.getByRole( 'button', { name: 'Fill open days' } );
		expect( fill.disabled ).toBe( false );
		expect( screen.getByRole( 'status' ).textContent ).toBe( '' );
		await userEvent.click( fill );
		expect( onFillOpenDays ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'shows progress beside Fill open days while it runs', () => {
		renderToolbar( {
			filling: true,
			fillStatus: 'Suggesting for open days: 3 of 14',
		} );
		const fill = screen.getByRole( 'button', { name: 'Fill open days' } );
		expect( fill.disabled ).toBe( true );
		expect( screen.getByRole( 'status' ).textContent ).toBe(
			'Suggesting for open days: 3 of 14'
		);
	} );

	it( 'keeps the reason visible when there is nothing to fill', () => {
		renderToolbar( {
			fillBlocked: 'Every open day in view has suggestions.',
		} );
		const fill = screen.getByRole( 'button', { name: 'Fill open days' } );
		expect( fill.disabled ).toBe( true );
		expect( fill.getAttribute( 'title' ) ).toBe(
			'Every open day in view has suggestions.'
		);
		expect( fill.getAttribute( 'aria-describedby' ) ).toBe(
			'masthead-fill-note'
		);
		expect(
			screen.getByText( 'Every open day in view has suggestions.' )
		).toBeTruthy();
	} );
} );
