import { render, screen } from '@testing-library/react';
import App from '../../../client/calendar/App';

describe( 'calendar App', () => {
	it( 'renders the Calendar heading', () => {
		render( <App /> );
		expect(
			screen.getByRole( 'heading', { name: 'Calendar' } )
		).toBeTruthy();
	} );
} );
