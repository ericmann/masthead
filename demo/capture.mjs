// Stills for the demo video, shot in story order against the demo site (demo/site/seed.mjs).
//
//   node capture.mjs          reseed the demo site, then shoot every still
//
// wp-env must be running (npm run test:e2e or npx wp-env start, from the repository root).
// The browser is 1600×900 at deviceScaleFactor 2, so every still is 3200 pixels wide: build.mjs
// downscales each one to the 1920-wide frame and keeps the full-size file for zoomed shots.
// manifest.json records each still's size and the boxes (CSS pixels, page coordinates) of the
// things the composition points at, so the synthetic cursor and camera land on real targets.
// The last step saves real `wp masthead plan` and masthead/get-plan output for the MCP scene.
import { chromium } from 'playwright';
import { mkdir, rm, writeFile } from 'node:fs/promises';
import { seedDemo, wp, DEMO_NOW } from './site/seed.mjs';

const BASE = 'http://localhost:8888';
const CALENDAR = `${ BASE }/wp-admin/edit.php?page=masthead`;
const SETTINGS = `${ BASE }/wp-admin/options-general.php?page=masthead-settings`;
const OUT = new URL( './assets/shots/', import.meta.url ).pathname;
const VIEWPORT = { width: 1600, height: 900 };
const SCALE = 2;
// "Next week" for the MCP scene, Sunday to Saturday after the pinned Tuesday.
const NEXT_WEEK = [ '2026-11-15', '2026-11-21' ];

await rm( OUT, { recursive: true, force: true } );
await mkdir( OUT, { recursive: true } );
console.log( seedDemo().trim().split( '\n' ).slice( -1 )[ 0 ] );
// Every post on one page of Posts › All Posts, for the opening pan.
wp( [ [ 'user', 'meta', 'update', 'admin', 'edit_post_per_page', '50' ] ] );

const browser = await chromium.launch();
const context = await browser.newContext( { viewport: VIEWPORT, deviceScaleFactor: SCALE } );
const page = await context.newPage();
const manifest = { viewport: VIEWPORT, scale: SCALE, now: DEMO_NOW, shots: {} };

const round = ( n ) => Math.round( n * 10 ) / 10;

async function settle( ms = 500 ) {
	await page.waitForLoadState( 'networkidle' ).catch( () => {} );
	await page.waitForTimeout( ms );
}

/** Box of a locator in page coordinates (CSS pixels), or null when it is not rendered. */
async function boxOf( locator ) {
	const first = locator.first();
	if ( ( await first.count() ) === 0 ) {
		return null;
	}
	const box = await first.boundingBox();
	if ( ! box ) {
		return null;
	}
	const [ sx, sy ] = await page.evaluate( () => [ window.scrollX, window.scrollY ] );
	return [ box.x + sx, box.y + sy, box.width, box.height ].map( round );
}

/**
 * Saves one still and the boxes of its targets.
 *
 * @param {string}                 name    Still name.
 * @param {Object<string,Locator>} targets Things the composition may point at.
 * @param {Object}                 opts    full: whole page; keepMouse: leave the pointer where it is.
 */
async function shot( name, targets = {}, { full = false, keepMouse = false } = {} ) {
	if ( ! keepMouse ) {
		await page.mouse.move( VIEWPORT.width - 4, VIEWPORT.height - 4 );
	}
	await page.evaluate( () => window.scrollTo( 0, 0 ) );
	await page.waitForTimeout( 150 );
	const boxes = {};
	for ( const [ key, locator ] of Object.entries( targets ) ) {
		boxes[ key ] = await boxOf( locator );
		if ( ! boxes[ key ] ) {
			throw new Error( `${ name }: target ${ key } is not on the page` );
		}
	}
	const size = full
		? await page.evaluate( () => [ document.documentElement.scrollWidth, document.documentElement.scrollHeight ] )
		: [ VIEWPORT.width, VIEWPORT.height ];
	await page.screenshot( { path: `${ OUT }${ name }.png`, fullPage: full, animations: 'disabled' } );
	manifest.shots[ name ] = { full, size, targets: boxes };
	console.log( `${ name.padEnd( 18 ) } ${ size.join( '×' ) }  ${ Object.keys( boxes ).join( ' ' ) }` );
}

