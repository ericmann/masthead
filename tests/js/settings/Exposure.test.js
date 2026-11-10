import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import Exposure from '../../../client/settings/Exposure';

describe( 'Exposure', () => {
	it( 'toggles expose_mcp and expose_share in the settings', async () => {
		const setSetting = jest.fn();
		render(
			<Exposure
				values={ { exposeMcp: true, exposeShare: false } }
				setSetting={ setSetting }
			/>
		);
		const mcp = screen.getByLabelText(
			'Expose calendar abilities to MCP clients'
		);
		const share = screen.getByLabelText(
			'Also expose sharing to MCP clients'
		);
		expect( mcp.checked ).toBe( true );
		expect( share.checked ).toBe( false );
		await userEvent.click( mcp );
		expect( setSetting ).toHaveBeenLastCalledWith(
			'abilities.expose_mcp',
			false
		);
		await userEvent.click( share );
		expect( setSetting ).toHaveBeenLastCalledWith(
			'abilities.expose_share',
			true
		);
	} );
} );
