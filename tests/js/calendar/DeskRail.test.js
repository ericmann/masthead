import { render, screen } from '@testing-library/react';

import { resetBoot } from '../../../client/data/boot';
import DeskRail from '../../../client/calendar/DeskRail';

const day = ( date, label, posts = [] ) => ( {
	date,
	lane: { slug: label.toLowerCase(), label, categories: [] },
	posts,
	open: posts.length === 0,
} );

const post = ( id, color ) => ( { id, color, title: `Post ${ id }` } );

// Week of Sunday 2026-11-08 to Saturday 2026-11-14.
const days = [
	day( '2026-11-08', 'Faith' ),
	day( '2026-11-09', 'Business', [
		post( 1, '#7a5c2e' ),
		post( 2, '#2f5fd0' ),
	] ),
	day( '2026-11-10', 'Technology' ),
	day( '2026-11-11', 'Standalone', [ post( 3, '#2f5fd0' ) ] ),
	day( '2026-11-12', 'Technology', [ post( 4, '#7a5c2e' ) ] ),
	day( '2026-11-13', 'Journal' ),
	day( '2026-11-14', 'Fiction' ),
	day( '2026-11-18', 'Standalone' ),
];

const result = {
	date: '2026-11-18',
	lane: { slug: 'standalone', label: 'Standalone', categories: [] },
	suggestions: [
		{
			key: 'draft:10178',
			kind: 'draft',
			title: 'From Craftsmen to Swarms',
			why: 'Wednesday is your Standalone slot.',
			actions: [ 'dismiss' ],
		},
	],
	ai: { used: false, remaining: 0 },
};

const renderRail = ( props = {} ) =>
	render(
		<DeskRail
			days={ days }
			today="2026-11-09"
			weekStartsOn={ 0 }
			selectedDate={ null }
			result={ null }
			onPlace={ () => {} }
			onShare={ () => {} }
			onDismiss={ () => {} }
			{ ...props }
		/>
	);

describe( 'DeskRail', () => {
	beforeEach( () => {
		window.mastheadBoot = { urls: { editPost: 'x%d' } };
		resetBoot();
	} );

	it( 'summarises how many days of the week have a post and names the next gap', () => {
		const { container } = renderRail();
		expect(
			screen.getByText(
				/3 of 7 days have a post\. Next gap: Tue Nov 10\./
			)
		).toBeTruthy();
		expect(
			container.querySelectorAll( '.masthead-desk__bar' )
		).toHaveLength( 7 );
		expect(
			container.querySelectorAll( '.masthead-desk__bar.is-empty' )
		).toHaveLength( 4 );
		expect(
			container.querySelectorAll( '.masthead-desk__bar' )[ 1 ].style
				.backgroundColor
		).toBe( 'rgb(122, 92, 46)' );
		expect(
			screen.getByText( 'Select a day to see its suggestions.' )
		).toBeTruthy();
	} );

	it( "shows the selected day's lane and suggestions", () => {
		renderRail( { selectedDate: '2026-11-18', result } );
		expect(
			screen.getByRole( 'heading', {
				name: 'Wednesday, Nov 18 · Standalone lane',
			} )
		).toBeTruthy();
		expect(
			screen.getByRole( 'heading', { name: 'Desk · Wednesday, Nov 18' } )
		).toBeTruthy();
		expect( screen.getByText( 'From Craftsmen to Swarms' ) ).toBeTruthy();
		expect(
			screen.getByText( 'Wednesday is your Standalone slot.' )
		).toBeTruthy();
	} );

	it( 'shows a message for a day that has a post', () => {
		renderRail( {
			selectedDate: '2026-11-11',
			result: { ...result, suggestions: [] },
		} );
		expect( screen.getByText( 'This day has a post.' ) ).toBeTruthy();
	} );

	it( 'shows a message when there is nothing to suggest', () => {
		renderRail( {
			selectedDate: '2026-11-18',
			result: { ...result, suggestions: [] },
		} );
		expect(
			screen.getByText( 'No suggestions for this day.' )
		).toBeTruthy();
	} );
} );