/** Types text in steps and saves a still after each one: name-1, name-2, … */
async function typeFrames( locator, text, name, steps, targets = {} ) {
	const words = text.split( ' ' );
	const per = Math.ceil( words.length / steps );
	let typed = '';
	for ( let i = 0; i < steps; i++ ) {
		const chunk = words.slice( i * per, ( i + 1 ) * per ).join( ' ' );
		if ( chunk === '' ) {
			break;
		}
		const piece = ( typed === '' ? '' : ' ' ) + chunk;
		await locator.pressSequentially( piece, { delay: 15 } );
		typed += piece;
		await shot( `${ name }-${ i + 1 }`, targets, { keepMouse: true } );
	}
}

const day = ( date ) => page.locator( `.masthead-grid [data-date="${ date }"]` );
const chip = ( id ) => page.locator( `.masthead-chip[data-post-id="${ id }"]` );
const button = ( name ) => page.getByRole( 'button', { name, exact: true } );

async function openCalendar( view = 'month' ) {
	await page.goto( `${ CALENDAR }#${ view }` );
	await page.reload();
	await page.waitForSelector( view === 'list' ? '.masthead-list__row' : '.masthead-grid [data-date]' );
	// Ghost cards load one day at a time; wait until the toolbar stops counting.
	await page.waitForFunction( () => ! /^Suggesting/.test( document.querySelector( '.masthead-toolbar__fill-note' )?.textContent || '' ), null, { timeout: 60000 } );
	await settle( 800 );
}

// Log in as the demo's editor (wp-env's admin, shown as Sam).
await page.goto( `${ BASE }/wp-login.php` );
await page.fill( '#user_login', 'admin' );
await page.fill( '#user_pass', 'password' );
await page.click( '#wp-submit' );
await page.waitForURL( /wp-admin/ );

// 2. The problem: an ordinary Posts list.
await page.goto( `${ BASE }/wp-admin/edit.php` );
await settle();
await shot(
	'posts',
	{
		after: page.locator( '#post-3031' ),
		before: page.locator( '#post-3022' ),
		drifted: page.locator( '#post-3036' ),
		evergreen: page.locator( '#post-3024' ),
	},
	{ full: true }
);

// 3. The calendar: month, week, list.
await openCalendar();
await shot( 'cal-month', {
	grid: page.locator( '.masthead-grid' ),
	legend: page.locator( '.masthead-legend' ),
	week: button( 'Week' ),
	list: button( 'List' ),
	month: button( 'Month' ),
	head: page.locator( '.masthead-grid__head' ),
	published: chip( 3021 ),
	scheduled: chip( 3032 ),
	draft: chip( 3041 ),
	ghost: day( '2026-11-20' ).locator( '.masthead-ghost' ),
	openSat: day( '2026-11-28' ).locator( '.masthead-open-slot' ),
	openWed: day( '2026-11-25' ).locator( '.masthead-open-slot' ),
	openThu: day( '2026-11-26' ).locator( '.masthead-open-slot' ),
	nov19: day( '2026-11-19' ).locator( '.masthead-day__button' ),
	rhythm: page.locator( '.masthead-header__rhythm' ),
	desk: page.locator( '.masthead-desk' ),
	menu: page.locator( '#adminmenu a[href="edit.php?page=masthead"]' ),
	hSun: page.locator( '.masthead-grid__weekday' ).nth( 0 ),
	hMon: page.locator( '.masthead-grid__weekday' ).nth( 1 ),
	hTue: page.locator( '.masthead-grid__weekday' ).nth( 2 ),
	hThu: page.locator( '.masthead-grid__weekday' ).nth( 4 ),
	hSat: page.locator( '.masthead-grid__weekday' ).nth( 6 ),
	toolbar: page.locator( '.masthead-toolbar' ),
} );
await button( 'Week' ).click();
await page.waitForSelector( '.masthead-week, .masthead-grid' );
await settle( 800 );
await shot( 'cal-week', { list: button( 'List' ), grid: page.locator( '.masthead-main' ) } );
await button( 'List' ).click();
await page.waitForSelector( '.masthead-list__row' );
await settle( 800 );
await shot( 'cal-list', { month: button( 'Month' ), rows: page.locator( '.masthead-list' ) } );

