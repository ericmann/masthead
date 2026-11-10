import fs from 'fs';
import path from 'path';

const read = ( file ) =>
	fs.readFileSync(
		path.join( __dirname, '..', '..', '..', '.github', 'workflows', file ),
		'utf8'
	);

describe( 'workflows', () => {
	it( 'ci runs every verify command from the spec', () => {
		const ci = read( 'ci.yml' );
		[
			'composer lint',
			'composer test:unit',
			'npm run lint',
			'npm run test:unit',
			'bash scripts/forbidden-patterns.sh',
			'npm run build',
		].forEach( ( cmd ) => expect( ci ).toContain( `run: ${ cmd }` ) );
		expect( ci ).toContain( 'node-version-file: .nvmrc' );
		expect( ci ).toContain( "php-version: '8.3'" );
	} );

	it( 'ci runs integration and e2e jobs', () => {
		const ci = read( 'ci.yml' );
		expect( ci ).toContain( 'run: npm run test:integration' );
		expect( ci ).toContain( 'npx playwright install --with-deps chromium' );
		expect( ci ).toContain( 'npm run test:e2e' );
		expect( ci ).toContain( 'upload-artifact' );
	} );

	it( 'ci runs both audits', () => {
		const ci = read( 'ci.yml' );
		expect( ci ).toContain( 'composer audit' );
		expect( ci ).toContain( 'npm audit --audit-level=high' );
	} );

	it( 'release checks the tag before packing', () => {
		const release = read( 'release.yml' );
		const check = release.indexOf( '--check-tag "$GITHUB_REF_NAME"' );
		const pack = release.indexOf( 'npm run release:pack' );
		expect( check ).toBeGreaterThan( -1 );
		expect( pack ).toBeGreaterThan( check );
		expect( release ).toContain( "- 'v*'" );
		expect( release ).toContain( 'contents: write' );
	} );

	it( 'release uploads the zip and its checksum', () => {
		const release = read( 'release.yml' );
		expect( release ).toContain( 'dist/masthead.zip' );
		expect( release ).toContain( 'dist/masthead.zip.sha256' );
		expect( release ).toContain( 'gh release create' );
		expect( release ).not.toContain( 'no-gpg-sign' );
	} );
} );
