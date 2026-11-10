import { renderHook, act } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';

import { resetBoot } from '../../../client/data/boot';
import { runAbility, useAbility } from '../../../client/data/useAbility';

jest.mock( '@wordpress/api-fetch' );

describe( 'useAbility', () => {
	beforeEach( () => {
		window.mastheadBoot = {
			run: {
				path: '/wp-abilities/v1/abilities/%s/run',
				readonly: { method: 'GET', input: 'query' },
				write: { method: 'POST', input: 'body' },
			},
			abilities: {
				'masthead/get-plan': { readonly: true },
				'masthead/reschedule': { readonly: false },
			},
		};
		resetBoot();
		apiFetch.mockReset();
		apiFetch.mockResolvedValue( { ok: true } );
	} );

	it( 'read-only abilities use the read-only method and input placement from boot data', async () => {
		await runAbility( 'masthead/get-plan', {
			from: '2026-11-09',
			to: '2026-11-15',
		} );
		const request = apiFetch.mock.calls[ 0 ][ 0 ];
		expect( request.method ).toBe( 'GET' );
		expect( decodeURIComponent( request.path ) ).toBe(
			'/wp-abilities/v1/abilities/masthead/get-plan/run?input[from]=2026-11-09&input[to]=2026-11-15'
		);
		expect( request.data ).toBeUndefined();
	} );

	it( 'other abilities post the input', async () => {
		await runAbility( 'masthead/reschedule', {
			post_id: 5,
			date: '2026-12-01',
		} );
		expect( apiFetch ).toHaveBeenCalledWith( {
			path: '/wp-abilities/v1/abilities/masthead/reschedule/run',
			method: 'POST',
			data: { input: { post_id: 5, date: '2026-12-01' } },
		} );
	} );

	it( 'a REST error rejects with code and message', async () => {
		apiFetch.mockRejectedValue( {
			code: 'masthead_published_locked',
			message: 'Published posts cannot be rescheduled.',
			data: { status: 409 },
		} );
		await expect( runAbility( 'masthead/reschedule', {} ) ).rejects.toEqual(
			{
				code: 'masthead_published_locked',
				message: 'Published posts cannot be rescheduled.',
			}
		);

		const { result } = renderHook( () =>
			useAbility( 'masthead/reschedule' )
		);
		await act( async () => {
			await expect( result.current.run( {} ) ).rejects.toBeDefined();
		} );
		expect( result.current.error.code ).toBe( 'masthead_published_locked' );
		expect( result.current.isRunning ).toBe( false );
	} );
} );