// 4. The rhythm, in Settings › Calendar.
await page.goto( SETTINGS );
await page.waitForSelector( '.masthead-rhythm tbody tr' );
await page.waitForSelector( '.masthead-series__item' );
await settle( 800 );
const rows = page.locator( '.masthead-rhythm tbody tr' );
await shot( 'rhythm', {
	table: page.locator( '.masthead-rhythm' ),
	mon: rows.nth( 1 ),
	tue: rows.nth( 2 ),
	thu: rows.nth( 4 ),
	sat: rows.nth( 6 ),
	thuLane: rows.nth( 4 ).locator( 'input[type="text"]' ),
	thuCats: rows.nth( 4 ).locator( '.masthead-picker summary' ),
	thuTime: rows.nth( 4 ).locator( 'input[type="time"]' ),
	satTime: rows.nth( 6 ).locator( 'input[type="time"]' ),
} );
await rows.nth( 4 ).locator( '.masthead-picker summary' ).click();
await settle( 300 );
await shot(
	'rhythm-picker',
	{
		thuCats: rows.nth( 4 ).locator( '.masthead-picker summary' ),
		picker: rows.nth( 4 ).locator( '.masthead-picker' ),
		thuTime: rows.nth( 4 ).locator( 'input[type="time"]' ),
		satTime: rows.nth( 6 ).locator( 'input[type="time"]' ),
		table: page.locator( '.masthead-rhythm' ),
	},
	{ keepMouse: true }
);

// 5. The Desk on an open Thursday.
await openCalendar();
await day( '2026-11-19' ).locator( '.masthead-day__button' ).click();
await page.waitForSelector( '.masthead-suggestion' );
await settle( 800 );
const suggestion = ( kind ) => page.locator( `.masthead-suggestion[data-kind="${ kind }"]` );
await shot( 'desk', {
	desk: page.locator( '.masthead-desk' ),
	dayhead: page.locator( '.masthead-desk__day' ),
	draft: suggestion( 'draft' ),
	draftWhy: suggestion( 'draft' ).locator( '.masthead-suggestion__why' ),
	series: suggestion( 'series' ),
	gap: suggestion( 'gap' ),
	gapWhy: suggestion( 'gap' ).locator( '.masthead-suggestion__why' ),
	ideas: page.getByRole( 'button', { name: 'Suggest ideas' } ),
	nov19: day( '2026-11-19' ),
} );
await page.getByRole( 'button', { name: 'Suggest ideas' } ).click();
await page.waitForSelector( '.masthead-suggestion[data-kind="idea"]' );
await settle( 800 );
await shot(
	'desk-ideas',
	{
		desk: page.locator( '.masthead-desk' ),
		draft: suggestion( 'draft' ),
		gap: suggestion( 'gap' ),
		gapPlan: suggestion( 'gap' ).getByRole( 'button', { name: 'Plan this' } ),
		ideas: page.locator( '.masthead-suggestion[data-kind="idea"]' ).first(),
		lastIdea: page.locator( '.masthead-suggestion[data-kind="idea"]' ).last(),
	},
	{ full: true }
);

// 6. Day plan: a title, an outline, a dated draft.
await suggestion( 'gap' ).getByRole( 'button', { name: 'Plan this' } ).click();
await page.waitForSelector( '.masthead-dayplan' );
await settle( 500 );
const title = page.locator( '#masthead-dayplan-title' );
const planTargets = {
	plan: page.locator( '.masthead-dayplan' ),
	title,
	outlineButton: button( 'Draft an outline' ),
	why: page.locator( '.masthead-dayplan__section' ).nth( 1 ),
};
await shot( 'plan', planTargets );
await title.click();
await typeFrames( title, 'A Family Security Checkup', 'plan-type', 4, planTargets );
await button( 'Draft an outline' ).click();
await page.waitForSelector( '.masthead-dayplan__outline-editor' );
await settle( 600 );
await shot(
	'plan-outline',
	{
		plan: page.locator( '.masthead-dayplan' ),
		outline: page.locator( '.masthead-dayplan__outline-editor' ),
		when: page.locator( '.masthead-dayplan__when' ),
		create: page.locator( '.masthead-dayplan__actions .button-primary' ),
	},
	{ full: true }
);
await page.locator( '.masthead-dayplan__actions .button-primary' ).click();
await page.waitForSelector( '.masthead-dayplan', { state: 'detached' } );
await page.waitForSelector( '.masthead-grid [data-date="2026-11-19"] .masthead-chip' );
await settle( 1000 );
await shot( 'plan-created', {
	nov19: day( '2026-11-19' ),
	newChip: day( '2026-11-19' ).locator( '.masthead-chip' ),
	desk: page.locator( '.masthead-desk' ),
} );

