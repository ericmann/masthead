import { render, screen, fireEvent } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import SeriesCadence from '../../../client/settings/SeriesCadence';

const mantle = ( meta = {} ) => ( {
	id: 55,
	name: 'The Mantle',
	meta: {
		masthead_cadence: '{"every_weeks":4,"weekday":null,"time":"06:23"}',
		masthead_lane_exception: false,
		...meta,
	},
} );

describe( 'SeriesCadence', () => {
	it( 'lists each series with its cadence fields', () => {
		render(
			<SeriesCadence terms={ [ mantle() ] } onChange={ () => {} } />
		);
		expect(
			screen.getByText( 'The Mantle', { selector: 'strong' } )
		).toBeTruthy();
		expect(
			screen.getByLabelText( 'The Mantle: every N weeks' ).value
		).toBe( '4' );
		expect( screen.getByLabelText( 'The Mantle: weekday' ).value ).toBe(
			''
		);
		expect( screen.getByLabelText( 'The Mantle: time' ).value ).toBe(
			'06:23'
		);
		expect(
			screen.getByLabelText( 'The Mantle: lane exception' ).checked
		).toBe( false );
		expect(
			screen.getByRole( 'option', { name: 'Same as previous part' } )
		).toBeTruthy();
		expect( screen.getAllByRole( 'option' ) ).toHaveLength( 8 );
	} );

	it( 'Same as previous part saves weekday null', async () => {
		const onChange = jest.fn();
		render(
			<SeriesCadence
				terms={ [
					mantle( {
						masthead_cadence:
							'{"every_weeks":4,"weekday":6,"time":"06:23"}',
					} ),
				] }
				onChange={ onChange }
			/>
		);
		const select = screen.getByLabelText( 'The Mantle: weekday' );
		expect( select.value ).toBe( '6' );
		await userEvent.selectOptions( select, '' );
		const patch = onChange.mock.calls[ 0 ][ 1 ];
		expect( onChange.mock.calls[ 0 ][ 0 ] ).toBe( 55 );
		expect( JSON.parse( patch.masthead_cadence ) ).toEqual( {
			every_weeks: 4,
			weekday: null,
			time: '06:23',
		} );
	} );

	it( 'editing weeks and time edits the cadence JSON', () => {
		const onChange = jest.fn();
		render(
			<SeriesCadence terms={ [ mantle() ] } onChange={ onChange } />
		);
		fireEvent.change(
			screen.getByLabelText( 'The Mantle: every N weeks' ),
			{ target: { value: '2' } }
		);
		expect(
			JSON.parse( onChange.mock.calls[ 0 ][ 1 ].masthead_cadence )
				.every_weeks
		).toBe( 2 );
		fireEvent.change( screen.getByLabelText( 'The Mantle: time' ), {
			target: { value: '07:00' },
		} );
		expect(
			JSON.parse( onChange.mock.calls[ 1 ][ 1 ].masthead_cadence ).time
		).toBe( '07:00' );
	} );

	it( 'toggling Lane exception edits the term meta', async () => {
		const onChange = jest.fn();
		render(
			<SeriesCadence terms={ [ mantle() ] } onChange={ onChange } />
		);
		await userEvent.click(
			screen.getByLabelText( 'The Mantle: lane exception' )
		);
		expect( onChange ).toHaveBeenCalledWith( 55, {
			masthead_lane_exception: true,
		} );
	} );

	it( 'shows an empty message without series', () => {
		render( <SeriesCadence terms={ [] } onChange={ () => {} } /> );
		expect( screen.getByText( 'No series yet.' ) ).toBeTruthy();
	} );
} );
