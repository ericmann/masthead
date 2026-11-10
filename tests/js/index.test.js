import { act } from '@testing-library/react';

jest.mock( '@wordpress/dom-ready', () => ( {
	__esModule: true,
	default: () => {},
} ) );

import { mountAll } from '../../client/index';

describe( 'client entry', () => {
	afterEach( () => {
		document.body.innerHTML = '';
	} );

	it( 'mounts the calendar app on #masthead-calendar-root', async () => {
		document.body.innerHTML = '<div id="masthead-calendar-root"></div>';
		await act( async () => {
			mountAll( document );
		} );
		expect(
			document.querySelector(
				'#masthead-calendar-root .masthead-calendar'
			)
		).not.toBeNull();
	} );

	it( 'does nothing when no root element exists', () => {
		expect( () => mountAll( document ) ).not.toThrow();
		expect( document.body.innerHTML ).toBe( '' );
	} );
} );