// 7. Reschedule: drag a scheduled post, then move a dated draft with the keyboard.
await openCalendar();
const from = await boxOf( chip( 3033 ) );
const to = await boxOf( day( '2026-11-23' ) );
await shot( 'move', {
	chip: chip( 3033 ),
	from: day( '2026-11-16' ),
	to: day( '2026-11-23' ),
	kbdChip: chip( 3041 ),
	kbdOpen: day( '2026-11-14' ).locator( '.masthead-move__open' ),
	kbdTarget: day( '2026-11-28' ),
} );
await chip( 3033 ).screenshot( { path: `${ OUT }drag-chip.png`, animations: 'disabled' } );
manifest.shots[ 'drag-chip' ] = { full: false, size: [ from[ 2 ], from[ 3 ] ], targets: {} };
// Mid-drag: the day under the pointer wears the calendar's own drop-target style. A screenshot
// taken during a real HTML5 drag does not show it, so the class is set for this still only.
await day( '2026-11-23' ).evaluate( ( el ) => el.classList.add( 'is-drop-target' ) );
await shot( 'move-over', { chip: chip( 3033 ), to: day( '2026-11-23' ) } );
await day( '2026-11-23' ).evaluate( ( el ) => el.classList.remove( 'is-drop-target' ) );
// The real drag and drop, which calls masthead/reschedule.
await page.mouse.move( from[ 0 ] + from[ 2 ] / 2, from[ 1 ] + from[ 3 ] / 2 );
await page.mouse.down();
await page.mouse.move( to[ 0 ] + to[ 2 ] / 2, to[ 1 ] + to[ 3 ] / 2, { steps: 12 } );
await page.waitForTimeout( 300 );
await page.mouse.up();
await page.waitForSelector( '.masthead-grid [data-date="2026-11-23"] .masthead-chip[data-post-id="3033"]' );
await settle( 1200 );
await shot( 'move-dropped', {
	chip: chip( 3033 ),
	to: day( '2026-11-23' ),
	from: day( '2026-11-16' ),
	kbdChip: chip( 3041 ),
	kbdOpen: day( '2026-11-14' ).locator( '.masthead-move__open' ),
	kbdTarget: day( '2026-11-28' ),
} );
// Keyboard: focus "Move to…", Enter, type the date, Enter.
await day( '2026-11-14' ).locator( '.masthead-move__open' ).focus();
await page.keyboard.press( 'Enter' );
const dateInput = day( '2026-11-14' ).locator( '.masthead-move input[type="date"]' );
await dateInput.waitFor();
await dateInput.focus();
await settle( 300 );
await shot( 'kbd-open', { form: day( '2026-11-14' ).locator( '.masthead-move' ), cell: day( '2026-11-14' ), target: day( '2026-11-28' ) }, { keepMouse: true } );
await page.keyboard.type( '11282026', { delay: 40 } );
await settle( 300 );
await shot( 'kbd-date', { form: day( '2026-11-14' ).locator( '.masthead-move' ), cell: day( '2026-11-14' ), target: day( '2026-11-28' ) }, { keepMouse: true } );
await page.keyboard.press( 'Enter' );
await page.waitForSelector( '.masthead-grid [data-date="2026-11-28"] .masthead-chip[data-post-id="3041"]' );
await settle( 1200 );
await shot( 'kbd-moved', { chip: chip( 3041 ), cell: day( '2026-11-14' ), target: day( '2026-11-28' ), dragged: chip( 3033 ) } );

