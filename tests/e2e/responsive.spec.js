import { test, expect } from '@playwright/test';

import { expectNoSeriousViolations } from './helpers/axe.mjs';
import { seedFixture, wpCli } from './helpers/wp.mjs';

const CALENDAR = '/wp-admin/edit.php?page=masthead';
const ROOT = '#masthead-calendar-root';

test.describe( 'responsive layout and accessibility', () => {
	test.beforeAll( () => {
		seedFixture();
		wpCli( [ 'option', 'update', 'start_of_week', '0' ] );
	} );

	test( 'month view has no horizontal page overflow', async ( { page } ) => {
		await page.goto( CALENDAR );
		await page.waitForSelector( '.masthead-grid [data-date]' );
		const overflow = await page.evaluate(
			() =>
				document.documentElement.scrollWidth -
				document.documentElement.clientWidth
		);
		expect( overflow ).toBeLessThanOrEqual( 0 );
	} );

	test( 'week view has no serious or critical axe violations', async ( {
		page,
	} ) => {
		await page.goto( `${ CALENDAR }#week` );
		await page.waitForSelector( '.masthead-week [data-date]' );
		await expectNoSeriousViolations( page, ROOT );
	} );

	test( 'list view has no serious or critical axe violations', async ( {
		page,
	} ) => {
		await page.goto( `${ CALENDAR }#list` );
		await page.waitForSelector( '.masthead-list__row' );
		await expectNoSeriousViolations( page, ROOT );
	} );

	test( 'the Draft tray has no serious or critical axe violations', async ( {
		page,
	} ) => {
		await page.goto( CALENDAR );
		await page.waitForSelector( '.masthead-tray [data-post-id]' );
		await expectNoSeriousViolations( page, '.masthead-tray' );
	} );

	test( 'month view shows one day per row', async ( { page }, testInfo ) => {
		test.skip( testInfo.project.name !== 'mobile', 'mobile only' );
		await page.goto( CALENDAR );
		await page.waitForSelector( '.masthead-grid [data-date]' );
		const boxes = await page
			.locator( '.masthead-day:visible' )
			.evaluateAll( ( cells ) =>
				cells.slice( 0, 3 ).map( ( cell ) => {
					const box = cell.getBoundingClientRect();
					return {
						left: Math.round( box.left ),
						top: Math.round( box.top ),
					};
				} )
			);
		expect( boxes ).toHaveLength( 3 );
		expect( new Set( boxes.map( ( box ) => box.left ) ).size ).toBe( 1 );
		expect( boxes[ 1 ].top ).toBeGreaterThan( boxes[ 0 ].top );
		expect( boxes[ 2 ].top ).toBeGreaterThan( boxes[ 1 ].top );
		await expect(
			page.locator( '.masthead-day.is-outside' ).first()
		).toBeHidden();
		await expect(
			page.locator( '[data-date="2026-11-09"] .masthead-day__meta' )
		).toHaveText( 'Mon · Business & leadership' );
	} );
} );
