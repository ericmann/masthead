import { renderHook } from '@testing-library/react';

import { resetBoot } from '../../../client/data/boot';
import { useSeries } from '../../../client/settings/SeriesCadence';

const mockGetEntityRecords = jest.fn();

jest.mock( '@wordpress/data', () => ( {
	useSelect: ( selector ) =>
		selector( () => ( {
			getEntityRecords: mockGetEntityRecords,
			getEditedEntityRecord: ( kind, name, id ) => ( { id, meta: {} } ),
		} ) ),
	useDispatch: () => ( {
		editEntityRecord: () => {},
		saveEditedEntityRecord: () => {},
	} ),
} ) );

describe( 'useSeries', () => {
	beforeEach( () => {
		mockGetEntityRecords.mockReset();
		mockGetEntityRecords.mockReturnValue( [ { id: 55 } ] );
		window.mastheadBoot = { series: { taxonomy: 'series' } };
		resetBoot();
	} );

	it( 'reads the series terms in the edit context, where the planning meta lives', () => {
		const { result } = renderHook( () => useSeries() );
		expect( mockGetEntityRecords ).toHaveBeenCalledWith(
			'taxonomy',
			'series',
			expect.objectContaining( { context: 'edit' } )
		);
		expect( result.current.terms ).toEqual( [ { id: 55, meta: {} } ] );
	} );
} );
