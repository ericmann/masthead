import { test, expect } from '@playwright/test';

import { expectNoSeriousViolations } from './helpers/axe.mjs';
import { seedFixture, wpCli } from './helpers/wp.mjs';

const CALENDAR = '/wp-admin/edit.php?page=masthead';

test.describe( 'share panel', () => {
	test.beforeAll( () => {
		seedFixture();
		wpCli( [ 'option', 'update', 'start_of_week', '0' ] );
	} );

	test.beforeEach( async ( { page } ) => {
		await page.goto( CALENDAR );
		await page.waitForSelector( '.masthead-grid [data-date]' );
		await page.locator( '[data-post-id="12155"]' ).click();
		await page.waitForSelector( '.masthead-share' );
	} );

	test( 'selecting the published post 12155 shows that sharing is unavailable with a reason', async ( {
		page,
	} ) => {
		const panel = page.locator( '.masthead-share' );
		await expect(
			panel.getByText( /Sharing is unavailable: .+/ )
		).toBeVisible();
		await expect( panel.getByText( 'Published Nov 9' ) ).toBeVisible();
		await expect( panel.locator( 'textarea, input, button' ) ).toHaveCount(
			0
		);
	} );

	test( 'the share panel has no serious or critical axe violations', async ( {
		page,
	} ) => {
		await expectNoSeriousViolations( page, '.masthead-share' );
	} );
} );
