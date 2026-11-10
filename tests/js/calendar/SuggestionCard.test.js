import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { resetBoot } from '../../../client/data/boot';
import SuggestionCard from '../../../client/calendar/SuggestionCard';

const suggestion = ( extra = {} ) => ( {
	key: 'draft:10178',
	kind: 'draft',
	title: 'From Guilds to Fleets',
	why: "Wednesday is your Standalone slot. This draft isn't on the calendar; last edited Oct 29.",
	post_id: 10178,
	actions: [ 'schedule', 'open', 'dismiss' ],
	...extra,
} );

const renderCard = ( props = {} ) =>
	render(
		<SuggestionCard
			suggestion={ suggestion() }
			onPlace={ () => {} }
			onShare={ () => {} }
			onDismiss={ () => {} }
			{ ...props }
		/>
	);

describe( 'SuggestionCard', () => {
	beforeEach( () => {
		window.mastheadBoot = {
			urls: {
				editPost:
					'http://example.test/wp-admin/post.php?post=%d&action=edit',
			},
		};
		resetBoot();
	} );

	it( 'renders the why line verbatim', () => {
		renderCard();
		expect(
			screen.getByText(
				"Wednesday is your Standalone slot. This draft isn't on the calendar; last edited Oct 29."
			)
		).toBeTruthy();
		expect( screen.getByText( 'DRAFT' ) ).toBeTruthy();
		expect(
			screen
				.getByRole( 'link', { name: 'Open draft' } )
				.getAttribute( 'href' )
		).toBe(
			'http://example.test/wp-admin/post.php?post=10178&action=edit'
		);
	} );

	it( 'Dismiss runs dismiss-suggestion and reloads the date', async () => {
		const onDismiss = jest.fn();
		renderCard( { onDismiss } );
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Dismiss' } )
		);
		expect( onDismiss ).toHaveBeenCalledWith( suggestion() );
	} );

	it( 'Place on this day reschedules the draft to the selected date', async () => {
		const onPlace = jest.fn();
		renderCard( { onPlace } );
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Place on this day' } )
		);
		expect( onPlace ).toHaveBeenCalledWith( suggestion() );
	} );

	it( 'actions the client cannot perform yet are not rendered', () => {
		renderCard( {
			suggestion: suggestion( {
				key: 'gap:8',
				kind: 'gap',
				post_id: undefined,
				actions: [ 'create_draft', 'suggest_topics', 'dismiss' ],
			} ),
		} );
		expect(
			screen.queryByRole( 'button', { name: /Plan this/ } )
		).toBeNull();
		expect( screen.queryByText( /topics/i ) ).toBeNull();
		expect( screen.getAllByRole( 'button' ) ).toHaveLength( 1 );
		expect( screen.queryByRole( 'link' ) ).toBeNull();
	} );

	it( 'a reshare offers Share again', async () => {
		const onShare = jest.fn();
		renderCard( {
			onShare,
			suggestion: suggestion( {
				key: 'reshare:1',
				kind: 'reshare',
				actions: [ 'share', 'open', 'dismiss' ],
			} ),
		} );
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Share again' } )
		);
		expect( onShare ).toHaveBeenCalledTimes( 1 );
		expect(
			screen.getByRole( 'link', { name: 'Open post' } )
		).toBeTruthy();
	} );
} );
