import { render, screen } from '@testing-library/react';

import AiSettings from '../../../client/settings/AiSettings';

describe( 'AiSettings', () => {
	it( 'shows the Connectors notice when no provider is supported', () => {
		render(
			<AiSettings
				supported={ false }
				connectorsUrl="http://example.test/wp-admin/options-connectors.php"
			/>
		);
		expect( screen.getByText( 'Provider: none active' ) ).toBeTruthy();
		expect(
			screen.getByText(
				/No AI provider is configured, so Desk suggestions are off/
			)
		).toBeTruthy();
		expect(
			screen
				.getByRole( 'link', { name: 'Set up in Connectors' } )
				.getAttribute( 'href' )
		).toBe( 'http://example.test/wp-admin/options-connectors.php' );
	} );

	it( 'shows available when a provider is supported', () => {
		render( <AiSettings supported connectorsUrl="x" /> );
		expect( screen.getByText( 'Provider: available' ) ).toBeTruthy();
		expect( screen.queryByRole( 'link' ) ).toBeNull();
	} );
} );
