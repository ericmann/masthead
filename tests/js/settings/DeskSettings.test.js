import { render, screen } from '@testing-library/react';
import { useState } from '@wordpress/element';
import userEvent from '@testing-library/user-event';

import DeskSettings from '../../../client/settings/DeskSettings';

const values = {
	'desk.horizon_days': 21,
	'desk.ghost_days': 14,
	'desk.max_suggestions': 3,
	'desk.ideas_per_request': 3,
	'desk.gap_days': 45,
	'desk.reshare_min_age_days': 90,
	'desk.reshare_cooldown_days': 180,
	'desk.series_window_days': 3,
	'swap.lookahead_days': 60,
	'swap.candidate_window_days': 3,
	'stats.window_days': 30,
	'swap.min_score_delta': 0.25,
	'swap.weights': { lane: 0.5, category: 0.3, weekday: 0.2 },
};

const get = ( key ) => values[ key ];

// Keeps edits so controlled inputs behave like the real settings screen.
function Stateful( { spy } ) {
	const [ edited, setEdited ] = useState( values );
	return (
		<DeskSettings
			get={ ( key ) => edited[ key ] }
			setSetting={ ( key, value ) => {
				spy( key, value );
				setEdited( ( current ) => ( { ...current, [ key ]: value } ) );
			} }
		/>
	);
}

describe( 'DeskSettings', () => {
	it( 'shows the current value of every settable desk and swap key', () => {
		render( <DeskSettings get={ get } setSetting={ () => {} } /> );
		const expected = {
			'Planning horizon (days)': 21,
			'Ghost suggestion window (days)': 14,
			'Suggestions per day': 3,
			'Ideas per request': 3,
			'Category gap (days)': 45,
			'Reshare minimum age (days)': 90,
			'Reshare cooldown (days)': 180,
			'Series date window (days)': 3,
			'Swap check look-ahead (days)': 60,
			'Swap candidate window (days)': 3,
			'Stats window (days)': 30,
			'Minimum swap improvement (0 to 1)': 0.25,
			'Swap weight: lane (0 to 1)': 0.5,
			'Swap weight: category (0 to 1)': 0.3,
			'Swap weight: weekday (0 to 1)': 0.2,
		};
		Object.entries( expected ).forEach( ( [ label, value ] ) => {
			expect( Number( screen.getByLabelText( label ).value ) ).toBe(
				value
			);
		} );
		expect( screen.getAllByRole( 'spinbutton' ) ).toHaveLength( 15 );
	} );

	it( 'saves only the keys the user changed', async () => {
		const setSetting = jest.fn();
		render( <Stateful spy={ setSetting } /> );
		expect( setSetting ).not.toHaveBeenCalled();
		const horizon = screen.getByLabelText( 'Planning horizon (days)' );
		await userEvent.clear( horizon );
		expect( setSetting ).not.toHaveBeenCalled();
		await userEvent.type( horizon, '30' );
		expect( setSetting ).toHaveBeenLastCalledWith(
			'desk.horizon_days',
			30
		);
		expect( setSetting.mock.calls.map( ( call ) => call[ 0 ] ) ).toEqual(
			expect.arrayContaining( [ 'desk.horizon_days' ] )
		);
		expect(
			setSetting.mock.calls.every(
				( call ) => call[ 0 ] === 'desk.horizon_days'
			)
		).toBe( true );
	} );

	it( 'a weight change sends all three weights', async () => {
		const setSetting = jest.fn();
		render( <Stateful spy={ setSetting } /> );
		const lane = screen.getByLabelText( 'Swap weight: lane (0 to 1)' );
		await userEvent.clear( lane );
		await userEvent.type( lane, '0.6' );
		expect( setSetting ).toHaveBeenLastCalledWith( 'swap.weights', {
			lane: 0.6,
			category: 0.3,
			weekday: 0.2,
		} );
	} );
} );
