import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import MoveTo from '../../../client/calendar/MoveTo';
import PostChip from '../../../client/calendar/PostChip';

const post = ( status ) => ( {
	id: 10194,
	title: 'What the Embeddings Reveal',
	status,
	color: '#7a5c2e',
	date: '2026-11-12T07:37:00-08:00',
} );

describe( 'MoveTo', () => {
	it( 'published chips have no move control', () => {
		const { container } = render(
			<MoveTo post={ post( 'publish' ) } onMove={ () => {} } />
		);
		expect( container.firstChild ).toBeNull();
		render(
			<PostChip
				post={ post( 'publish' ) }
				onSelect={ () => {} }
				onMove={ () => {} }
			/>
		);
		expect(
			screen.queryByRole( 'button', { name: /to another day/ } )
		).toBeNull();
		expect( screen.getByRole( 'button' ).getAttribute( 'draggable' ) ).toBe(
			'false'
		);
	} );

	it( 'submitting a date calls move', async () => {
		const onMove = jest.fn();
		render( <MoveTo post={ post( 'future' ) } onMove={ onMove } /> );
		await userEvent.click(
			screen.getByRole( 'button', {
				name: 'Move “What the Embeddings Reveal” to another day',
			} )
		);
		const input = screen.getByLabelText(
			'New date for “What the Embeddings Reveal”'
		);
		expect( input.value ).toBe( '2026-11-12' );
		await userEvent.clear( input );
		await userEvent.type( input, '2026-11-13' );
		await userEvent.click( screen.getByRole( 'button', { name: 'Move' } ) );
		expect( onMove ).toHaveBeenCalledWith( post( 'future' ), '2026-11-13' );
	} );

	it( 'the control is reachable and operable from the keyboard', async () => {
		const onMove = jest.fn();
		render( <MoveTo post={ post( 'draft' ) } onMove={ onMove } /> );
		await userEvent.tab();
		expect( document.activeElement.textContent ).toContain( 'Move' );
		await userEvent.keyboard( '{Enter}' );
		const input = screen.getByLabelText( /New date for/ );
		input.focus();
		await userEvent.clear( input );
		await userEvent.type( input, '2026-11-14' );
		await userEvent.keyboard( '{Enter}' );
		expect( onMove ).toHaveBeenCalledWith( post( 'draft' ), '2026-11-14' );
	} );
} );
