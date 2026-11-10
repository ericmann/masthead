import { resetBoot } from '../../../client/data/boot';
import {
	NEUTRAL_COLOR,
	categoryColor,
	laneColor,
} from '../../../client/data/colors';

describe( 'colors', () => {
	beforeEach( () => {
		window.mastheadBoot = {
			categories: [
				{ slug: 'technology', name: 'Technology', color: '#2f5fd0' },
				{ slug: 'journal', name: 'Journal', color: '#1f7a52' },
			],
		};
		resetBoot();
	} );

	it( 'reads a category colour from boot data', () => {
		expect( categoryColor( 'journal' ) ).toBe( '#1f7a52' );
		expect( categoryColor( 'missing' ) ).toBeNull();
		expect( categoryColor( undefined ) ).toBeNull();
	} );

	it( "uses the lane's first category before the suggestion's", () => {
		expect(
			laneColor(
				{ categories: [ 'journal', 'technology' ] },
				{ category: 'technology' }
			)
		).toBe( '#1f7a52' );
	} );

	it( "falls back to the suggestion's category for a lane without one, then to grey", () => {
		expect(
			laneColor( { categories: [] }, { category: 'technology' } )
		).toBe( '#2f5fd0' );
		expect( laneColor( { categories: [] }, { kind: 'gap' } ) ).toBe(
			NEUTRAL_COLOR
		);
		expect( laneColor( null ) ).toBe( NEUTRAL_COLOR );
	} );
} );
