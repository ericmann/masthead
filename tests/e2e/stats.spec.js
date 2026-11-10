import { test, expect } from '@playwright/test';

import { seedFixture, setFixtureStats } from './helpers/wp.mjs';

const CALENDAR = '/wp-admin/edit.php?page=masthead';

async function statsSummary( page, request ) {
	await page.goto( CALENDAR );
	const nonce = await (
		await request.get( '/wp-admin/admin-ajax.php?action=rest-nonce' )
	).text();
	const response = await request.get(
		'/wp-json/wp-abilities/v1/abilities/masthead/stats-summary/run',
		{ headers: { 'X-WP-Nonce': nonce } }
	);
	expect( response.ok() ).toBe( true );
	return response.json();
}

test.describe( 'stats summary', () => {
	test.beforeAll( () => {
		seedFixture();
	} );

	test.afterAll( () => {
		setFixtureStats( false );
	} );

	test( 'stats-summary reports unavailable by default', async ( {
		page,
		request,
	} ) => {
		setFixtureStats( false );
		const summary = await statsSummary( page, request );
		expect( summary.available ).toBe( false );
		expect( summary.reason ).toBeTruthy();
		expect( summary.by_category ).toBeUndefined();
	} );

	test( 'stats-summary reports Jetpack Stats as the source when fixture stats are on', async ( {
		page,
		request,
	} ) => {
		setFixtureStats( true );
		const summary = await statsSummary( page, request );
		expect( summary.available ).toBe( true );
		expect( summary.source ).toBe( 'Jetpack Stats' );
		expect( summary.top_posts[ 0 ].post_id ).toBe( 12155 );
		expect( summary.fetched_at ).toBe( '2026-11-09T13:00:00-08:00' );
	} );
} );
