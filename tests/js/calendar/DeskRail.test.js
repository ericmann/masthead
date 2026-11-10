import { render, screen, renderHook, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { resetBoot } from '../../../client/data/boot';
import DeskRail from '../../../client/calendar/DeskRail';
import { useSuggestions } from '../../../client/calendar/useSuggestions';
import { runAbility } from '../../../client/data/useAbility';

jest.mock( '../../../client/data/useAbility', () => ( {
	runAbility: jest.fn(),
} ) );

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
			title: 'From Guilds to Fleets',
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
		expect(
			screen.getByRole( 'heading', { name: 'This week · Nov 8–14' } )
		).toBeTruthy();
		expect( container.querySelector( '.masthead-desk__date' ) ).toBeNull();
	} );

	it( 'tints each kind label with the lane colour', () => {
		window.mastheadBoot = {
			urls: { editPost: 'x%d' },
			categories: [
				{ slug: 'technology', name: 'Technology', color: '#2f5fd0' },
			],
		};
		resetBoot();
		renderRail( {
			selectedDate: '2026-11-18',
			result: {
				...result,
				suggestions: [
					{ ...result.suggestions[ 0 ], category: 'technology' },
				],
			},
		} );
		// Standalone has no category, so the draft's own category colours it.
		expect( screen.getByText( 'DRAFT' ).style.color ).toBe(
			'rgb(47, 95, 208)'
		);
	} );

	it( "shows the selected day's lane and suggestions", () => {
		renderRail( { selectedDate: '2026-11-18', result } );
		expect(
			screen.getByRole( 'heading', {
				name: 'Wednesday, Nov 18 · Standalone lane',
			} )
		).toBeTruthy();
		expect( screen.getByRole( 'heading', { name: 'Desk' } ) ).toBeTruthy();
		expect( screen.getByText( 'Wed, Nov 18' ).className ).toBe(
			'masthead-desk__date'
		);
		expect( screen.getByText( 'From Guilds to Fleets' ) ).toBeTruthy();
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

	describe( 'Suggest ideas', () => {
		const boot = ( ai, caps ) => {
			window.mastheadBoot = { urls: { editPost: 'x%d' }, ai, caps };
			resetBoot();
		};

		it( 'is shown only when AI is supported and permitted', () => {
			const props = {
				selectedDate: '2026-11-18',
				result,
				onSuggestIdeas: () => {},
			};
			boot( { supported: true }, { promptAi: true } );
			const first = renderRail( props );
			expect(
				screen.getByRole( 'button', { name: 'Suggest ideas' } )
			).toBeTruthy();
			first.unmount();

			boot( { supported: false }, { promptAi: true } );
			const second = renderRail( props );
			expect( screen.queryByText( 'Suggest ideas' ) ).toBeNull();
			second.unmount();

			boot( { supported: true }, { promptAi: false } );
			const third = renderRail( props );
			expect( screen.queryByText( 'Suggest ideas' ) ).toBeNull();
			third.unmount();

			boot( { supported: true }, { promptAi: true } );
			renderRail( { ...props, selectedDate: '2026-11-11' } );
			expect( screen.queryByText( 'Suggest ideas' ) ).toBeNull();
		} );

		it( 'hides the button once ideas are loaded and shows an AI error', () => {
			boot( { supported: true }, { promptAi: true } );
			const props = {
				selectedDate: '2026-11-18',
				onSuggestIdeas: () => {},
			};
			const first = renderRail( {
				...props,
				result: { ...result, ai: { used: true, remaining: 3 } },
			} );
			expect( screen.queryByText( 'Suggest ideas' ) ).toBeNull();
			first.unmount();
			renderRail( {
				...props,
				result: {
					...result,
					ai: { used: false, error: 'masthead_ai_budget' },
				},
			} );
			expect(
				screen.getByText(
					'The monthly AI request limit has been reached.'
				)
			).toBeTruthy();
		} );

		it( 'calls onSuggestIdeas with the date from the button and from Suggest topics', async () => {
			boot( { supported: true }, { promptAi: true } );
			const onSuggestIdeas = jest.fn();
			renderRail( {
				selectedDate: '2026-11-18',
				result: {
					...result,
					suggestions: [
						{
							key: 'gap:14',
							kind: 'gap',
							title: 'Something under Security',
							why: 'Quiet.',
							actions: [ 'suggest_topics' ],
						},
					],
				},
				onSuggestIdeas,
			} );
			await userEvent.click(
				screen.getByRole( 'button', { name: 'Suggest ideas' } )
			);
			await userEvent.click(
				screen.getByRole( 'button', { name: 'Suggest topics' } )
			);
			expect( onSuggestIdeas ).toHaveBeenCalledTimes( 2 );
			expect( onSuggestIdeas ).toHaveBeenNthCalledWith( 1, '2026-11-18' );
			expect( onSuggestIdeas ).toHaveBeenNthCalledWith( 2, '2026-11-18' );
		} );

		it( 'reloads the date with include_ideas', async () => {
			runAbility.mockReset();
			runAbility.mockResolvedValueOnce( result );
			const { result: hook } = renderHook( () => useSuggestions() );
			await act( async () => {
				await hook.current.load( '2026-11-18' );
			} );
			expect( runAbility ).toHaveBeenLastCalledWith(
				'masthead/suggest-for-date',
				{ date: '2026-11-18' }
			);

			const withIdeas = { ...result, ai: { used: true, remaining: 3 } };
			runAbility.mockResolvedValueOnce( withIdeas );
			await act( async () => {
				await hook.current.loadIdeas( '2026-11-18' );
			} );
			expect( runAbility ).toHaveBeenLastCalledWith(
				'masthead/suggest-for-date',
				{ date: '2026-11-18', include_ideas: true }
			);
			expect( hook.current.get( '2026-11-18' ) ).toEqual( withIdeas );

			runAbility.mockRejectedValueOnce( {
				code: 'masthead_ai_budget',
				message: 'x',
			} );
			await act( async () => {
				await hook.current.loadIdeas( '2026-11-18' );
			} );
			expect( hook.current.get( '2026-11-18' ).ai.error ).toBe(
				'masthead_ai_budget'
			);
		} );
	} );
} );
