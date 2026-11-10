import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import PostChip from '../../../client/calendar/PostChip';

const base = {
	id: 12177,
	title: 'Nineteen Years, Four Domains, and a New Front Page',
	color: '#2f5fd0',
	date: '2026-11-11T08:00:00-08:00',
};

describe( 'PostChip', () => {
	it( 'published chip is solid in the category colour', () => {
		render(
			<PostChip
				post={ { ...base, status: 'publish' } }
				onSelect={ () => {} }
			/>
		);
		const chip = screen.getByRole( 'button' );
		expect( chip.getAttribute( 'data-status' ) ).toBe( 'publish' );
		expect( chip.style.backgroundColor ).toBe( 'rgb(47, 95, 208)' );
		expect( chip.style.color ).toBe( 'rgb(255, 255, 255)' );
		expect( chip.textContent ).toContain( '✓ Published · 8:00 am' );
		expect( chip.getAttribute( 'title' ) ).toBe( base.title );
	} );

	it( 'scheduled chip is outlined with a clock glyph', () => {
		render(
			<PostChip
				post={ { ...base, status: 'future' } }
				onSelect={ () => {} }
			/>
		);
		const chip = screen.getByRole( 'button' );
		expect( chip.style.backgroundColor ).toBe( 'rgb(255, 255, 255)' );
		expect( chip.style.borderColor ).toBe( 'rgb(47, 95, 208)' );
		expect( chip.style.color ).toBe( 'rgb(47, 95, 208)' );
		expect( chip.textContent ).toContain( '◷ Scheduled · 8:00 am' );
	} );

	it( 'dated draft chip is tinted and labelled Draft', () => {
		render(
			<PostChip
				post={ { ...base, status: 'draft' } }
				onSelect={ () => {} }
			/>
		);
		const chip = screen.getByRole( 'button' );
		expect( chip.getAttribute( 'data-status' ) ).toBe( 'draft' );
		expect( chip.style.backgroundColor ).toBe( 'rgba(47, 95, 208, 0.14)' );
		expect( chip.textContent ).toContain( 'Draft · 8:00 am' );
	} );

	it( 'clicking a chip selects the post', async () => {
		const onSelect = jest.fn();
		const post = { ...base, status: 'future' };
		render( <PostChip post={ post } onSelect={ onSelect } /> );
		await userEvent.click( screen.getByRole( 'button' ) );
		expect( onSelect ).toHaveBeenCalledWith( post );
		expect(
			screen.getByRole( 'button' ).getAttribute( 'data-post-id' )
		).toBe( '12177' );
	} );
} );
