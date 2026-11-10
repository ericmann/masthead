import {
	addDays,
	diffDays,
	weekday,
	weekRange,
	monthGrid,
	dayLabel,
	monthLabel,
	timeLabel,
	shortWeekdayMonthDayLabel,
	spanLabel,
} from '../../../client/data/dates';

describe( 'dates', () => {
	it( 'monthGrid for November 2026 starting Sunday has five weeks from Nov 1 to Dec 5', () => {
		const grid = monthGrid( '2026-11-14', 0 );
		expect( grid ).toHaveLength( 5 );
		expect( grid[ 0 ][ 0 ] ).toBe( '2026-11-01' );
		expect( grid[ 4 ][ 6 ] ).toBe( '2026-12-05' );
		grid.forEach( ( week ) => expect( week ).toHaveLength( 7 ) );
	} );

	it( 'monthGrid honours a Monday week start', () => {
		const grid = monthGrid( '2026-11-14', 1 );
		expect( grid[ 0 ][ 0 ] ).toBe( '2026-10-26' );
		expect( grid[ grid.length - 1 ][ 6 ] ).toBe( '2026-12-06' );
		expect( grid ).toHaveLength( 6 );
	} );

	it( 'weekday of 2026-11-09 is 1', () => {
		expect( weekday( '2026-11-09' ) ).toBe( 1 );
		expect( weekday( '2026-11-08' ) ).toBe( 0 );
	} );

	it( 'addDays crosses a month boundary', () => {
		expect( addDays( '2026-11-30', 1 ) ).toBe( '2026-12-01' );
		expect( addDays( '2026-03-01', -1 ) ).toBe( '2026-02-28' );
		expect( diffDays( '2026-11-09', '2026-12-14' ) ).toBe( 35 );
		expect( diffDays( '2026-12-14', '2026-11-09' ) ).toBe( -35 );
	} );

	it( 'weekRange returns seven days', () => {
		expect( weekRange( '2026-11-11', 0 ) ).toEqual( [
			'2026-11-08',
			'2026-11-09',
			'2026-11-10',
			'2026-11-11',
			'2026-11-12',
			'2026-11-13',
			'2026-11-14',
		] );
		expect( weekRange( '2026-11-08', 1 )[ 0 ] ).toBe( '2026-11-02' );
	} );

	it( 'formats labels', () => {
		expect( monthLabel( '2026-11-09' ) ).toBe( 'November 2026' );
		expect( dayLabel( '2026-11-09' ) ).toBe( 'Monday, November 9' );
		expect( timeLabel( '2026-11-11T08:00:00-08:00' ) ).toBe( '8:00 am' );
		expect( timeLabel( null ) ).toBe( '' );
	} );

	it( 'short weekday label has a comma and spans name the month once', () => {
		expect( shortWeekdayMonthDayLabel( '2026-11-18' ) ).toBe(
			'Wed, Nov 18'
		);
		expect( spanLabel( '2026-11-08', '2026-11-14' ) ).toBe( 'Nov 8–14' );
		expect( spanLabel( '2026-11-29', '2026-12-05' ) ).toBe(
			'Nov 29 – Dec 5'
		);
	} );
} );
