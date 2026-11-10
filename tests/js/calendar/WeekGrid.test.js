import { render, screen } from '@testing-library/react';

import WeekGrid from '../../../client/calendar/WeekGrid';

const lanes = [
	'Faith',
	'Business',
	'Technical series',
	'Standalone',
	'Technical series',
	'Journal',
	'Fiction',
];

const week = ( start ) =>
	Array.from( { length: 7 }, ( _, i ) => {
		const date = `2026-11-${ String( start + i ).padStart( 2, '0' ) }`;
		return {
			date,
			lane: { slug: 's', label: lanes[ i ], categories: [] },
			posts: [],
			open: true,
		};
	} );

const renderWeek = ( props = {} ) =>
	render(
		<WeekGrid
			anchor="2026-11-11"
			weekStartsOn={ 0 }
			days={ week( 8 ) }
			today="2026-11-09"
			selectedDate={ null }
			hidden={ new Set() }
			onSelectDay={ () => {} }
			onSelectPost={ () => {} }
			{ ...props }
		/>
	);

describe( 'WeekGrid', () => {
	it( 'renders seven day columns with lane labels', () => {
		const { container } = renderWeek();
		expect(
			container.querySelectorAll( '.masthead-week__column' )
		).toHaveLength( 7 );
		expect( screen.getByText( 'Sun 8 · Faith' ) ).toBeTruthy();
		expect( screen.getByText( 'Wed 11 · Standalone' ) ).toBeTruthy();
		expect( screen.getByText( 'Sat 14 · Fiction' ) ).toBeTruthy();
		expect(
			container.querySelector( '[data-date="2026-11-09"]' ).className
		).toContain( 'is-today' );
	} );

	it( 'honours the week start', () => {
		const { container } = renderWeek( {
			weekStartsOn: 1,
			days: week( 9 ),
		} );
		expect(
			container.querySelector( '[data-date]' ).getAttribute( 'data-date' )
		).toBe( '2026-11-09' );
	} );
} );
