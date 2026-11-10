import AxeBuilder from '@axe-core/playwright';
import { expect } from '@playwright/test';

/**
 * Fails on any serious or critical axe violation inside the root element.
 *
 * @param {import('@playwright/test').Page} page         Page.
 * @param {string}                          rootSelector CSS selector of the Masthead root.
 */
export async function expectNoSeriousViolations( page, rootSelector ) {
	const results = await new AxeBuilder( { page } )
		.include( rootSelector )
		.analyze();
	const serious = results.violations.filter( ( violation ) =>
		[ 'serious', 'critical' ].includes( violation.impact )
	);
	expect(
		serious.map( ( violation ) => `${ violation.id }: ${ violation.help }` )
	).toEqual( [] );
}
