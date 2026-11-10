import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { resetBoot } from '../../../client/data/boot';
import { runAbility } from '../../../client/data/useAbility';
import AskDesk from '../../../client/calendar/AskDesk';
import DeskRail from '../../../client/calendar/DeskRail';

jest.mock( '../../../client/data/useAbility', () => ( {
	runAbility: jest.fn(),
} ) );

const proposal = {
	name: 'Locking things down',
	category: 'security',
	cadence: { every_weeks: 1, weekday: 4, time: '07:30' },
	lane: { slug: 'technology', label: 'Technical series', categories: [] },
	parts: [
		{
			date: '2026-11-12',
			title: 'Start with the threat',
			outline: [ 'Who', 'What' ],
		},
		{
			date: '2026-11-19',
			title: 'Passwords without the pain',
			outline: [ 'Managers' ],
		},
	],
};

const boot = ( ai = { supported: true }, caps = { promptAi: true } ) => {
	window.mastheadBoot = {
		ai,
		caps,
		urls: { connectors: 'http://x/options-connectors.php' },
	};
	resetBoot();
};

const ask = async () => {
	await userEvent.type(
		screen.getByLabelText( 'Ask the Desk' ),
		'a security series'
	);
	await userEvent.click( screen.getByRole( 'button', { name: 'Ask' } ) );
	await screen.findByLabelText( 'Series name' );
};

describe( 'AskDesk', () => {
	beforeEach( () => {
		boot();
		runAbility.mockReset();
		runAbility.mockImplementation( async ( name ) =>
			name === 'masthead/propose-series'
				? proposal
				: { term_id: 5, post_ids: [ 1, 2 ] }
		);
	} );

	it( 'is replaced by a Connectors hint when AI is unsupported', () => {
		boot( { supported: false } );
		render(
			<DeskRail
				days={ [] }
				today="2026-11-09"
				weekStartsOn={ 0 }
				selectedDate={ null }
				result={ null }
				onPlace={ () => {} }
				onShare={ () => {} }
				onDismiss={ () => {} }
			/>
		);
		expect(
			screen.getByText( /Ask the Desk needs an AI provider/ )
		).toBeTruthy();
		expect(
			screen.getByRole( 'link', { name: 'Settings › Connectors' } ).href
		).toBe( 'http://x/options-connectors.php' );
		expect( screen.queryByRole( 'button', { name: 'Ask' } ) ).toBeNull();
	} );

	it( 'shows the box in the rail when AI is supported and permitted', () => {
		render(
			<DeskRail
				days={ [] }
				today="2026-11-09"
				weekStartsOn={ 0 }
				selectedDate={ null }
				result={ null }
				onPlace={ () => {} }
				onShare={ () => {} }
				onDismiss={ () => {} }
			/>
		);
		expect( screen.getByRole( 'button', { name: 'Ask' } ) ).toBeTruthy();
		expect(
			screen.getByRole( 'link', { name: 'Settings › Connectors' } )
		).toBeTruthy();
	} );

	it( 'asking runs propose-series and lists the dated parts', async () => {
		render( <AskDesk /> );
		await ask();
		expect( runAbility ).toHaveBeenCalledWith( 'masthead/propose-series', {
			prompt: 'a security series',
		} );
		expect( screen.getByLabelText( 'Series name' ).value ).toBe(
			'Locking things down'
		);
		expect( screen.getByText( 'Lane: Technical series' ) ).toBeTruthy();
		expect( screen.getByLabelText( 'Part 1 date' ).value ).toBe(
			'2026-11-12'
		);
		expect( screen.getByLabelText( 'Part 2 title' ).value ).toBe(
			'Passwords without the pain'
		);
		expect( screen.getByText( 'Managers' ) ).toBeTruthy();
	} );

	it( 'edited dates and titles are sent to accept-series', async () => {
		const onCreated = jest.fn().mockResolvedValue( undefined );
		render( <AskDesk onCreated={ onCreated } /> );
		await ask();
		const date = screen.getByLabelText( 'Part 1 date' );
		await userEvent.clear( date );
		await userEvent.type( date, '2026-11-13' );
		const title = screen.getByLabelText( 'Part 1 title' );
		await userEvent.clear( title );
		await userEvent.type( title, 'Edited' );
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Remove part 2' } )
		);
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Accept series' } )
		);
		await waitFor( () => expect( onCreated ).toHaveBeenCalled() );
		expect( runAbility ).toHaveBeenLastCalledWith(
			'masthead/accept-series',
			{
				name: 'Locking things down',
				category: 'security',
				cadence: proposal.cadence,
				parts: [
					{
						date: '2026-11-13',
						title: 'Edited',
						outline: [ 'Who', 'What' ],
					},
				],
			}
		);
		expect(
			await screen.findAllByText( 'Created 2 planned drafts' )
		).not.toHaveLength( 0 );
		expect( screen.queryByLabelText( 'Series name' ) ).toBeNull();
	} );

	it( 'discard clears the proposal without any write', async () => {
		render( <AskDesk /> );
		await ask();
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Discard' } )
		);
		expect( screen.queryByLabelText( 'Series name' ) ).toBeNull();
		expect(
			runAbility.mock.calls.filter(
				( call ) => call[ 0 ] === 'masthead/accept-series'
			)
		).toHaveLength( 0 );
	} );

	it( 'an error keeps the proposal and shows the message', async () => {
		render( <AskDesk /> );
		await ask();
		runAbility.mockRejectedValueOnce( {
			code: 'masthead_series_exists',
			message: 'A series with that name already exists.',
		} );
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Accept series' } )
		);
		expect(
			await screen.findByText( 'A series with that name already exists.' )
		).toBeTruthy();
		expect( screen.getByLabelText( 'Series name' ).value ).toBe(
			'Locking things down'
		);
		expect(
			screen.getByRole( 'button', { name: 'Accept series' } ).disabled
		).toBe( false );
	} );

	it( 'a propose error shows the budget message', async () => {
		runAbility.mockReset();
		runAbility.mockRejectedValue( {
			code: 'masthead_ai_budget',
			message: 'The monthly AI request limit has been reached.',
		} );
		render( <AskDesk /> );
		await userEvent.type( screen.getByLabelText( 'Ask the Desk' ), 'x' );
		await userEvent.click( screen.getByRole( 'button', { name: 'Ask' } ) );
		expect(
			await screen.findByText(
				'The monthly AI request limit has been reached.'
			)
		).toBeTruthy();
	} );
} );
