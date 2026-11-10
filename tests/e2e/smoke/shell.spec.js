import { test, expect } from '@playwright/test';
import { expectNoSeriousViolations } from '../helpers/axe.mjs';
import { wpCli } from '../helpers/wp.mjs';

const PAGE = '/wp-admin/edit.php?page=masthead';

test.describe( 'app shell', () => {
	test( 'calendar page renders the app shell', async ( { page } ) => {
		await page.goto( PAGE );
		await expect(
			page.locator( '#masthead-calendar-root .masthead-calendar h1' )
		).toHaveText( 'Calendar' );
	} );

	test( 'calendar page has no serious or critical axe violations', async ( {
		page,
	} ) => {
		await page.goto( PAGE );
		await page.waitForSelector(
			'#masthead-calendar-root .masthead-calendar'
		);
		await expectNoSeriousViolations( page, '#masthead-calendar-root' );
	} );

	test( 'e2e mu-plugins are loaded', async () => {
		wpCli( [
			'option',
			'update',
			'masthead_seed_now',
			'2026-11-09 14:00:00',
		] );
		try {
			const out = wpCli( [
				'eval',
				'echo apply_filters( "masthead_now", "unpinned" );',
			] );
			expect( out ).toContain( '2026-11-09 14:00:00' );
		} finally {
			wpCli( [ 'option', 'delete', 'masthead_seed_now' ] );
		}
	} );
} );