// 8. Ask the Desk for a series.
await openCalendar();
const prompt = page.locator( '#masthead-ask-prompt' );
const askTargets = {
	ask: page.locator( '.masthead-ask' ),
	prompt,
	start: page.locator( '#masthead-ask-start' ),
	askButton: page.locator( '.masthead-ask form button[type="submit"]' ),
};
await shot( 'ask', askTargets );
await prompt.click();
await typeFrames( prompt, 'A 4-part security series starting in January', 'ask-type', 4, askTargets );
await page.locator( '#masthead-ask-start' ).focus();
await page.keyboard.type( '01012027', { delay: 40 } );
await settle( 300 );
await shot( 'ask-start', askTargets, { keepMouse: true } );
await page.locator( '.masthead-ask form button[type="submit"]' ).click();
await page.waitForSelector( '.masthead-ask__proposal' );
await settle( 800 );
await shot(
	'ask-proposal',
	{
		ask: page.locator( '.masthead-ask' ),
		proposal: page.locator( '.masthead-ask__proposal' ),
		parts: page.locator( '.masthead-ask__parts' ),
		firstPart: page.locator( '.masthead-ask__parts > li' ).first(),
		dates: page.locator( '.masthead-ask__parts > li input[type="date"]' ).first(),
		lastDate: page.locator( '.masthead-ask__parts > li input[type="date"]' ).last(),
		lane: page.locator( '.masthead-ask__proposal > p' ).nth( 1 ),
		name: page.locator( '#masthead-ask-name' ),
		accept: button( 'Accept series' ),
	},
	{ full: true }
);
await button( 'Accept series' ).click();
await page.waitForSelector( '.masthead-ask__created' );
await settle( 1000 );
await shot( 'ask-created', {
	created: page.locator( '.masthead-ask__created' ),
	next: page.getByRole( 'button', { name: 'Next month' } ),
	ask: page.locator( '.masthead-ask' ),
} );
await page.getByRole( 'button', { name: 'Next month' } ).click();
await settle( 1500 );
await shot( 'ask-dec', { next: page.getByRole( 'button', { name: 'Next month' } ) }, { full: true } );
await page.getByRole( 'button', { name: 'Next month' } ).click();
await page.waitForSelector( '.masthead-grid [data-date="2027-01-07"] .masthead-chip' );
await settle( 1500 );
await shot(
	'ask-january',
	{
		grid: page.locator( '.masthead-grid' ),
		jan7: day( '2027-01-07' ),
		jan14: day( '2027-01-14' ),
		jan21: day( '2027-01-21' ),
		jan28: day( '2027-01-28' ),
		title: page.locator( '.masthead-toolbar' ),
	},
	{ full: true }
);

// 9. The swap check, in the list.
await openCalendar( 'list' );
const row = ( id ) => page.locator( `.masthead-list__row[data-post-id="${ id }"]` );
await shot(
	'swap-list',
	{
		list: page.locator( '.masthead-list' ),
		checked: page.locator( '.masthead-list__checked' ),
		mantle: row( 3036 ),
		mantleVerdict: row( 3036 ).locator( '.masthead-list__verdict' ),
		collisionA: row( 3034 ),
		collisionB: row( 3035 ),
	},
	{ full: true }
);
await row( 3036 ).click();
await page.waitForSelector( '.masthead-swap__finding' );
await settle( 600 );
const finding = ( type ) => page.locator( `.masthead-swap__finding[data-type="${ type }"]` );
await shot(
	'swap-panel',
	{
		panel: page.locator( '.masthead-swap' ),
		lane: finding( 'lane_mismatch' ),
		cadence: finding( 'cadence_break' ),
		fix: finding( 'cadence_break' ).locator( '.masthead-swap__option' ),
		mantle: row( 3036 ),
	},
	{ full: true }
);
await finding( 'cadence_break' ).locator( '.masthead-swap__option' ).click();
await page.waitForFunction( () => document.querySelector( '.masthead-swap__outcome' )?.textContent.trim() === 'Done.' );
await settle( 1200 );
await shot(
	'swap-fixed',
	{
		panel: page.locator( '.masthead-swap' ),
		mantle: row( 3036 ),
		mantleVerdict: row( 3036 ).locator( '.masthead-list__verdict' ),
		list: page.locator( '.masthead-list' ),
	},
	{ full: true }
);

await browser.close();

// 11. Real output for the MCP scene, after everything above.
const [ weekFrom, weekTo ] = NEXT_WEEK;
const table = wp( [ [ 'masthead', 'plan', weekFrom, weekTo, `--now=${ DEMO_NOW }` ] ], { tables: true } );
const json = wp( [
	[
		'--user=admin',
		'eval',
		`echo wp_json_encode( wp_get_ability( 'masthead/get-plan' )->execute( array( 'from' => '${ weekFrom }', 'to' => '${ weekTo }' ) ), JSON_PRETTY_PRINT );`,
	],
] );
await writeFile( `${ OUT }mcp-plan.txt`, `$ wp masthead plan ${ weekFrom } ${ weekTo }\n${ table.trim() }\n` );
await writeFile( `${ OUT }mcp-get-plan.json`, `${ json.trim() }\n` );
manifest.mcp = { from: weekFrom, to: weekTo };

await writeFile( `${ OUT }manifest.json`, `${ JSON.stringify( manifest, null, 2 ) }\n` );
console.log( `${ Object.keys( manifest.shots ).length } stills in ${ OUT }` );
