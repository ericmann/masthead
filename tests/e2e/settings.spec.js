import { test, expect } from '@playwright/test';

import { expectNoSeriousViolations } from './helpers/axe.mjs';
import { seedFixture, wpCli } from './helpers/wp.mjs';

const SETTINGS = '/wp-admin/options-general.php?page=masthead-settings';

test.describe( 'Settings › Calendar', () => {
	test.beforeAll( () => {
		seedFixture();
		wpCli( [ 'option', 'update', 'start_of_week', '0' ] );
	} );

	test.beforeEach( async ( { page } ) => {
		await page.goto( SETTINGS );
		await page.waitForSelector( '.masthead-rhythm tbody tr' );
	} );

	test( 'Settings › Calendar shows the seeded rhythm', async ( { page } ) => {
		await expect(
			page.getByRole( 'heading', { name: 'Calendar Settings' } )
		).toBeVisible();
		await expect( page.locator( '.masthead-rhythm tbody tr' ) ).toHaveCount(
			7
		);
		await expect( page.getByLabel( 'Wednesday lane label' ) ).toHaveValue(
			'Standalone'
		);
		await expect( page.getByLabel( 'Friday lane label' ) ).toHaveValue(
			'Journal & build log'
		);
		await expect(
			page.getByLabel( 'Thursday categories' ).locator( 'option:checked' )
		).toHaveCount( 2 );
	} );

	test( "changing Friday's time and saving persists after reload", async ( {
		page,
	} ) => {
		await page.getByLabel( 'Friday default time' ).fill( '09:15' );
		await page.getByRole( 'button', { name: 'Save changes' } ).click();
		await expect( page.getByText( 'Settings saved.' ) ).toBeVisible();
		await page.reload();
		await page.waitForSelector( '.masthead-rhythm tbody tr' );
		await expect( page.getByLabel( 'Friday default time' ) ).toHaveValue(
			'09:15'
		);
	} );

	test( 'settings page has no serious or critical axe violations', async ( {
		page,
	} ) => {
		await expectNoSeriousViolations( page, '#masthead-settings-root' );
	} );

	test( 'The Mantle shows every 4 weeks and can be marked as a lane exception', async ( {
		page,
	} ) => {
		await expect(
			page.getByLabel( 'The Mantle: every N weeks' )
		).toHaveValue( '4' );
		await expect( page.getByLabel( 'The Mantle: weekday' ) ).toHaveValue(
			''
		);
		const exception = page.getByLabel( 'The Mantle: lane exception' );
		await expect( exception ).not.toBeChecked();
		await exception.check();
		await page.getByRole( 'button', { name: 'Save changes' } ).click();
		await expect( page.getByText( 'Settings saved.' ) ).toBeVisible();
		await page.reload();
		await page.waitForSelector( '.masthead-rhythm tbody tr' );
		await expect(
			page.getByLabel( 'The Mantle: lane exception' )
		).toBeChecked();
	} );

	test( 'the exposure toggles persist after reload', async ( { page } ) => {
		const share = page.getByLabel( 'Also expose sharing to MCP clients' );
		await expect(
			page.getByLabel( 'Expose calendar abilities to MCP clients' )
		).toBeChecked();
		await expect( share ).not.toBeChecked();
		await share.check();
		await page.getByRole( 'button', { name: 'Save changes' } ).click();
		await expect( page.getByText( 'Settings saved.' ) ).toBeVisible();
		await page.reload();
		await page.waitForSelector( '.masthead-rhythm tbody tr' );
		await expect(
			page.getByLabel( 'Also expose sharing to MCP clients' )
		).toBeChecked();
	} );

	test( 'the AI section links to Connectors when no provider is configured', async ( {
		page,
	} ) => {
		wpCli( [ 'option', 'update', 'masthead_e2e_ai', '0' ] );
		try {
			await page.reload();
			await page.waitForSelector( '.masthead-rhythm tbody tr' );
			await expect(
				page.getByText( 'Provider: none active' )
			).toBeVisible();
			await expect(
				page.getByRole( 'link', { name: 'Set up in Connectors' } )
			).toHaveAttribute( 'href', /options-connectors\.php$/ );
		} finally {
			wpCli( [ 'option', 'delete', 'masthead_e2e_ai' ] );
		}
	} );

	test( 'the AI section shows the provider, the budget and the model message', async ( {
		page,
	} ) => {
		await expect( page.getByText( 'Provider: available' ) ).toBeVisible();
		await expect(
			page.getByText( /requests left this month/ )
		).toBeVisible();
		await expect(
			page.getByText(
				'The AI Client does not report which model was used.'
			)
		).toBeVisible();
		await expect(
			page.getByLabel( 'Draft titles and excerpts' )
		).toBeChecked();
	} );

	test( 'changing the monthly request cap persists after reload', async ( {
		page,
	} ) => {
		const cap = page.getByLabel( 'Monthly AI request cap' );
		await expect( cap ).toHaveValue( '300' );
		await cap.fill( '150' );
		await page.getByRole( 'button', { name: 'Save changes' } ).click();
		await expect( page.getByText( 'Settings saved.' ) ).toBeVisible();
		await page.reload();
		await page.waitForSelector( '.masthead-rhythm tbody tr' );
		await expect( page.getByLabel( 'Monthly AI request cap' ) ).toHaveValue(
			'150'
		);
		await expect(
			page.getByText( '150 requests left this month' )
		).toBeVisible();
		// Only the changed key is stored.
		const stored = JSON.parse(
			wpCli( [ 'option', 'get', 'masthead_settings', '--format=json' ] )
		);
		expect( stored.ai.monthly_request_cap ).toBe( 150 );
		expect( stored.desk ).toBeUndefined();
	} );

	test( 'Desk settings show the defaults and have no serious or critical axe violations', async ( {
		page,
	} ) => {
		await expect(
			page.getByLabel( 'Planning horizon (days)' )
		).toHaveValue( '21' );
		await expect( page.getByLabel( 'Category gap (days)' ) ).toHaveValue(
			'45'
		);
		await expect(
			page.getByLabel( 'Swap weight: lane (0 to 1)' )
		).toHaveValue( '0.5' );
		await expectNoSeriousViolations( page, '.masthead-desk-settings' );
	} );
} );
