import {
	shouldInclude,
	tagMatches,
	checksumLine,
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
} );
