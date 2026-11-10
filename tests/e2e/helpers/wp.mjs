import { execFileSync } from 'node:child_process';

/**
 * Runs `wp <args>` in the wp-env development container and returns stdout.
 *
 * @param {string[]} args WP-CLI arguments.
 * @return {string} Trimmed stdout.
 */
export function wpCli( args ) {
	return execFileSync( 'npx', [ 'wp-env', 'run', 'cli', 'wp', ...args ], {
		encoding: 'utf8',
		stdio: [ 'ignore', 'pipe', 'ignore' ],
	} ).trim();
}

/**
 * Re-seeds the fixture queue with the pinned clock.
 *
 * @return {string} Seed command output.
 */
export function seedFixture() {
	setFixtureStats( false );
	return wpCli( [
		'masthead',
		'seed',
		'--fixture=wp-content/masthead-fixtures/queue-2026-11-09.json',
		'--now=2026-11-09 14:00:00',
		'--reset',
	] );
}

/**
 * Turns fixture stats on or off for the e2e site.
 *
 * @param {boolean} on Whether Masthead should read docs/fixtures/stats-sample.json.
 */
export function setFixtureStats( on ) {
	if ( on ) {
		wpCli( [ 'option', 'update', 'masthead_e2e_stats', '1' ] );
		return;
	}
	try {
		wpCli( [ 'option', 'delete', 'masthead_e2e_stats' ] );
	} catch {
		// The option was not set.
	}
}
