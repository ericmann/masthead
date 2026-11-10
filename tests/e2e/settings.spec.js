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
} );
