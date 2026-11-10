import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import CategoryLegend, {
	legendCategories,
} from '../../../client/components/CategoryLegend';

const categories = [
	{ slug: 'technology', name: 'Technology', color: '#2f5fd0' },
	{ slug: 'faith', name: 'Faith', color: '#7a5c2e' },
];

describe( 'CategoryLegend', () => {
	it( 'renders one pressed button per visible category', () => {
		render(
			<CategoryLegend
				categories={ categories }
				hidden={ new Set() }
				onToggle={ () => {} }
			/>
		);
		const buttons = screen.getAllByRole( 'button' );
		expect( buttons ).toHaveLength( 2 );
		buttons.forEach( ( button ) =>
			expect( button.getAttribute( 'aria-pressed' ) ).toBe( 'true' )
		);
	} );

	it( 'calls onToggle with the slug', async () => {
		const onToggle = jest.fn();
		render(
			<CategoryLegend
				categories={ categories }
				hidden={ new Set() }
				onToggle={ onToggle }
			/>
		);
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Faith' } )
		);
		expect( onToggle ).toHaveBeenCalledWith( 'faith' );
	} );

	it( 'marks hidden categories as not pressed', () => {
		render(
			<CategoryLegend
				categories={ categories }
				hidden={ new Set( [ 'faith' ] ) }
				onToggle={ () => {} }
			/>
		);
		expect(
			screen
				.getByRole( 'button', { name: 'Faith' } )
				.getAttribute( 'aria-pressed' )
		).toBe( 'false' );
		expect(
			screen
				.getByRole( 'button', { name: 'Technology' } )
				.getAttribute( 'aria-pressed' )
		).toBe( 'true' );
	} );
} );

describe( 'legendCategories', () => {
	const all = [
		{
			slug: 'uncategorized',
			name: 'Uncategorized',
			color: '#8a5409',
			default: true,
		},
		{
			slug: 'technology',
			name: 'Technology',
			color: '#2f5fd0',
			default: false,
		},
		{ slug: 'faith', name: 'Faith', color: '#7a5c2e' },
	];

	it( 'hides the default category while nothing uses it', () => {
		expect(
			legendCategories( all, new Set( [ 'technology' ] ) ).map(
				( category ) => category.slug
			)
		).toEqual( [ 'technology', 'faith' ] );
	} );

	it( 'keeps the default category once something uses it', () => {
		expect(
			legendCategories( all, new Set( [ 'uncategorized' ] ) ).map(
				( category ) => category.slug
			)
		).toEqual( [ 'uncategorized', 'technology', 'faith' ] );
	} );
} );
