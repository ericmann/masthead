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
	return wpCli( [
		'masthead',
		'seed',
		'--fixture=wp-content/masthead-fixtures/queue-2026-11-09.json',
		'--now=2026-11-09 14:00:00',
		'--reset',
	] );
}
