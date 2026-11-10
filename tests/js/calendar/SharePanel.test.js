import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { resetBoot } from '../../../client/data/boot';
import { runAbility } from '../../../client/data/useAbility';
import SharePanel, {
	withSiteOffset,
} from '../../../client/calendar/SharePanel';

jest.mock( '../../../client/data/useAbility', () => ( {
	runAbility: jest.fn(),
} ) );

const post = {
	id: 12155,
	title: 'The Mantle - Part 1 of 4',
	status: 'publish',
	date: '2026-11-09T06:23:15-08:00',
};

const connections = {
	available: true,
	connections: [
		{ id: 7, service: 'mastodon', display_name: 'Me' },
		{ id: 9, service: 'bluesky', display_name: 'Me too' },
	],
};

describe( 'SharePanel', () => {
	beforeEach( () => {
		window.mastheadBoot = { timezone: 'America/Los_Angeles' };
		resetBoot();
		runAbility.mockReset();
	} );

	it( 'shows the reason and no controls when sharing is unavailable', async () => {
		runAbility.mockResolvedValue( {
			available: false,
			connections: [],
			reason: 'Jetpack Social is not connected on this site, so sharing is unavailable.',
		} );
		const { container } = render( <SharePanel post={ post } /> );
		expect(
			await screen.findByText(
				/Sharing is unavailable: Jetpack Social is not connected/
			)
		).toBeTruthy();
		expect( screen.getByText( 'Published Nov 9' ) ).toBeTruthy();
		expect(
			container.querySelector( 'textarea, input, button' )
		).toBeNull();
		expect( runAbility ).toHaveBeenCalledWith(
			'masthead/share-connections',
			{}
		);
	} );

	it( 'lists connections as unchecked checkboxes', async () => {
		runAbility.mockResolvedValue( connections );
		render( <SharePanel post={ post } /> );
		const boxes = await screen.findAllByRole( 'checkbox' );
		expect( boxes ).toHaveLength( 2 );
		boxes.forEach( ( box ) => expect( box.checked ).toBe( false ) );
		expect( screen.getByLabelText( 'Message' ).value ).toBe( '' );
		expect( screen.getByLabelText( 'Me (mastodon)' ) ).toBeTruthy();
	} );

	it( 'buttons are disabled until there is a message and a connection', async () => {
		runAbility.mockResolvedValue( connections );
		render( <SharePanel post={ post } /> );
		await screen.findByLabelText( 'Message' );
		const share = screen.getByRole( 'button', { name: 'Share now' } );
		expect( share.disabled ).toBe( true );
		await userEvent.type(
			screen.getByLabelText( 'Message' ),
			'Worth a read'
		);
		expect( share.disabled ).toBe( true );
		await userEvent.click( screen.getByLabelText( 'Me (mastodon)' ) );
		expect( share.disabled ).toBe( false );
		expect(
			screen.getByRole( 'button', { name: 'Queue for this date' } )
				.disabled
		).toBe( true );
	} );

	it( 'Share now runs share-post with the message and connection ids', async () => {
		runAbility.mockResolvedValueOnce( connections );
		runAbility.mockResolvedValueOnce( { status: 'shared', results: [] } );
		render( <SharePanel post={ post } /> );
		await userEvent.type(
			await screen.findByLabelText( 'Message' ),
			'Worth a read'
		);
		await userEvent.click( screen.getByLabelText( 'Me (mastodon)' ) );
		await userEvent.click( screen.getByLabelText( 'Me too (bluesky)' ) );
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Share now' } )
		);
		expect( runAbility ).toHaveBeenLastCalledWith( 'masthead/share-post', {
			post_id: 12155,
			message: 'Worth a read',
			connection_ids: [ 7, 9 ],
		} );
		expect( await screen.findByText( 'Shared.' ) ).toBeTruthy();
	} );

	it( 'Queue sends at with the site offset', async () => {
		runAbility.mockResolvedValueOnce( connections );
		runAbility.mockResolvedValueOnce( { status: 'queued', results: [] } );
		render( <SharePanel post={ post } /> );
		await userEvent.type(
			await screen.findByLabelText( 'Message' ),
			'Later'
		);
		await userEvent.click( screen.getByLabelText( 'Me (mastodon)' ) );
		await userEvent.type(
			screen.getByLabelText( 'Queue for date' ),
			'2026-11-12T09:00'
		);
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Queue for this date' } )
		);
		await waitFor( () =>
			expect( runAbility ).toHaveBeenLastCalledWith(
				'masthead/share-post',
				{
					post_id: 12155,
					message: 'Later',
					connection_ids: [ 7 ],
					at: '2026-11-12T09:00:00-08:00',
				}
			)
		);
		expect( await screen.findByText( 'Share queued.' ) ).toBeTruthy();
		expect(
			withSiteOffset( '2026-07-12T09:00', 'America/Los_Angeles' )
		).toBe( '2026-07-12T09:00:00-07:00' );
	} );

	it( 'a scheduling refusal hides the queue controls and shows the reason', async () => {
		runAbility.mockResolvedValueOnce( connections );
		runAbility.mockRejectedValueOnce( {
			code: 'masthead_share_unavailable',
			message: 'Scheduling is not on your plan.',
		} );
		render( <SharePanel post={ post } /> );
		await userEvent.type(
			await screen.findByLabelText( 'Message' ),
			'Later'
		);
		await userEvent.click( screen.getByLabelText( 'Me (mastodon)' ) );
		await userEvent.type(
			screen.getByLabelText( 'Queue for date' ),
			'2026-11-12T09:00'
		);
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Queue for this date' } )
		);
		expect(
			await screen.findByText( 'Scheduling is not on your plan.' )
		).toBeTruthy();
		expect(
			screen.queryByRole( 'button', { name: 'Queue for this date' } )
		).toBeNull();
		expect(
			screen.getByRole( 'button', { name: 'Share now' } )
		).toBeTruthy();
	} );
} );
