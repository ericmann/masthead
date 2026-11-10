import { render, screen, fireEvent } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import RhythmTable, { slugify } from '../../../client/settings/RhythmTable';

const rhythm = () => ( {
	0: {
		lane: 'faith',
		label: 'Faith',
		categories: [ 'faith' ],
		time: '06:30',
	},
	1: {
		lane: 'business',
		label: 'Business',
		categories: [ 'business' ],
		time: '07:00',
	},
	2: {
		lane: 'technology',
		label: 'Technical series',
		categories: [ 'technology' ],
		time: '07:30',
	},
	3: {
		lane: 'standalone',
		label: 'Standalone',
		categories: [],
		time: '08:00',
	},
	4: {
		lane: 'technology',
		label: 'Technical series',
		categories: [ 'technology', 'security' ],
		time: '07:30',
	},
	5: {
		lane: 'journal',
		label: 'Journal',
		categories: [ 'journal' ],
		time: '08:00',
	},
	6: {
		lane: 'fiction',
		label: 'Fiction serial',
		categories: [ 'writing' ],
		time: '06:30',
	},
} );

const categories = [
	{ slug: 'technology', name: 'Technology' },
	{ slug: 'faith', name: 'Faith' },
	{ slug: 'security', name: 'Security' },
];

const renderTable = ( props = {} ) =>
	render(
		<RhythmTable
			rhythm={ rhythm() }
			categories={ categories }
			weekStartsOn={ 0 }
			setRhythm={ () => {} }
			{ ...props }
		/>
	);

describe( 'RhythmTable', () => {
	it( 'renders seven weekday rows with label, categories and time', () => {
		const { container } = renderTable();
		expect( container.querySelectorAll( 'tbody tr' ) ).toHaveLength( 7 );
		expect( screen.getByLabelText( 'Sunday lane label' ).value ).toBe(
			'Faith'
		);
		expect( screen.getByLabelText( 'Friday default time' ).value ).toBe(
			'08:00'
		);
		const thursday = screen.getByLabelText( 'Thursday categories' );
		expect(
			[ ...thursday.selectedOptions ].map( ( o ) => o.value )
		).toEqual( [ 'technology', 'security' ] );
		expect(
			container.querySelector( 'tbody tr' ).getAttribute( 'data-weekday' )
		).toBe( '0' );
	} );

	it( 'starts at the site week start', () => {
		const { container } = renderTable( { weekStartsOn: 1 } );
		expect(
			container.querySelector( 'tbody tr' ).getAttribute( 'data-weekday' )
		).toBe( '1' );
		const rows = container.querySelectorAll( 'tbody tr' );
		expect( rows[ 6 ].getAttribute( 'data-weekday' ) ).toBe( '0' );
	} );

	it( 'an empty category selection reads Any category', () => {
		renderTable();
		expect( screen.getAllByText( 'Any category' ) ).toHaveLength( 1 );
		expect(
			screen.getByLabelText( 'Wednesday categories' ).closest( 'td' )
				.textContent
		).toContain( 'Any category' );
	} );

	it( 'editing a time calls setRhythm with the new value', async () => {
		const setRhythm = jest.fn();
		renderTable( { setRhythm } );
		const time = screen.getByLabelText( 'Friday default time' );
		fireEvent.change( time, { target: { value: '09:15' } } );
		const last =
			setRhythm.mock.calls[ setRhythm.mock.calls.length - 1 ][ 0 ];
		expect( last[ 5 ].time ).toBe( '09:15' );
		expect( last[ 4 ].time ).toBe( '07:30' );
	} );

	it( 'derives the lane slug from the label', async () => {
		const setRhythm = jest.fn();
		renderTable( { setRhythm } );
		const label = screen.getByLabelText( 'Saturday lane label' );
		await userEvent.type( label, 's' );
		expect( setRhythm.mock.calls[ 0 ][ 0 ][ 6 ] ).toMatchObject( {
			label: 'Fiction serials',
			lane: 'fiction-serials',
		} );
		expect( slugify( ' Journal & build log ' ) ).toBe(
			'journal-build-log'
		);
	} );
} );
