import { test, expect } from '@playwright/test';

import { expectNoSeriousViolations } from './helpers/axe.mjs';
import { seedFixture, wpCli } from './helpers/wp.mjs';

const CALENDAR = '/wp-admin/edit.php?page=masthead';

test.describe( 'AI ideas', () => {
	test.beforeAll( () => {
		seedFixture();
		wpCli( [ 'option', 'update', 'start_of_week', '0' ] );
		wpCli( [ 'option', 'delete', 'masthead_ai_usage' ] );
	} );

	test( 'Suggest ideas on Nov 18 adds the three fixture ideas after the draft and gap suggestions', async ( {
		page,
	} ) => {
		await page.goto( CALENDAR );
		await page.waitForSelector( '.masthead-grid [data-date]' );
		await page
			.locator( '[data-date="2026-11-18"] .masthead-day__button' )
			.click();
		await page.waitForSelector( '.masthead-suggestion' );
		await expect(
			page.locator( '.masthead-suggestion[data-kind="idea"]' )
		).toHaveCount( 0 );

		await page.getByRole( 'button', { name: 'Suggest ideas' } ).click();
		const ideas = page.locator( '.masthead-suggestion[data-kind="idea"]' );
		await expect( ideas ).toHaveCount( 3 );

		const kinds = await page
			.locator( '.masthead-suggestion' )
			.evaluateAll( ( cards ) =>
				cards.map( ( card ) => card.getAttribute( 'data-kind' ) )
			);
		expect( kinds.slice( -3 ) ).toEqual( [ 'idea', 'idea', 'idea' ] );
		expect( kinds[ 0 ] ).toBe( 'draft' );
		expect( kinds ).toContain( 'gap' );
		await expect( ideas.first() ).toContainText(
			'What a quiet inbox taught me about notifications'
		);
		await expect(
			page.getByRole( 'button', { name: 'Suggest ideas' } )
		).toHaveCount( 0 );
		await expectNoSeriousViolations( page, '.masthead-desk' );
	} );
} );
