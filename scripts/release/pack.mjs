import { execFileSync } from 'node:child_process';
import crypto from 'node:crypto';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';

// npm runs scripts from the repository root.
const ROOT = process.cwd();

const TOP_LEVEL_FILES = [
	'masthead.php',
	'LICENSE',
	'readme.txt',
	'README.md',
	'CHANGELOG.md',
];
const TOP_LEVEL_DIRS = [ 'src', 'build' ];
const FORBIDDEN = [
	'tests/',
	'docs/',
	'client/',
	'scripts/',
	'vendor/',
	'node_modules/',
];

/**
 * Whether a repository-relative path ships in the release zip.
 *
 * @param {string} relativePath Path relative to the repository root.
 * @return {boolean} True when the file is shippable.
 */
export function shouldInclude( relativePath ) {
	const parts = relativePath.split( '/' ).filter( Boolean );
	if ( parts.length === 0 || parts.some( ( p ) => p.startsWith( '.' ) ) ) {
		return false;
	}
	if ( parts.length === 1 ) {
		return TOP_LEVEL_FILES.includes( parts[ 0 ] );
	}
	return TOP_LEVEL_DIRS.includes( parts[ 0 ] );
}

/**
 * Whether a tag is `v` plus the package version.
 *
 * @param {string} tag     Git tag.
 * @param {string} version package.json version.
 * @return {boolean} True when they agree.
 */
export function tagMatches( tag, version ) {
	return Boolean( version ) && tag === `v${ version }`;
}

/**
 * A line that `sha256sum -c` accepts.
 *
 * @param {string} hex Hex digest.
 * @return {string} Checksum line with a trailing newline.
 */
export function checksumLine( hex ) {
	return `${ hex }  masthead.zip\n`;
}

function walk( dir, base = '' ) {
	const found = [];
	for ( const entry of fs
		.readdirSync( path.join( dir, base ), { withFileTypes: true } )
		.sort( ( a, b ) => ( a.name < b.name ? -1 : 1 ) ) ) {
		const rel = base ? `${ base }/${ entry.name }` : entry.name;
		if ( entry.isDirectory() ) {
			found.push( ...walk( dir, rel ) );
		} else {
			found.push( rel );
		}
	}
	return found;
}

function pack() {
	const dist = path.join( ROOT, 'dist' );
	fs.rmSync( dist, { recursive: true, force: true } );
	fs.mkdirSync( dist, { recursive: true } );

	const stage = fs.mkdtempSync( path.join( os.tmpdir(), 'masthead-pack-' ) );
	const target = path.join( stage, 'masthead' );
	fs.mkdirSync( target );

	const candidates = [
		...TOP_LEVEL_FILES.filter( ( f ) =>
			fs.existsSync( path.join( ROOT, f ) )
		),
		...TOP_LEVEL_DIRS.filter( ( d ) =>
			fs.existsSync( path.join( ROOT, d ) )
		).flatMap( ( d ) => walk( ROOT, d ) ),
	]
		.filter( shouldInclude )
		.sort();

	for ( const rel of candidates ) {
		const dest = path.join( target, rel );
		fs.mkdirSync( path.dirname( dest ), { recursive: true } );
		fs.copyFileSync( path.join( ROOT, rel ), dest );
	}

	const zipPath = path.join( dist, 'masthead.zip' );
	execFileSync( 'zip', [ '-X', '-q', '-r', zipPath, 'masthead' ], {
		cwd: stage,
	} );

	const listing = execFileSync( 'unzip', [ '-Z1', zipPath ], {
		encoding: 'utf8',
	} )
		.split( '\n' )
		.filter( Boolean );
	fs.rmSync( stage, { recursive: true, force: true } );

	const bad = listing.filter( ( entry ) =>
		FORBIDDEN.some( ( f ) => entry.startsWith( `masthead/${ f }` ) )
	);
	if ( bad.length > 0 ) {
		throw new Error( `Forbidden paths in archive: ${ bad.join( ', ' ) }` );
	}
	for ( const required of [
		'masthead/masthead.php',
		'masthead/build/index.js',
	] ) {
		if ( ! listing.includes( required ) ) {
			throw new Error( `Archive is missing ${ required }` );
		}
	}

	const hex = crypto
		.createHash( 'sha256' )
		.update( fs.readFileSync( zipPath ) )
		.digest( 'hex' );
	fs.writeFileSync( `${ zipPath }.sha256`, checksumLine( hex ) );
	// eslint-disable-next-line no-console
	console.log( `Packed ${ listing.length } entries into dist/masthead.zip` );
}

function main( argv ) {
	const flag = argv.indexOf( '--check-tag' );
	if ( flag !== -1 ) {
		const version = JSON.parse(
			fs.readFileSync( path.join( ROOT, 'package.json' ), 'utf8' )
		).version;
		const ok = tagMatches( argv[ flag + 1 ] ?? '', version );
		if ( ! ok ) {
			// eslint-disable-next-line no-console
			console.error(
				`Tag ${ argv[ flag + 1 ] } does not match v${ version }`
			);
		}
		process.exit( ok ? 0 : 1 );
	}
	try {
		pack();
	} catch ( error ) {
		// eslint-disable-next-line no-console
		console.error( error.message );
		process.exit( 1 );
	}
}

if ( process.argv[ 1 ] && process.argv[ 1 ].endsWith( 'pack.mjs' ) ) {
	main( process.argv.slice( 2 ) );
}
