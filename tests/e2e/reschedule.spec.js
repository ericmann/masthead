import { test, expect } from '@playwright/test';

import { seedFixture, wpCli } from './helpers/wp.mjs';

const CALENDAR = '/wp-admin/edit.php?page=masthead';

test.describe( 'rescheduling', () => {
	test.beforeAll( () => {
		seedFixture();
		wpCli( [ 'option', 'update', 'start_of_week', '0' ] );
	} );

	test.beforeEach( async ( { page } ) => {
		await page.goto( CALENDAR );
		await page.waitForSelector( '.masthead-grid [data-date]' );
	} );

	test( 'dragging post 12177 to Nov 10 calls reschedule and persists after reload', async ( {
		page,
	}, testInfo ) => {
		test.skip(
			testInfo.project.name !== 'desktop',
			'drag is desktop only'
		);
		const request = page.waitForResponse(
			( response ) =>
				response.url().includes( 'masthead%2Freschedule' ) ||
				response.url().includes( 'masthead/reschedule' )
		);
		await page
			.locator( '[data-post-id="12177"]' )
			.dragTo( page.locator( '[data-date="2026-11-10"]' ) );
		expect( ( await request ).status() ).toBe( 200 );
		await page.reload();
		await page.waitForSelector( '.masthead-grid [data-date]' );
		await expect(
			page.locator( '[data-date="2026-11-10"] [data-post-id="12177"]' )
		).toHaveCount( 1 );
		await expect(
			page.locator( '[data-date="2026-11-11"] [data-post-id="12177"]' )
		).toHaveCount( 0 );
	} );

	test( 'Move to… moves post 10194 to Nov 13 from the keyboard', async ( {
		page,
	} ) => {
		const opener = page.getByRole( 'button', {
			name: /Move “What the Clusters Show: A Map of Reading Habits” to another day/,
		} );
		await opener.focus();
		await page.keyboard.press( 'Enter' );
		const input = page.getByLabel( /New date for “What the Clusters/ );
		await input.fill( '2026-11-13' );
		await input.press( 'Enter' );
		await expect(
			page.locator( '[data-date="2026-11-13"] [data-post-id="10194"]' )
		).toHaveCount( 1 );
		await expect(
			page.locator( '[data-date="2026-11-12"] [data-post-id="10194"]' )
		).toHaveCount( 0 );
	} );

	test( 'a published chip cannot be dragged', async ( { page } ) => {
		const chip = page
			.locator( '[data-date="2026-11-09"] [data-status="publish"]' )
			.first();
		await expect( chip ).toHaveAttribute( 'draggable', 'false' );
		await expect(
			page
				.locator( '[data-date="2026-11-09"]' )
				.getByRole( 'button', { name: /to another day/ } )
		).toHaveCount( 0 );
	} );
} );
