import { test, expect } from '@playwright/test';

import { expectNoSeriousViolations } from './helpers/axe.mjs';
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

test.describe( 'Desk rail', () => {
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
		await page.waitForSelector( '.masthead-suggestion' );
	} );

	test( "selecting Nov 18 lists draft 10178 first with the reason Wednesday is your Standalone slot. This draft isn't on the calendar; last edited Oct 29.", async ( {
		page,
	} ) => {
		const first = page.locator( '.masthead-suggestion' ).first();
		await expect( first ).toHaveAttribute( 'data-key', 'draft:10178' );
		await expect(
			first.getByText(
				"Wednesday is your Standalone slot. This draft isn't on the calendar; last edited Oct 29."
			)
		).toBeVisible();
		await expect(
			page.getByRole( 'heading', {
				name: 'Wednesday, Nov 18 · Standalone lane',
			} )
		).toBeVisible();
	} );

	test( 'Nov 18 also lists a gap suggestion and no reshare suggestion', async ( {
		page,
	} ) => {
		await expect(
			page.locator( '.masthead-suggestion[data-kind="gap"]' ).first()
		).toBeVisible();
		await expect(
			page.locator( '.masthead-suggestion[data-kind="reshare"]' )
		).toHaveCount( 0 );
	} );

	test( 'the Desk rail has no serious or critical axe violations', async ( {
		page,
	} ) => {
		await expectNoSeriousViolations( page, '.masthead-desk' );
	} );

	test( 'dismissing the draft suggestion persists across a reload', async ( {
		page,
	} ) => {
		const draft = page.locator(
			'.masthead-suggestion[data-key="draft:10178"]'
		);
		await draft.getByRole( 'button', { name: 'Dismiss' } ).click();
		await expect( draft ).toHaveCount( 0 );
		await page.reload();
		await page.waitForSelector( '.masthead-grid [data-date]' );
		await page
			.locator( '[data-date="2026-11-18"] .masthead-day__button' )
			.click();
		await page.waitForSelector( '.masthead-suggestion' );
		await expect(
			page.locator( '.masthead-suggestion[data-key="draft:10178"]' )
		).toHaveCount( 0 );
	} );
} );
