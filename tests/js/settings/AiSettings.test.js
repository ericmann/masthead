import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from '@wordpress/element';

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

	const values = {
		'ai.models.fast': [ 'claude-haiku-5-5' ],
		'ai.models.strong': [ 'claude-sonnet-5-5' ],
		'ai.monthly_request_cap': 300,
		'ai.context_days': 90,
		'ai.include_draft_excerpts': true,
	};
	const get = ( key ) => values[ key ];

	// Keeps edits so controlled inputs behave like the real settings screen.
	function Stateful( { spy, remaining } ) {
		const [ edited, setEdited ] = useState( values );
		return (
			<AiSettings
				supported
				connectorsUrl="x"
				remaining={ remaining }
				get={ ( key ) => edited[ key ] }
				setSetting={ ( key, value ) => {
					spy( key, value );
					setEdited( ( current ) => ( {
						...current,
						[ key ]: value,
					} ) );
				} }
			/>
		);
	}

	it( 'model lists are edited as comma-separated ids', async () => {
		const setSetting = jest.fn();
		render(
			<AiSettings
				supported
				connectorsUrl="x"
				get={ get }
				setSetting={ setSetting }
			/>
		);
		const fast = screen.getByLabelText( 'Fast model ids' );
		expect( fast.value ).toBe( 'claude-haiku-5-5' );
		await userEvent.clear( fast );
		await userEvent.type( fast, 'a, b ,c' );
		expect( setSetting ).toHaveBeenLastCalledWith( 'ai.models.fast', [
			'a',
			'b',
			'c',
		] );
		expect( fast.value ).toBe( 'a, b ,c' );
		expect(
			screen.getByText(
				"Used only if the active provider offers them; otherwise the provider's default model is used."
			)
		).toBeTruthy();
	} );

	it( 'the draft toggle is labelled Draft titles and excerpts', async () => {
		const setSetting = jest.fn();
		render(
			<AiSettings
				supported
				connectorsUrl="x"
				get={ get }
				setSetting={ setSetting }
			/>
		);
		const toggle = screen.getByLabelText( 'Draft titles and excerpts' );
		expect( toggle.checked ).toBe( true );
		await userEvent.click( toggle );
		expect( setSetting ).toHaveBeenCalledWith(
			'ai.include_draft_excerpts',
			false
		);
		expect( screen.queryByText( /Draft bodies/ ) ).toBeNull();
	} );

	it( 'shows requests left this month and the cap help', async () => {
		const setSetting = jest.fn();
		render( <Stateful spy={ setSetting } remaining={ 41 } /> );
		expect(
			screen.getByText( '41 requests left this month' )
		).toBeTruthy();
		expect( screen.getByText( "0 turns the Desk's AI off." ) ).toBeTruthy();
		const cap = screen.getByLabelText( 'Monthly AI request cap' );
		expect( cap.value ).toBe( '300' );
		await userEvent.clear( cap );
		await userEvent.type( cap, '0' );
		expect( setSetting ).toHaveBeenLastCalledWith(
			'ai.monthly_request_cap',
			0
		);
	} );

	it( 'shows the last model used per tier when known', () => {
		render(
			<AiSettings
				supported
				connectorsUrl="x"
				get={ get }
				models={ { fast: 'claude-haiku-5-5' } }
			/>
		);
		expect(
			screen.getByText( 'Last model used (fast): claude-haiku-5-5' )
		).toBeTruthy();
		expect( screen.queryByText( /does not report/ ) ).toBeNull();
	} );

	it( 'explains who gets prompt_ai, naming the fallback capability', () => {
		const { rerender } = render(
			<AiSettings
				supported
				connectorsUrl="x"
				get={ get }
				fallbackCapability="publish_posts"
			/>
		);
		expect(
			screen.getByText(
				/Masthead grants it to users who have publish_posts\. A role or plugin that grants or denies prompt_ai itself takes precedence\./
			)
		).toBeTruthy();

		rerender( <AiSettings supported connectorsUrl="x" get={ get } /> );
		expect(
			screen.getByText( 'The AI features need the prompt_ai capability.' )
		).toBeTruthy();
		expect( screen.queryByText( /Masthead grants it/ ) ).toBeNull();
	} );

	it( 'shows the model-not-reported message when no model is known', () => {
		render( <AiSettings supported connectorsUrl="x" get={ get } /> );
		expect(
			screen.getByText(
				'The AI Client does not report which model was used.'
			)
		).toBeTruthy();
	} );
} );
