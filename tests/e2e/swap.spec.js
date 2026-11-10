import { test, expect } from '@playwright/test';

import { expectNoSeriousViolations } from './helpers/axe.mjs';
import { seedFixture, setFixtureStats, wpCli } from './helpers/wp.mjs';

const LIST = '/wp-admin/edit.php?page=masthead#list';
const NOW = '2026-11-09 14:00:00';

function seedAndCheck() {
	seedFixture();
	wpCli( [ 'option', 'update', 'start_of_week', '0' ] );
	wpCli( [ 'masthead', 'swap-check', '--write', `--now=${ NOW }` ] );
}

async function openList( page ) {
	await page.goto( LIST );
	await page.reload();
	await page.waitForSelector( '.masthead-list__row' );
}

async function selectRow( page, id ) {
	await page.locator( `.masthead-list__row[data-post-id="${ id }"]` ).click();
	await page.waitForSelector( '.masthead-swap' );
}

test.describe( 'swap panel', () => {
	test.beforeAll( () => {
		seedAndCheck();
	} );

	test( 'list view marks The Mantle parts 2 to 4 Review and posts 12177 and 10194 Fits', async ( {
		page,
	} ) => {
		await openList( page );
		for ( const id of [ 12156, 12157, 12158 ] ) {
			await expect(
				page.locator(
					`.masthead-list__row[data-post-id="${ id }"] .masthead-list__verdict`
				)
			).toHaveText( 'Review' );
		}
		for ( const id of [ 12177, 10194 ] ) {
			await expect(
				page.locator(
					`.masthead-list__row[data-post-id="${ id }"] .masthead-list__verdict`
				)
			).toHaveText( 'Fits' );
		}
		await expect( page.getByText( 'Swap check ran Nov 9' ) ).toBeVisible();
	} );

	test( 'The Mantle Part 2 shows a lane mismatch with the option Move to Saturdays: 2026-12-12, 2027-01-09, 2027-02-06 and a cadence break', async ( {
		page,
	} ) => {
		await openList( page );
		await selectRow( page, 12156 );
		const panel = page.locator( '.masthead-swap' );
		await expect(
			panel.locator( '[data-type="lane_mismatch"]' )
		).toHaveCount( 1 );
		await expect(
			panel.locator( '[data-type="cadence_break"]' )
		).toHaveCount( 1 );
		await expect(
			panel.getByRole( 'button', {
				name: 'Move to Saturdays: 2026-12-12, 2027-01-09, 2027-02-06',
			} )
		).toBeVisible();
		await expect( panel.getByText( 'Reschedules 3 posts' ) ).toBeVisible();
		await expect(
			panel.getByRole( 'button', { name: 'Move to 2026-12-07' } )
		).toBeVisible();
	} );

	test( 'the swap panel has no serious or critical axe violations', async ( {
		page,
	} ) => {
		await openList( page );
		await selectRow( page, 12156 );
		await expectNoSeriousViolations( page, '.masthead-swap' );
	} );

	test( 'applying Move to Saturdays moves the three parts', async ( {
		page,
	} ) => {
		await openList( page );
		await selectRow( page, 12156 );
		await page
			.locator( '.masthead-swap' )
			.getByRole( 'button', { name: /^Move to Saturdays/ } )
			.click();
		await expect(
			page.locator( '.masthead-swap' ).getByText( 'Done.' )
		).toBeVisible();
		expect(
			wpCli( [ 'post', 'get', '12156', '--field=post_date' ] ).slice(
				0,
				10
			)
		).toBe( '2026-12-12' );
		expect(
			wpCli( [ 'post', 'get', '12157', '--field=post_date' ] ).slice(
				0,
				10
			)
		).toBe( '2027-01-09' );
		expect(
			wpCli( [ 'post', 'get', '12158', '--field=post_date' ] ).slice(
				0,
				10
			)
		).toBe( '2027-02-06' );
		expect(
			wpCli( [ 'post', 'get', '12157', '--field=post_status' ] )
		).toBe( 'future' );
	} );
} );

test.describe( 'swap panel: lane exception', () => {
	test.beforeAll( () => {
		seedAndCheck();
	} );

	test( 'marking the lane exception and re-running the check leaves only the cadence break', async ( {
		page,
	} ) => {
		await openList( page );
		await selectRow( page, 12156 );
		await page
			.locator( '.masthead-swap' )
			.getByRole( 'button', {
				name: 'Keep Mondays and mark this series as a lane exception',
			} )
			.click();
		await expect(
			page.locator( '.masthead-swap' ).getByText( 'Done.' )
		).toBeVisible();
		await expect(
			page.locator( '.masthead-swap [data-type="lane_mismatch"]' )
		).toHaveCount( 0 );
		await expect(
			page.locator( '.masthead-swap [data-type="cadence_break"]' )
		).toHaveCount( 1 );

		wpCli( [ 'masthead', 'swap-check', '--write', `--now=${ NOW }` ] );
		await openList( page );
		await expect(
			page.locator(
				'.masthead-list__row[data-post-id="12156"] .masthead-list__verdict'
			)
		).toHaveText( 'Review' );
		for ( const id of [ 12157, 12158 ] ) {
			await expect(
				page.locator(
					`.masthead-list__row[data-post-id="${ id }"] .masthead-list__verdict`
				)
			).toHaveText( 'Fits' );
		}
	} );
} );

test.describe( 'swap panel: numbers', () => {
	test.beforeAll( () => {
		seedFixture();
		wpCli( [ 'option', 'update', 'start_of_week', '0' ] );
	} );

	test.afterAll( () => {
		setFixtureStats( false );
	} );

	test( 'with fixture stats on, every number in the swap panel has Jetpack Stats beside it', async ( {
		page,
	} ) => {
		setFixtureStats( true );
		const plan = ( date, title, category ) =>
			wpCli( [
				'eval',
				`wp_set_current_user( 1 ); $r = wp_get_ability( 'masthead/create-planned-draft' )->execute( array( 'date' => '${ date }', 'time' => '09:00', 'title' => '${ title }', 'category' => '${ category }' ) ); echo is_wp_error( $r ) ? 'error' : $r['post_id'];`,
			] );
		const business = plan(
			'2026-11-17',
			'Business draft on a technology day',
			'business'
		);
		plan(
			'2026-11-16',
			'Technology draft on a business day',
			'technology'
		);
		wpCli( [ 'masthead', 'swap-check', '--write', `--now=${ NOW }` ] );

		await openList( page );
		await selectRow( page, business );
		const panel = page.locator( '.masthead-swap' );
		await expect(
			panel.locator( '[data-type="better_candidate"]' )
		).toHaveCount( 1 );
		const stats = panel.locator( '.masthead-stat' );
		await expect( stats ).toHaveCount( 3 );
		for ( const stat of await stats.all() ) {
			await expect( stat ).toContainText( 'Jetpack Stats' );
		}
	} );
} );
