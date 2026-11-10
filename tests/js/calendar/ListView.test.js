import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { resetBoot } from '../../../client/data/boot';
import ListView from '../../../client/calendar/ListView';

const post = ( id, title, status, date, extra = {} ) => ( {
	id,
	title,
	status,
	date,
	color: '#2f5fd0',
	categories: [ 'technology', 'faith' ],
	primary_category: 'technology',
	...extra,
} );

const days = [
	{
		date: '2026-11-09',
		lane: { label: 'Business' },
		posts: [
			post( 12155, 'Mantle 1', 'publish', '2026-11-09T06:23:15-08:00' ),
		],
	},
	{ date: '2026-11-10', lane: { label: 'Technical' }, posts: [] },
	{
		date: '2026-11-11',
		lane: { label: 'Standalone' },
		posts: [
			post(
				12177,
				'Nineteen Years',
				'future',
				'2026-11-11T08:00:00-08:00'
			),
		],
	},
	{
		date: '2026-11-12',
		lane: { label: 'Technical' },
		posts: [
			post( 10194, 'Embeddings', 'future', '2026-11-12T07:37:00-08:00', {
				primary_category: 'faith',
			} ),
		],
	},
];

describe( 'ListView', () => {
	beforeEach( () => {
		window.mastheadBoot = {
			categories: [
				{ slug: 'technology', name: 'Technology', color: '#2f5fd0' },
				{ slug: 'faith', name: 'Faith', color: '#7a5c2e' },
			],
		};
		resetBoot();
	} );

	it( 'lists posts in date order with status', () => {
		const { container } = render(
			<ListView
				days={ days }
				hidden={ new Set() }
				onSelectPost={ () => {} }
			/>
		);
		const rows = [ ...container.querySelectorAll( '[data-post-id]' ) ];
		expect(
			rows.map( ( row ) => row.getAttribute( 'data-post-id' ) )
		).toEqual( [ '12155', '12177', '10194' ] );
		expect( rows[ 0 ].textContent ).toContain(
			'Mon Nov 9 · 6:23 am · Technology, Faith'
		);
		expect( rows[ 0 ].textContent ).toContain( 'Published' );
		expect( rows[ 1 ].textContent ).toContain( 'Scheduled' );
	} );

	it( 'counts scheduled posts in the heading', () => {
		render(
			<ListView
				days={ days }
				hidden={ new Set() }
				onSelectPost={ () => {} }
			/>
		);
		expect(
			screen.getByRole( 'heading', { name: 'Scheduled (2)' } )
		).toBeTruthy();
	} );

	it( 'hides rows of hidden categories', () => {
		render(
			<ListView
				days={ days }
				hidden={ new Set( [ 'faith' ] ) }
				onSelectPost={ () => {} }
			/>
		);
		expect(
			screen.getByRole( 'heading', { name: 'Scheduled (1)' } )
		).toBeTruthy();
	} );

	it( 'selecting a row selects the post', async () => {
		const onSelectPost = jest.fn();
		render(
			<ListView
				days={ days }
				hidden={ new Set() }
				onSelectPost={ onSelectPost }
			/>
		);
		await userEvent.click(
			screen.getByRole( 'button', { name: /Nineteen Years/ } )
		);
		expect( onSelectPost ).toHaveBeenCalledWith( days[ 2 ].posts[ 0 ] );
	} );

	it( 'rows with a review finding read Review and others read Fits', () => {
		const findings = {
			generatedAt: '2026-11-09T14:00:00-08:00',
			byPost: ( id ) =>
				id === 12177 ? [ { severity: 'review' } ] : [],
		};
		const { container } = render(
			<ListView
				days={ days }
				hidden={ new Set() }
				onSelectPost={ () => {} }
				findings={ findings }
			/>
		);
		const verdicts = [
			...container.querySelectorAll( '.masthead-list__verdict' ),
		].map( ( v ) => [
			v.closest( '[data-post-id]' ).getAttribute( 'data-post-id' ),
			v.textContent,
		] );
		expect( verdicts ).toEqual( [
			[ '12177', 'Review' ],
			[ '10194', 'Fits' ],
		] );
		expect( screen.getByText( 'Swap check ran Nov 9' ) ).toBeTruthy();
	} );

	it( 'says when the swap check has not run yet', () => {
		const findings = { generatedAt: null, byPost: () => [] };
		render(
			<ListView
				days={ days }
				hidden={ new Set() }
				onSelectPost={ () => {} }
				findings={ findings }
			/>
		);
		expect( screen.getByText( 'Swap check has not run yet' ) ).toBeTruthy();
	} );
} );
