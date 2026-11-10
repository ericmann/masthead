import { defineConfig, devices } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = path.dirname( fileURLToPath( import.meta.url ) );

export const STORAGE_STATE = path.join( dir, '.auth', 'admin.json' );

export default defineConfig( {
	testDir: dir,
	testMatch: '**/*.spec.js',
	workers: 1,
	fullyParallel: false,
	retries: 0,
	reporter: 'list',
	globalSetup: path.join( dir, 'global-setup.mjs' ),
	outputDir: path.join( dir, '..', '..', 'test-results' ),
	use: {
		baseURL: 'http://localhost:8888',
		storageState: STORAGE_STATE,
		trace: 'retain-on-failure',
	},
	projects: [
		{
			name: 'desktop',
			use: {
				...devices[ 'Desktop Chrome' ],
				viewport: { width: 1280, height: 800 },
			},
		},
		{
			name: 'mobile',
			use: {
				...devices[ 'Desktop Chrome' ],
				viewport: { width: 390, height: 844 },
			},
		},
	],
} );
