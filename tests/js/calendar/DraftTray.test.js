import { render, screen } from '@testing-library/react';

import DraftTray from '../../../client/calendar/DraftTray';

const draft = {
	id: 10178,
	title: 'From Craftsmen to Swarms',
	color: '#2f5fd0',
	modified: '2026-10-29T21:46:13-07:00',
	edit_url: 'http://example.test/wp-admin/post.php?post=10178&action=edit',
};

describe( 'DraftTray', () => {
	it( 'shows the count and one row per draft', () => {
		const { container } = render( <DraftTray unscheduled={ [ draft ] } /> );
		expect(
			screen.getByRole( 'heading', { name: 'Unscheduled drafts (1)' } )
		).toBeTruthy();
		const rows = container.querySelectorAll( '[data-post-id]' );
		expect( rows ).toHaveLength( 1 );
		expect( rows[ 0 ].getAttribute( 'data-post-id' ) ).toBe( '10178' );
		expect(
			container.querySelector( '.masthead-tray__title' ).textContent
		).toBe( 'From Craftsmen to Swarms' );
		expect(
			screen
				.getByRole( 'link', { name: /Open draft/ } )
				.getAttribute( 'href' )
		).toBe( draft.edit_url );
	} );

	it( 'shows the edited date', () => {
		render( <DraftTray unscheduled={ [ draft ] } /> );
		expect( screen.getByText( 'edited Oct 29' ) ).toBeTruthy();
	} );

	it( 'shows an empty message', () => {
		render( <DraftTray unscheduled={ [] } /> );
		expect(
			screen.getByRole( 'heading', { name: 'Unscheduled drafts (0)' } )
		).toBeTruthy();
		expect( screen.getByText( 'No unscheduled drafts.' ) ).toBeTruthy();
	} );
} );
