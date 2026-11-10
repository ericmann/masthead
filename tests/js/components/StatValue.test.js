import { render } from '@testing-library/react';

import StatValue from '../../../client/components/StatValue';

const numbers = {
	source: 'Jetpack Stats',
	fetched_at: '2026-11-09T05:00:00-08:00',
	values: { visitors: 1234, empty: null },
};

describe( 'StatValue', () => {
	it( 'renders the value with its source and fetched time', () => {
		const { container } = render(
			<StatValue numbers={ numbers } name="visitors" label="Visitors" />
		);
		expect( container.textContent ).toContain( 'Visitors' );
		expect( container.textContent ).toContain( '1,234' );
		expect( container.textContent ).toContain( 'Jetpack Stats' );
		expect( container.textContent ).toContain( '2026-11-09 5:00 am' );
	} );

	it( 'renders Unavailable when numbers is null', () => {
		const { container } = render(
			<StatValue numbers={ null } name="visitors" label="Visitors" />
		);
		expect( container.textContent ).toBe( 'Visitors Unavailable' );
	} );

	it( 'renders Unavailable when the named value is missing', () => {
		const missing = render(
			<StatValue numbers={ numbers } name="views" label="Views" />
		);
		expect( missing.container.textContent ).toBe( 'Views Unavailable' );
		const empty = render(
			<StatValue numbers={ numbers } name="empty" label="Empty" />
		);
		expect( empty.container.textContent ).toBe( 'Empty Unavailable' );
	} );

	it( 'never renders a number without the source', () => {
		const { container } = render(
			<StatValue
				numbers={ { ...numbers, fetched_at: null } }
				name="visitors"
				label="Visitors"
			/>
		);
		expect( container.textContent ).toContain( '1,234' );
		expect( container.textContent ).toContain( 'Jetpack Stats' );
		const unavailable = render(
			<StatValue numbers={ null } name="visitors" label="Visitors" />
		);
		expect( unavailable.container.textContent ).not.toMatch( /\d/ );
	} );
} );
