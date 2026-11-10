import { render, screen } from '@testing-library/react';

import Header, { rhythmSummary } from '../../../client/calendar/Header';

const rhythm = [
	{ label: 'Faith' },
	{ label: 'Business & leadership' },
	{ label: 'Technical series' },
	{ label: 'Standalone' },
	{ label: 'Technical series' },
	{ label: 'Journal & build log' },
	{ label: 'Fiction serial' },
];

describe( 'rhythmSummary', () => {
	it( 'joins weekdays that share a lane and starts at the week start', () => {
		expect( rhythmSummary( rhythm, 0 ) ).toBe(
			'Sun Faith · Mon Business & leadership · Tue/Thu Technical series · Wed Standalone · Fri Journal & build log · Sat Fiction serial'
		);
		expect( rhythmSummary( rhythm, 1 ).startsWith( 'Mon Business' ) ).toBe(
			true
		);
		expect( rhythmSummary( rhythm, 1 ).endsWith( 'Sun Faith' ) ).toBe(
			true
		);
	} );

	it( 'is empty without a rhythm', () => {
		expect( rhythmSummary( [], 0 ) ).toBe( '' );
		expect( rhythmSummary( undefined, 0 ) ).toBe( '' );
	} );
} );

describe( 'Header', () => {
	it( 'shows the title, Add New Post and the rhythm line', () => {
		render(
			<Header
				newPostUrl="http://example.test/wp-admin/post-new.php"
				rhythm={ rhythm }
				weekStartsOn={ 0 }
			/>
		);
		expect(
			screen.getByRole( 'heading', { level: 1, name: 'Calendar' } )
		).toBeTruthy();
		expect(
			screen
				.getByRole( 'link', { name: 'Add New Post' } )
				.getAttribute( 'href' )
		).toBe( 'http://example.test/wp-admin/post-new.php' );
		expect( screen.getByText( /^Rhythm: Sun Faith · Mon / ) ).toBeTruthy();
	} );

	it( 'leaves out Add New Post without a URL and the rhythm line without lanes', () => {
		const { container } = render(
			<Header newPostUrl="" rhythm={ [] } weekStartsOn={ 0 } />
		);
		expect( screen.queryByRole( 'link' ) ).toBeNull();
		expect( container.querySelector( '.masthead-header__rhythm' ) ).toBe(
			null
		);
	} );
} );
