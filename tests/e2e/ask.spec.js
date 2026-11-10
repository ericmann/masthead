import { test, expect } from '@playwright/test';

import { expectNoSeriousViolations } from './helpers/axe.mjs';
import { seedFixture, wpCli } from './helpers/wp.mjs';

const CALENDAR = '/wp-admin/edit.php?page=masthead';

test.describe( 'Ask the Desk', () => {
	test.beforeAll( () => {
		seedFixture();
		wpCli( [ 'option', 'update', 'start_of_week', '0' ] );
		wpCli( [ 'option', 'delete', 'masthead_ai_usage' ] );
	} );

	test.beforeEach( async ( { page } ) => {
		await page.goto( CALENDAR );
		await page.waitForSelector( '.masthead-grid [data-date]' );
	} );

	test( 'Ask the Desk has no serious or critical axe violations', async ( {
		page,
	} ) => {
		await page.getByLabel( 'Ask the Desk' ).fill( 'a security series' );
		await page.getByRole( 'button', { name: 'Ask', exact: true } ).click();
		await page.getByLabel( 'Series name' ).waitFor();
		await expectNoSeriousViolations( page, '.masthead-ask' );
	} );

	test( 'accepting the fixture series creates four dated drafts on Thursdays', async ( {
		page,
	} ) => {
		await page
			.getByLabel( 'Ask the Desk' )
			.fill( 'a 4-part security series starting on a Thursday' );
		await page.getByRole( 'button', { name: 'Ask', exact: true } ).click();
		await expect( page.getByLabel( 'Series name' ) ).toHaveValue(
			'Locking things down'
		);
		await expect( page.getByLabel( 'Part 4 date' ) ).toBeVisible();
		await page.getByRole( 'button', { name: 'Accept series' } ).click();
		await expect( page.locator( '.masthead-ask__created' ) ).toHaveText(
			'Created 4 planned drafts'
		);

		await page.getByRole( 'button', { name: 'List', exact: true } ).click();
		const titles = [
			'Start with the threat you actually face',
			'Passwords without the pain',
			'Updates you can trust',
			'Backups that survive a bad day',
		];
		for ( const title of titles ) {
			const row = page
				.locator( '.masthead-list__row' )
				.filter( { hasText: title } );
			await expect( row ).toHaveCount( 1 );
			await expect( row.locator( '.masthead-list__when' ) ).toContainText(
				/^Thu /
			);
			await expect( row ).toHaveAttribute( 'data-status', 'draft' );
		}
	} );
} );
