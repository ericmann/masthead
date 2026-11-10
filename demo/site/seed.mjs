// Loads the demo site (Northfield Notes) into the running wp-env, or puts the e2e defaults back.
//
//   node demo/site/seed.mjs            seed the demo: posts, stats, AI answers, names, findings
//   node demo/site/seed.mjs --restore  drop the demo options and names (the e2e suite reseeds its own posts)
//
// Local only: `wp masthead seed` refuses to run outside a local environment, and the fake AI client
// and fixture stats come from the e2e mu-plugins, which wp-env maps only in development.
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const ROOT = fileURLToPath( new URL( '../../', import.meta.url ) );

/** The pinned clock of the demo: Tuesday, 9 a.m. in Los Angeles. */
export const DEMO_NOW = '2026-11-10 09:00:00';

/** Options the demo sets on top of the seeded posts; the e2e helpers delete them before every spec. */
export const DEMO_OPTIONS = [ 'masthead_e2e_ai_dir', 'masthead_e2e_stats_file' ];

const IN_PLUGIN = 'wp-content/plugins/masthead/demo/site';

function quote( value ) {
	return `'${ String( value ).replaceAll( "'", "'\\''" ) }'`;
}

/**
 * Runs wp-cli commands in the wp-env cli container, in one shell, stopping at the first failure.
 * SHELL_PIPE=0 (tables: true) keeps WP-CLI's bordered tables, 120 columns wide, although the output is piped.
 */
export function wp( commands, { tables = false } = {} ) {
	const prefix = tables ? 'SHELL_PIPE=0 COLUMNS=120 ' : '';
	const script = commands.map( ( args ) => prefix + [ 'wp', ...args ].map( quote ).join( ' ' ) ).join( ' && ' );
	return execFileSync( 'npx', [ 'wp-env', 'run', 'cli', 'bash', '-c', script ], {
		cwd: ROOT,
		encoding: 'utf8',
		stdio: [ 'ignore', 'pipe', 'pipe' ],
	} );
}

export function seedDemo() {
	return wp( [
		[ 'masthead', 'seed', `--fixture=${ IN_PLUGIN }/fixture.json`, `--now=${ DEMO_NOW }`, '--reset' ],
		[ 'option', 'update', 'blogname', 'Northfield Notes' ],
		[ 'option', 'update', 'start_of_week', '0' ],
		[ 'user', 'update', 'admin', '--display_name=Sam', '--first_name=Sam', '--nickname=Sam' ],
		// Fixture stats and the fake AI client (tests/e2e/mu), pointed at the demo's own files.
		[ 'option', 'update', 'masthead_e2e_stats', '1' ],
		[ 'option', 'update', 'masthead_e2e_stats_file', 'demo/site/stats.json' ],
		[ 'option', 'update', 'masthead_e2e_ai_dir', 'demo/site/ai' ],
		[ 'option', 'delete', 'masthead_e2e_ai', 'masthead_ai_usage' ],
		// What the nightly check would have stored for this morning.
		[ 'masthead', 'swap-check', '--write', `--now=${ DEMO_NOW }` ],
	] );
}

export function restoreDefaults() {
	return wp( [
		[ 'option', 'delete', 'masthead_e2e_stats', ...DEMO_OPTIONS ],
		[ 'option', 'update', 'blogname', 'masthead' ],
		[ 'user', 'update', 'admin', '--display_name=admin', '--first_name=', '--nickname=admin' ],
	] );
}

if ( process.argv[ 1 ] === fileURLToPath( import.meta.url ) ) {
	const out = process.argv.includes( '--restore' ) ? restoreDefaults() : seedDemo();
	process.stdout.write( out );
}
