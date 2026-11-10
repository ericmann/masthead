import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import DayCell from '../../../client/calendar/DayCell';

const post = ( id, category ) => ( {
	id,
	title: `Post ${ id }`,
	status: 'future',
	color: '#2f5fd0',
	date: '2026-11-11T08:00:00-08:00',
	primary_category: category,
} );

const day = ( overrides = {} ) => ( {
	date: '2026-11-18',
	lane: { slug: 'standalone', label: 'Standalone', categories: [] },
	posts: [],
	open: true,
	...overrides,
} );

const renderDay = ( props ) =>
	render(
		<DayCell
			isToday={ false }
			isSelected={ false }
			hidden={ new Set() }
			onSelectDay={ () => {} }
			onSelectPost={ () => {} }
			{ ...props }
		/>
	);

describe( 'DayCell', () => {
	it( 'open day button is labelled Wednesday, November 18: open Standalone slot', async () => {
		const onSelectDay = jest.fn();
		renderDay( { day: day(), onSelectDay } );
		const button = screen.getByRole( 'button', {
			name: 'Wednesday, November 18: open Standalone slot',
		} );
		expect( button.getAttribute( 'aria-pressed' ) ).toBe( 'false' );
		await userEvent.click( button );
		expect( onSelectDay ).toHaveBeenCalledWith( '2026-11-18' );
		expect( screen.getByText( 'Open · Standalone' ) ).toBeTruthy();
	} );

	it( 'day with two posts is labelled with the count', () => {
		renderDay( {
			day: day( {
				posts: [ post( 1, 'technology' ), post( 2, 'faith' ) ],
				open: false,
			} ),
		} );
		expect(
			screen.getByRole( 'button', {
				name: 'Wednesday, November 18: 2 posts',
			} )
		).toBeTruthy();
		renderDay( {
			day: day( {
				date: '2026-11-19',
				posts: [ post( 3, 'faith' ) ],
				open: false,
			} ),
		} );
		expect(
			screen.getByRole( 'button', {
				name: 'Thursday, November 19: 1 post',
			} )
		).toBeTruthy();
	} );

	it( 'hidden categories are not rendered', () => {
		renderDay( {
			day: day( {
				posts: [ post( 1, 'technology' ), post( 2, 'faith' ) ],
				open: false,
			} ),
			hidden: new Set( [ 'faith' ] ),
		} );
		expect( screen.getByText( 'Post 1' ) ).toBeTruthy();
		expect( screen.queryByText( 'Post 2' ) ).toBeNull();
	} );

	it( 'chips are not nested inside the day button', () => {
		const { container } = renderDay( {
			day: day( { posts: [ post( 1, 'technology' ) ], open: false } ),
		} );
		const dayButton = container.querySelector( '.masthead-day__button' );
		expect( within( dayButton ).queryByRole( 'button' ) ).toBeNull();
		expect(
			container.querySelector( '[data-post-id]' ).parentElement
		).toBe( container.querySelector( '[data-date]' ) );
	} );

	it( 'today is marked', () => {
		const { container } = renderDay( {
			day: day(),
			isToday: true,
			isSelected: true,
		} );
		expect( screen.getByText( 'TODAY' ) ).toBeTruthy();
		expect( container.querySelector( '[data-date]' ).className ).toContain(
			'is-today'
		);
		expect(
			screen
				.getByRole( 'button', { name: /open Standalone slot/ } )
				.getAttribute( 'aria-pressed' )
		).toBe( 'true' );
	} );
} );
