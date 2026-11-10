import { renderHook, act } from '@testing-library/react';

import { useSettings } from '../../../client/settings/useSettings';

const mockEdit = jest.fn();
const mockSave = jest.fn();
let mockState;

jest.mock( '@wordpress/data', () => ( {
	useSelect: ( mapper ) =>
		mapper( () => ( {
			getEditedEntityRecord: () => mockState.record,
			isSavingEntityRecord: () => mockState.isSaving,
			getLastEntitySaveError: () => mockState.error,
		} ) ),
	useDispatch: () => ( {
		editEntityRecord: mockEdit,
		saveEditedEntityRecord: mockSave,
	} ),
} ) );

describe( 'useSettings', () => {
	beforeEach( () => {
		mockEdit.mockReset();
		mockSave.mockReset();
		mockSave.mockResolvedValue( {} );
		mockState = {
			record: {
				masthead_rhythm: { 5: { time: '08:00' } },
				masthead_settings: { desk: { horizon_days: 21 } },
			},
			isSaving: false,
			error: null,
		};
	} );

	it( 'save persists the edited site settings', async () => {
		const { result } = renderHook( () => useSettings() );
		expect( result.current.rhythm ).toEqual( { 5: { time: '08:00' } } );
		act( () => result.current.setRhythm( { 5: { time: '09:15' } } ) );
		expect( mockEdit ).toHaveBeenCalledWith( 'root', 'site', undefined, {
			masthead_rhythm: { 5: { time: '09:15' } },
		} );
		act( () => result.current.setSetting( 'desk.gap_days', 60 ) );
		expect( mockEdit ).toHaveBeenLastCalledWith(
			'root',
			'site',
			undefined,
			{
				masthead_settings: { desk: { horizon_days: 21, gap_days: 60 } },
			}
		);
		await act( async () => {
			await result.current.save();
		} );
		expect( mockSave ).toHaveBeenCalledWith( 'root', 'site' );
		expect( result.current.isSaved ).toBe( true );
	} );

	it( 'a failed save reports the error', async () => {
		mockState.error = {
			code: 'rest_forbidden',
			message: 'Sorry, you are not allowed to do that.',
		};
		const { result } = renderHook( () => useSettings() );
		await act( async () => {
			await result.current.save();
		} );
		expect( result.current.error.message ).toBe(
			'Sorry, you are not allowed to do that.'
		);
		expect( result.current.isSaved ).toBe( false );
	} );
} );
