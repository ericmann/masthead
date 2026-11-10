import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { resetBoot } from '../../../client/data/boot';
import { runAbility } from '../../../client/data/useAbility';
import DayPlan from '../../../client/calendar/DayPlan';

const mockSave = jest.fn();

jest.mock( '../../../client/data/useAbility', () => ( {
	runAbility: jest.fn(),
} ) );
jest.mock( '@wordpress/data', () => ( {
	useDispatch: () => ( { saveEntityRecord: mockSave } ),
} ) );

const gap = {
	key: 'gap:52',
	kind: 'gap',
	title: 'Something under Business',
	why: 'Nothing has been published under Business yet.',
	category: 'business',
	actions: [ 'create_draft', 'dismiss' ],
};

const draft = {
	key: 'draft:10178',
	kind: 'draft',
	title: 'From Craftsmen to Swarms',
	why: 'Wednesday is your Standalone slot.',
	category: 'technology',
	post_id: 10178,
	actions: [ 'schedule' ],
};

const renderPlan = ( props = {} ) => {
	const handlers = {
		onPick: jest.fn(),
		onCreated: jest.fn().mockResolvedValue( undefined ),
		onBack: jest.fn(),
	};
	render(
		<DayPlan
			suggestion={ draft }
			date="2026-11-18"
			laneLabel="Standalone"
			others={ [ gap ] }
			{ ...handlers }
			{ ...props }
		/>
	);
	return handlers;
};

