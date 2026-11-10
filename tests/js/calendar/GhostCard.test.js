import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import GhostCard from '../../../client/calendar/GhostCard';

const suggestion = { kind: 'draft', title: 'From Craftsmen to Swarms' };

describe( 'GhostCard', () => {
	it( 'renders the kind label and title', () => {
		render(
			<GhostCard
				suggestion={ suggestion }
				color="#787c82"
				onSelect={ () => {} }
			/>
		);
		expect( screen.getByText( 'DRAFT' ) ).toBeTruthy();
		expect( screen.getByText( 'From Craftsmen to Swarms' ) ).toBeTruthy();
		expect( screen.getByRole( 'button' ).getAttribute( 'data-kind' ) ).toBe(
			'draft'
		);
		render(
			<GhostCard
				suggestion={ { kind: 'gap', title: 'x' } }
				color="#000"
				onSelect={ () => {} }
			/>
		);
		expect( screen.getByText( 'GAP' ) ).toBeTruthy();
	} );

	it( 'uses the lane colour for the dashed border', () => {
		render(
			<GhostCard
				suggestion={ suggestion }
				color="#2f5fd0"
				onSelect={ () => {} }
			/>
		);
		const card = screen.getByRole( 'button' );
		expect( card.style.borderColor ).toBe( 'rgb(47, 95, 208)' );
		expect( card.className ).toContain( 'masthead-ghost' );
	} );

	it( 'selects the day when clicked', async () => {
		const onSelect = jest.fn();
		render(
			<GhostCard
				suggestion={ suggestion }
				color="#2f5fd0"
				onSelect={ onSelect }
			/>
		);
		await userEvent.click( screen.getByRole( 'button' ) );
		expect( onSelect ).toHaveBeenCalledTimes( 1 );
	} );
} );
