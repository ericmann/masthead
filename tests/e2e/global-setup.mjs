import { chromium } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = path.dirname( fileURLToPath( import.meta.url ) );
const BASE_URL = 'http://localhost:8888';

export default async function globalSetup() {
	const statePath = path.join( dir, '.auth', 'admin.json' );
	fs.mkdirSync( path.dirname( statePath ), { recursive: true } );

	const browser = await chromium.launch();
	const page = await browser.newPage( { baseURL: BASE_URL } );
	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'password' );
	await page.click( '#wp-submit' );
	await page.waitForURL( /wp-admin/ );
	await page.context().storageState( { path: statePath } );
	await browser.close();
}
