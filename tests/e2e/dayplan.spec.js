import { test, expect } from '@playwright/test';

import { expectNoSeriousViolations } from './helpers/axe.mjs';
import { seedFixture, wpCli } from './helpers/wp.mjs';

const CALENDAR = '/wp-admin/edit.php?page=masthead';

test.describe( 'Day plan', () => {
	test.beforeAll( () => {
		seedFixture();
		wpCli( [ 'option', 'update', 'start_of_week', '0' ] );
	} );

	test.beforeEach( async ( { page } ) => {
		await page.goto( CALENDAR );
		await page.waitForSelector( '.masthead-grid [data-date]' );
		await page
			.locator( '[data-date="2026-11-18"] .masthead-day__button' )
			.click();
		await page
			.locator( '.masthead-suggestion[data-kind="gap"]' )
			.first()
			.getByRole( 'button', { name: 'Plan this' } )
			.click();
		await page.waitForSelector( '.masthead-dayplan' );
	} );

	test( 'the Day plan has no serious or critical axe violations', async ( {
		page,
	} ) => {
		await expectNoSeriousViolations( page, '.masthead-dayplan' );
	} );

	test( 'planning a gap suggestion on Nov 18 creates a dated draft chip on that day', async ( {
		page,
	} ) => {
		await expect( page.getByLabel( 'Time' ) ).toHaveValue( '08:00' );
		await page.getByLabel( 'Title' ).fill( 'Why leaders still read' );
		await page.getByRole( 'button', { name: 'Create draft' } ).click();
		const chip = page.locator(
			'[data-date="2026-11-18"] [data-status="draft"]'
		);
		await expect( chip ).toHaveCount( 1 );
		await expect( chip ).toContainText( 'Why leaders still read' );
		await expect( chip ).toContainText( 'Draft · 8:00 am' );
		await expect( page.locator( '.masthead-dayplan' ) ).toHaveCount( 0 );
	} );
} );
