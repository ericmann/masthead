import fs from 'node:fs';
import path from 'node:path';

import {
	shouldInclude,
	tagMatches,
	checksumLine,
	missingDocs,
} from '../../../scripts/release/pack.mjs';

describe( 'release pack', () => {
	it( 'includes plugin code and build output', () => {
		expect( shouldInclude( 'masthead.php' ) ).toBe( true );
		expect( shouldInclude( 'src/Plugin.php' ) ).toBe( true );
		expect( shouldInclude( 'src/Admin/Assets.php' ) ).toBe( true );
		expect( shouldInclude( 'build/index.js' ) ).toBe( true );
		expect( shouldInclude( 'LICENSE' ) ).toBe( true );
		expect( shouldInclude( 'readme.txt' ) ).toBe( true );
	} );

	it( 'excludes tests, docs, client sources and tool configuration', () => {
		[
			'tests/unit/ConfigTest.php',
			'docs/SPEC.md',
			'client/index.js',
			'scripts/release/pack.mjs',
			'vendor/autoload.php',
			'node_modules/a/b.js',
			'.eslintrc.js',
			'.wp-env.json',
			'package.json',
			'composer.json',
			'phpcs.xml.dist',
			'src/.hidden.php',
		].forEach( ( file ) => {
			expect( shouldInclude( file ) ).toBe( false );
		} );
	} );

	it( 'tag must equal v plus the package version', () => {
		expect( tagMatches( 'v1.2.3', '1.2.3' ) ).toBe( true );
		expect( tagMatches( '1.2.3', '1.2.3' ) ).toBe( false );
		expect( tagMatches( 'v1.2.4', '1.2.3' ) ).toBe( false );
		expect( tagMatches( 'v', '' ) ).toBe( false );
	} );

	it( 'checksum line is sha256sum compatible', () => {
		const hex = 'a'.repeat( 64 );
		expect( checksumLine( hex ) ).toBe( `${ hex }  masthead.zip\n` );
	} );

	it( 'includes readme.txt and CHANGELOG.md in the zip', () => {
		expect( shouldInclude( 'readme.txt' ) ).toBe( true );
		expect( shouldInclude( 'CHANGELOG.md' ) ).toBe( true );
		expect(
			missingDocs( '0.1.0', [
				'masthead.php',
				'readme.txt',
				'README.md',
			] )
		).toEqual( [ 'CHANGELOG.md' ] );
		expect(
			missingDocs( '0.1.0', [
				'readme.txt',
				'README.md',
				'CHANGELOG.md',
			] )
		).toEqual( [] );
		expect( missingDocs( '0.1.0', [] ) ).toEqual( [
			'readme.txt',
			'README.md',
			'CHANGELOG.md',
		] );
	} );

	it( 'includes README.md in the zip', () => {
		expect( shouldInclude( 'README.md' ) ).toBe( true );
		expect(
			missingDocs( '0.1.0', [ 'readme.txt', 'CHANGELOG.md' ] )
		).toEqual( [ 'README.md' ] );
		expect( shouldInclude( 'docs/screenshots/settings.png' ) ).toBe(
			false
		);
	} );

	it( 'does not require the docs for the unreleased 0.0.0', () => {
		expect( missingDocs( '0.0.0', [] ) ).toEqual( [] );
	} );

	it( 'README.md references five non-empty screenshots', () => {
		const readme = fs.readFileSync(
			path.join( process.cwd(), 'README.md' ),
			'utf8'
		);
		[
			'calendar-month',
			'day-plan',
			'swap-panel',
			'settings',
			'demo-poster',
		].forEach( ( name ) => {
			const relative = `docs/screenshots/${ name }.png`;
			expect( readme ).toContain( relative );
			expect(
				fs.statSync( path.join( process.cwd(), relative ) ).size
			).toBeGreaterThan( 0 );
		} );
	} );
} );
