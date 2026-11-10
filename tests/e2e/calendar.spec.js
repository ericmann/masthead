import { test, expect } from '@playwright/test';

import { expectNoSeriousViolations } from './helpers/axe.mjs';
import { seedFixture, wpCli } from './helpers/wp.mjs';

const CALENDAR = '/wp-admin/edit.php?page=masthead';

// The fixture assumes weeks start on Sunday; wp-env defaults to Monday.
function seedCalendar() {
	seedFixture();
	wpCli( [ 'option', 'update', 'start_of_week', '0' ] );
}

test.describe( 'calendar month view', () => {
	test.beforeAll( () => {
		seedCalendar();
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

test.describe( 'calendar week and list views', () => {
	test.beforeAll( () => {
		seedCalendar();
	} );

	test( 'week view shows Nov 8 to Nov 14 with the scheduled posts', async ( {
		page,
	} ) => {
		await page.goto( `${ CALENDAR }#week` );
		await page.waitForSelector( '.masthead-week [data-date]' );
		const dates = await page
			.locator( '.masthead-week [data-date]' )
			.evaluateAll( ( cells ) =>
				cells.map( ( cell ) => cell.dataset.date )
			);
		expect( dates ).toEqual( [
			'2026-11-08',
			'2026-11-09',
			'2026-11-10',
			'2026-11-11',
			'2026-11-12',
			'2026-11-13',
			'2026-11-14',
		] );
		await expect(
			page.locator( '[data-date="2026-11-11"] [data-status="future"]' )
		).toHaveCount( 1 );
		await expect(
			page.locator( '[data-date="2026-11-12"] [data-status="future"]' )
		).toHaveCount( 1 );
	} );

	test( 'list view lists the five scheduled posts including The Mantle parts 2 to 4', async ( {
		page,
	} ) => {
		await page.goto( `${ CALENDAR }#list` );
		await page.waitForSelector( '.masthead-list__row' );
		await expect(
			page.getByRole( 'heading', { name: 'Scheduled (5)' } )
		).toBeVisible();
		for ( const id of [ 12156, 12157, 12158 ] ) {
			await expect(
				page.locator( `.masthead-list__row[data-post-id="${ id }"]` )
			).toHaveCount( 1 );
		}
	} );
} );
