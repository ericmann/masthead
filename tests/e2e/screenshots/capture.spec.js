import { test, expect } from '@playwright/test';
import path from 'node:path';

import { restoreDefaults, seedDemo } from '../../../demo/site/seed.mjs';

// npm runs scripts from the repository root.
const OUT = path.join( process.cwd(), 'docs', 'screenshots' );
const CALENDAR = '/wp-admin/edit.php?page=masthead';
const SETTINGS = '/wp-admin/options-general.php?page=masthead-settings';

// Screenshots are rewritten only on request, so the normal e2e run never touches the images.
test.skip(
	process.env.MASTHEAD_SCREENSHOTS !== '1',
	'Set MASTHEAD_SCREENSHOTS=1 (npm run screenshots) to regenerate the README images.'
);

test.use( { viewport: { width: 1440, height: 1000 } } );

// Ghost cards load one day at a time in the background; wait until they are done
// so no screenshot catches "Fill open days" disabled mid-run.
async function waitForGhosts( page ) {
	await expect(
		page.locator( '.masthead-toolbar__fill-note' )
	).not.toHaveText( /^Suggesting/, { timeout: 60000 } );
}

// The images show the demo site (demo/site): Northfield Notes, with sample stats and fixed AI answers.
test.describe( 'README screenshots', () => {
	test.beforeAll( () => {
		// Seeds the posts, names the site and its editor, weeks start on Sunday, and stores this
		// morning's swap-check findings.
		seedDemo();
	} );

	test.afterAll( () => {
		restoreDefaults();
	} );

	test( 'captures the four README screenshots', async ( { page } ) => {
		// Month view with Thursday, Nov 19 selected: a draft, a series part and a quiet category.
		await page.goto( CALENDAR );
		await page.waitForSelector( '.masthead-grid [data-date]' );
		await page
			.locator( '[data-date="2026-11-19"] .masthead-day__button' )
			.click();
		await page.waitForSelector( '.masthead-suggestion' );
		await page.waitForSelector( '.masthead-ghost' );
		await waitForGhosts( page );
		await expect(
			page.getByRole( 'button', { name: 'Fill open days' } )
		).toBeEnabled();
		// The hero image: the whole 1440 by 1000 viewport, admin bar and menu included, like Main.dc.html.
		await page.screenshot( {
			path: path.join( OUT, 'calendar-month.png' ),
		} );
		const calendar = page.locator( '#masthead-calendar-root' );

		// Day plan for Nov 19, with an outline from the demo's AI answers.
		await page
			.locator( '.masthead-suggestion[data-kind="gap"]' )
			.first()
			.getByRole( 'button', { name: 'Plan this' } )
			.click();
		await page.waitForSelector( '.masthead-dayplan' );
		await page.getByLabel( 'Title' ).fill( 'A Family Security Checkup' );
		await page.getByRole( 'button', { name: 'Draft an outline' } ).click();
		await page.waitForSelector( '.masthead-dayplan__outline-editor' );
		await calendar.screenshot( {
			path: path.join( OUT, 'day-plan.png' ),
		} );

		// Swap panel for Part 2 of The Mantle: off its lane and off its cadence.
		await page.goto( `${ CALENDAR }#list` );
		await page.reload();
		await page.waitForSelector( '.masthead-list__row' );
		await waitForGhosts( page );
		await page
			.locator( '.masthead-list__row[data-post-id="3036"]' )
			.click();
		await page.waitForSelector( '.masthead-swap' );
		await calendar.screenshot( {
			path: path.join( OUT, 'swap-panel.png' ),
		} );

		// Settings.
		await page.goto( SETTINGS );
		await page.waitForSelector( '.masthead-rhythm tbody tr' );
		await expect( page.getByText( 'Provider: available' ) ).toBeVisible();
		// Series terms arrive from core-data after first paint and grow the page.
		await page.waitForSelector( '.masthead-series__item' );
		await page.locator( '#masthead-settings-root' ).screenshot( {
			path: path.join( OUT, 'settings.png' ),
		} );
	} );
} );
