import { test, expect } from '@playwright/test';

const CALENDAR = '/wp-admin/edit.php?page=masthead';

test.describe( 'seeded fixture', () => {
	test( 'seeded post 12177 is scheduled for 2026-11-11', async ( {
		page,
		request,
	} ) => {
		await page.goto( CALENDAR );
		const nonce = await (
			await request.get( '/wp-admin/admin-ajax.php?action=rest-nonce' )
		).text();
		const response = await request.get(
			'/wp-json/wp/v2/posts/12177?context=edit',
			{ headers: { 'X-WP-Nonce': nonce } }
		);
		expect( response.ok() ).toBe( true );
		const post = await response.json();
		expect( post.status ).toBe( 'future' );
		expect( post.date ).toBe( '2026-11-11T08:00:00' );
	} );

	test( 'the pinned clock is 2026-11-09', async ( { page } ) => {
		await page.goto( CALENDAR );
		const today = await page.evaluate( () => window.mastheadBoot.today );
		expect( today ).toBe( '2026-11-09' );
	} );
} );
