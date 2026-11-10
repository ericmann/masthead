import { test, expect } from '@playwright/test';

import { seedFixture, wpCli } from './helpers/wp.mjs';

const CALENDAR = '/wp-admin/edit.php?page=masthead';

test.describe( 'Desk ghost cards', () => {
	test.beforeAll( () => {
		seedFixture();
		wpCli( [ 'option', 'update', 'start_of_week', '0' ] );
	} );

	test.beforeEach( async ( { page } ) => {
		await page.goto( CALENDAR );
		await page.waitForSelector( '.masthead-grid [data-date]' );
	} );

	test( 'Nov 18 shows a DRAFT ghost card for post 10178', async ( {
		page,
	} ) => {
		const ghost = page.locator(
			'[data-date="2026-11-18"] .masthead-ghost'
		);
		await expect( ghost ).toHaveCount( 1 );
		await expect( ghost ).toHaveAttribute( 'data-kind', 'draft' );
		await expect(
			ghost.getByText( 'DRAFT', { exact: true } )
		).toBeVisible();
		await expect( ghost ).toContainText( 'From Craftsmen to Swarms' );
	} );

	test( 'a day beyond the ghost window shows Open with its lane', async ( {
		page,
	} ) => {
		const cell = page.locator( '[data-date="2026-11-30"]' );
		await expect(
			cell.getByText( 'Open · Business & leadership' )
		).toBeVisible();
		await expect( cell.locator( '.masthead-ghost' ) ).toHaveCount( 0 );
	} );

	test( 'Fill open days adds ghost cards to open days in the visible range', async ( {
		page,
	} ) => {
		const cell = page.locator( '[data-date="2026-11-30"]' );
		await expect( cell.locator( '.masthead-ghost' ) ).toHaveCount( 0 );
		await page.getByRole( 'button', { name: 'Fill open days' } ).click();
		await expect( cell.locator( '.masthead-ghost' ) ).toHaveCount( 1, {
			timeout: 30000,
		} );
		await expect( cell.locator( '.masthead-ghost' ) ).toHaveAttribute(
			'data-kind',
			'gap'
		);
		// Beyond today plus the horizon stays open.
		await expect(
			page
				.locator( '[data-date="2026-12-01"]' )
				.locator( '.masthead-ghost' )
		).toHaveCount( 0 );
	} );
} );
