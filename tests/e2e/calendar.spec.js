import { test, expect } from '@playwright/test';

import { expectNoSeriousViolations } from './helpers/axe.mjs';
import { seedFixture } from './helpers/wp.mjs';

const CALENDAR = '/wp-admin/edit.php?page=masthead';

test.describe( 'calendar month view', () => {
	test.beforeAll( () => {
		seedFixture();
	} );

	test.beforeEach( async ( { page } ) => {
		await page.goto( CALENDAR );
		await page.waitForSelector( '.masthead-grid [data-date]' );
	} );

	test( 'November 2026 shows two published chips on Nov 9', async ( {
		page,
	} ) => {
		await expect(
			page.getByRole( 'region', { name: 'November 2026' } )
		).toBeVisible();
		await expect(
			page.locator( '[data-date="2026-11-09"] [data-status="publish"]' )
		).toHaveCount( 2 );
	} );

	test( 'Nov 11 and Nov 12 each show one scheduled chip', async ( {
		page,
	} ) => {
		for ( const date of [ '2026-11-11', '2026-11-12' ] ) {
			await expect(
				page.locator( `[data-date="${ date }"] [data-status="future"]` )
			).toHaveCount( 1 );
		}
	} );

	test( 'Nov 9 is marked as today', async ( { page } ) => {
		const cell = page.locator( '[data-date="2026-11-09"]' );
		await expect( cell ).toHaveClass( /is-today/ );
		await expect(
			cell.getByText( 'TODAY', { exact: true } )
		).toBeVisible();
	} );

	test( 'view buttons change the URL hash', async ( { page } ) => {
		await page.getByRole( 'button', { name: 'Week', exact: true } ).click();
		await expect( page ).toHaveURL( /#week$/ );
		await page.getByRole( 'button', { name: 'List', exact: true } ).click();
		await expect( page ).toHaveURL( /#list$/ );
		await page
			.getByRole( 'button', { name: 'Month', exact: true } )
			.click();
		await expect( page ).toHaveURL( /#month$/ );
	} );

	test( 'month view has no serious or critical axe violations', async ( {
		page,
	} ) => {
		await expectNoSeriousViolations( page, '#masthead-calendar-root' );
	} );
} );