describe( 'DayPlan', () => {
	beforeEach( () => {
		window.mastheadBoot = {
			rhythm: [
				{ time: '06:30' },
				{ time: '07:00' },
				{ time: '07:30' },
				{ time: '08:00' },
				{ time: '07:30' },
				{ time: '08:00' },
				{ time: '06:30' },
			],
			categories: [
				{ slug: 'technology', name: 'Technology', color: '#2f5fd0' },
				{ slug: 'business', name: 'Business', color: '#8a5409' },
			],
		};
		resetBoot();
		runAbility.mockReset();
		runAbility.mockResolvedValue( {
			post_id: 99,
			edit_url: 'x',
			date: '2026-11-18T08:00:00-08:00',
		} );
		mockSave.mockReset();
		mockSave.mockResolvedValue( {} );
	} );

	it( 'prefills the title and default lane time', () => {
		renderPlan();
		expect( screen.getByLabelText( 'Title' ).value ).toBe(
			'From Craftsmen to Swarms'
		);
		expect( screen.getByLabelText( 'Time' ).value ).toBe( '08:00' );
		expect(
			screen.getByRole( 'heading', {
				name: 'Plan Wednesday, Nov 18 · Standalone lane',
			} )
		).toBeTruthy();
		expect( screen.getByText( 'Technology' ) ).toBeTruthy();
		expect(
			screen.getByText( 'Wednesday is your Standalone slot.' )
		).toBeTruthy();
		expect(
			screen.getByRole( 'button', { name: 'Create draft' } )
		).toBeTruthy();
		expect(
			screen.getByRole( 'button', { name: 'Something under Business' } )
		).toBeTruthy();
	} );

	it( 'a gap suggestion requires a title', async () => {
		renderPlan( { suggestion: gap, others: [] } );
		expect( screen.getByLabelText( 'Title' ).value ).toBe( '' );
		const create = screen.getByRole( 'button', { name: 'Create draft' } );
		expect( create.disabled ).toBe( true );
		await userEvent.type(
			screen.getByLabelText( 'Title' ),
			'Why leaders read'
		);
		expect( create.disabled ).toBe( false );
	} );

	it( 'Create draft runs create-planned-draft with the chosen time and category', async () => {
		const { onCreated, onBack } = renderPlan( {
			suggestion: gap,
			others: [],
		} );
		await userEvent.type(
			screen.getByLabelText( 'Title' ),
			'Why leaders read'
		);
		fireEvent.change( screen.getByLabelText( 'Time' ), {
			target: { value: '09:15' },
		} );
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Create draft' } )
		);
		expect( runAbility ).toHaveBeenCalledWith(
			'masthead/create-planned-draft',
			{
				date: '2026-11-18',
				time: '09:15',
				title: 'Why leaders read',
				category: 'business',
			}
		);
		await waitFor( () => expect( onBack ).toHaveBeenCalled() );
		expect( onCreated ).toHaveBeenCalled();
		expect( mockSave ).not.toHaveBeenCalled();
	} );

	it( 'passes the series and outline through', async () => {
		renderPlan( {
			suggestion: {
				...draft,
				kind: 'series',
				series: { term_id: 3, part: 2 },
				outline: [ 'One', 'Two' ],
			},
			others: [],
		} );
		expect(
			screen
				.getAllByRole( 'listitem' )
				.map( ( item ) => item.textContent )
		).toEqual( [ 'One', 'Two' ] );
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Create draft with outline' } )
		);
		expect( runAbility.mock.calls[ 0 ][ 1 ] ).toMatchObject( {
			series: { term_id: 3, part: 2 },
			outline: [ 'One', 'Two' ],
		} );
	} );

	it( 'Scheduled sets the new post to future after creating it', async () => {
		const { onBack } = renderPlan();
		await userEvent.selectOptions(
			screen.getByLabelText( 'Save as' ),
			'scheduled'
		);
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Create draft' } )
		);
		await waitFor( () => expect( onBack ).toHaveBeenCalled() );
		expect( mockSave ).toHaveBeenCalledWith( 'postType', 'post', {
			id: 99,
			status: 'future',
		} );
	} );

	it( 'a failed scheduling step keeps the draft and shows the error', async () => {
		mockSave.mockRejectedValue( { message: 'Not allowed.' } );
		const { onBack, onCreated } = renderPlan();
		await userEvent.selectOptions(
			screen.getByLabelText( 'Save as' ),
			'scheduled'
		);
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Create draft' } )
		);
		expect(
			await screen.findByText(
				'The draft was kept, but scheduling failed: Not allowed.'
			)
		).toBeTruthy();
		expect( onCreated ).toHaveBeenCalled();
		expect( onBack ).not.toHaveBeenCalled();
		expect(
			screen.getByRole( 'button', { name: 'Create draft' } ).disabled
		).toBe( true );
	} );

	it( 'Back to suggestions returns to the rail', async () => {
		const { onBack, onPick } = renderPlan();
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Back to suggestions' } )
		);
		expect( onBack ).toHaveBeenCalled();
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Something under Business' } )
		);
		expect( onPick ).toHaveBeenCalledWith( gap );
	} );

	describe( 'Draft an outline', () => {
		const withAi = (
			ai = { supported: true },
			caps = { promptAi: true }
		) => {
			window.mastheadBoot = { ...window.mastheadBoot, ai, caps };
			resetBoot();
		};

		it( 'is hidden when AI is unsupported or not permitted', () => {
			withAi( { supported: false } );
			const first = render(
				<DayPlan
					suggestion={ gap }
					date="2026-11-18"
					laneLabel="Standalone"
					onPick={ () => {} }
					onCreated={ () => {} }
					onBack={ () => {} }
				/>
			);
			expect( screen.queryByText( 'Draft an outline' ) ).toBeNull();
			first.unmount();
			withAi( { supported: true }, { promptAi: false } );
			renderPlan();
			expect( screen.queryByText( 'Draft an outline' ) ).toBeNull();
		} );

		it( 'the returned outline is editable and sent with create-planned-draft', async () => {
			withAi();
			runAbility.mockImplementation( async ( name ) =>
				name === 'masthead/draft-outline'
					? { outline: [ 'First', 'Second', 'Third' ] }
					: { post_id: 99, edit_url: 'x', date: 'd' }
			);
			renderPlan( { suggestion: gap, others: [] } );
			await userEvent.type(
				screen.getByLabelText( 'Title' ),
				'Why leaders read'
			);
			await userEvent.click(
				screen.getByRole( 'button', { name: 'Draft an outline' } )
			);
			expect( runAbility ).toHaveBeenCalledWith(
				'masthead/draft-outline',
				{
					title: 'Why leaders read',
					date: '2026-11-18',
					category: 'business',
				}
			);
			const first = await screen.findByLabelText( 'Outline line 1' );
			expect( first.value ).toBe( 'First' );
			expect(
				screen.getByText(
					'Outline only. The Desk never writes body copy.'
				)
			).toBeTruthy();

			await userEvent.clear( first );
			await userEvent.type( first, 'Opening' );
			await userEvent.click(
				screen.getByRole( 'button', { name: 'Remove line 2' } )
			);
			await userEvent.click(
				screen.getByRole( 'button', { name: 'Add line' } )
			);
			await userEvent.type(
				screen.getByLabelText( 'Outline line 3' ),
				'Closing'
			);
			await userEvent.click(
				screen.getByRole( 'button', {
					name: 'Create draft with outline',
				} )
			);
			const create = runAbility.mock.calls.find(
				( call ) => call[ 0 ] === 'masthead/create-planned-draft'
			);
			expect( create[ 1 ].outline ).toEqual( [
				'Opening',
				'Third',
				'Closing',
			] );
		} );

		it( 'an outline error leaves the form usable', async () => {
			withAi();
			runAbility.mockRejectedValueOnce( {
				code: 'masthead_ai_budget',
				message: 'The monthly AI request limit has been reached.',
			} );
			renderPlan();
			await userEvent.click(
				screen.getByRole( 'button', { name: 'Draft an outline' } )
			);
			expect(
				await screen.findByText(
					'The monthly AI request limit has been reached.'
				)
			).toBeTruthy();
			expect( screen.getByLabelText( 'Title' ).disabled ).toBe( false );
			expect(
				screen.getByRole( 'button', { name: 'Create draft' } ).disabled
			).toBe( false );
			expect(
				screen.getByRole( 'button', { name: 'Draft an outline' } )
					.disabled
			).toBe( false );
		} );
	} );
} );
