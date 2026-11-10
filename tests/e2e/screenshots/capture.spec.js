import { test, expect } from '@playwright/test';
import path from 'node:path';

import { seedFixture, wpCli } from '../helpers/wp.mjs';

// npm runs scripts from the repository root.
const OUT = path.join( process.cwd(), 'docs', 'screenshots' );
const CALENDAR = '/wp-admin/edit.php?page=masthead';
const SETTINGS = '/wp-admin/options-general.php?page=masthead-settings';
const NOW = '2026-11-09 14:00:00';

// Screenshots are rewritten only on request, so the normal e2e run never touches the images.
test.skip(
	process.env.MASTHEAD_SCREENSHOTS !== '1',
	'Set MASTHEAD_SCREENSHOTS=1 (npm run screenshots) to regenerate the README images.'
);

test.use( { viewport: { width: 1440, height: 1000 } } );

test.describe( 'README screenshots', () => {
	test.beforeAll( () => {
		seedFixture();
		wpCli( [ 'option', 'update', 'start_of_week', '0' ] );
		wpCli( [ 'option', 'delete', 'masthead_ai_usage' ] );
		wpCli( [ 'masthead', 'swap-check', '--write', `--now=${ NOW }` ] );
	} );

	test( 'captures the four README screenshots', async ( { page } ) => {
		// Month view with Nov 18 selected.
		await page.goto( CALENDAR );
		await page.waitForSelector( '.masthead-grid [data-date]' );
		await page
			.locator( '[data-date="2026-11-18"] .masthead-day__button' )
			.click();
		await page.waitForSelector( '.masthead-suggestion' );
		await page.waitForSelector( '.masthead-ghost' );
		const calendar = page.locator( '#masthead-calendar-root' );
		await calendar.screenshot( {
			path: path.join( OUT, 'calendar-month.png' ),
		} );

		// Day plan for Nov 18.
		await page
			.locator( '.masthead-suggestion[data-kind="gap"]' )
			.first()
			.getByRole( 'button', { name: 'Plan this' } )
			.click();
		await page.waitForSelector( '.masthead-dayplan' );
		await page
			.getByLabel( 'Title' )
			.fill( 'Why small teams still write things down' );
		await calendar.screenshot( {
			path: path.join( OUT, 'day-plan.png' ),
		} );

		// Swap panel for Part 2 of the series.
		await page.goto( `${ CALENDAR }#list` );
		await page.reload();
		await page.waitForSelector( '.masthead-list__row' );
		await page
			.locator( '.masthead-list__row[data-post-id="12156"]' )
			.click();
		await page.waitForSelector( '.masthead-swap' );
		await calendar.screenshot( {
			path: path.join( OUT, 'swap-panel.png' ),
		} );

		// Settings.
		await page.goto( SETTINGS );
		await page.waitForSelector( '.masthead-rhythm tbody tr' );
		await expect( page.getByText( 'Provider: available' ) ).toBeVisible();
		await page.locator( '#masthead-settings-root' ).screenshot( {
			path: path.join( OUT, 'settings.png' ),
		} );
	} );
} );
