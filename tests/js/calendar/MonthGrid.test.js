import { render, screen } from '@testing-library/react';

import { resetBoot } from '../../../client/data/boot';
import MonthGrid from '../../../client/calendar/MonthGrid';

const rhythm = [
	{ label: 'Faith' },
	{ label: 'Business & leadership' },
	{ label: 'Technical series' },
	{ label: 'Standalone' },
	{ label: 'Technical series' },
	{ label: 'Journal & build log' },
	{ label: 'Fiction serial' },
];

const renderGrid = ( props = {} ) =>
	render(
		<MonthGrid
			anchor="2026-11-14"
			weekStartsOn={ 0 }
			days={ [] }
			today="2026-11-09"
			selectedDate={ null }
			hidden={ new Set() }
			onSelectDay={ () => {} }
			onSelectPost={ () => {} }
			{ ...props }
		/>
	);

describe( 'MonthGrid', () => {
	beforeEach( () => {
		window.mastheadBoot = { rhythm };
		resetBoot();
	} );

	it( 'renders five weeks for November 2026', () => {
		const { container } = renderGrid();
		expect(
			container.querySelectorAll( '.masthead-grid__week' )
		).toHaveLength( 5 );
		expect( container.querySelectorAll( '[data-date]' ) ).toHaveLength(
			35
		);
		expect(
			screen.getByRole( 'region', { name: 'November 2026' } )
		).toBeTruthy();
		expect(
			container.querySelector( '[data-date]' ).getAttribute( 'data-date' )
		).toBe( '2026-11-01' );
	} );

	it( "header shows each weekday's lane label", () => {
		renderGrid();
		const heads = [
			...document.querySelectorAll( '.masthead-grid__weekday' ),
		];
		expect( heads[ 0 ].textContent ).toBe( 'Sun · Faith' );
		expect( heads[ 3 ].textContent ).toBe( 'Wed · Standalone' );
		expect( heads[ 6 ].textContent ).toBe( 'Sat · Fiction serial' );
		// The weekday is bold and the lane muted; the full text is the tooltip when it is cut short.
		expect(
			heads[ 3 ].querySelector( '.masthead-grid__dow' ).textContent
		).toBe( 'Wed' );
		expect(
			heads[ 3 ].querySelector( '.masthead-grid__lane' ).textContent
		).toBe( ' · Standalone' );
		expect( heads[ 1 ].getAttribute( 'title' ) ).toBe(
			'Mon · Business & leadership'
		);
	} );

	it( 'marks days before today as past', () => {
		const { container } = renderGrid();
		expect(
			container.querySelector( '[data-date="2026-11-08"]' ).className
		).toContain( 'is-past' );
		expect(
			container.querySelector( '[data-date="2026-11-09"]' ).className
		).not.toContain( 'is-past' );
	} );

	it( 'honours the week start in the header', () => {
		renderGrid( { weekStartsOn: 1 } );
		const heads = document.querySelectorAll( '.masthead-grid__weekday' );
		expect( heads[ 0 ].textContent ).toBe( 'Mon · Business & leadership' );
		expect( heads[ 6 ].textContent ).toBe( 'Sun · Faith' );
	} );

	it( 'marks days outside the month', () => {
		const { container } = renderGrid();
		expect(
			container.querySelector( '[data-date="2026-12-01"]' ).className
		).toContain( 'is-outside' );
		expect(
			container.querySelector( '[data-date="2026-11-30"]' ).className
		).not.toContain( 'is-outside' );
		expect(
			container.querySelector( '[data-date="2026-11-09"]' ).className
		).toContain( 'is-today' );
	} );
} );
