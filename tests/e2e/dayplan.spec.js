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

test.describe( 'Day plan with an AI outline', () => {
	test.beforeAll( () => {
		seedFixture();
		wpCli( [ 'option', 'update', 'start_of_week', '0' ] );
		wpCli( [ 'option', 'delete', 'masthead_ai_usage' ] );
	} );

	test( 'planning the fixture idea with a drafted outline creates a draft whose content has the outline list', async ( {
		page,
	} ) => {
		await page.goto( CALENDAR );
		await page.waitForSelector( '.masthead-grid [data-date]' );
		await page
			.locator( '[data-date="2026-11-18"] .masthead-day__button' )
			.click();
		await page.waitForSelector( '.masthead-suggestion' );
		await page.getByRole( 'button', { name: 'Suggest ideas' } ).click();
		await page
			.locator( '.masthead-suggestion[data-kind="idea"]' )
			.first()
			.getByRole( 'button', { name: 'Plan this' } )
			.click();
		await page.waitForSelector( '.masthead-dayplan' );

		await page.getByRole( 'button', { name: 'Draft an outline' } ).click();
		await expect( page.getByLabel( 'Outline line 1' ) ).toHaveValue(
			'Why the problem is worth solving now'
		);
		await expect( page.getByLabel( 'Outline line 4' ) ).toBeVisible();
		await expectNoSeriousViolations( page, '.masthead-dayplan' );

		await page.getByRole( 'button', { name: 'Remove line 4' } ).click();
		await page
			.getByRole( 'button', { name: 'Create draft with outline' } )
			.click();
		await expect(
			page.locator( '[data-date="2026-11-18"] [data-status="draft"]' )
		).toHaveCount( 1 );

		const id = wpCli( [
			'post',
			'list',
			'--post_status=draft',
			'--s=What a quiet inbox taught me',
			'--field=ID',
		] );
		const content = wpCli( [ 'post', 'get', id, '--field=post_content' ] );
		expect( content ).toContain( 'Why the problem is worth solving now' );
		expect( content ).toContain( 'The approach, step by step' );
		expect( content ).toContain( 'What went wrong along the way' );
		expect( content ).not.toContain( 'What to try next' );
		expect( content ).toContain( '<li>' );
	} );
} );
