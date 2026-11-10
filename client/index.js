import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';

import CalendarApp from './calendar/App';
import './style.scss';

/**
 * Root element id to app component. Later tasks add routes here.
 */
export const ROUTES = {
	'masthead-calendar-root': CalendarApp,
};

export function mountAll( doc = document ) {
	Object.entries( ROUTES ).forEach( ( [ id, Component ] ) => {
		const root = doc.getElementById( id );
		if ( root ) {
			createRoot( root ).render( <Component /> );
		}
	} );
}

domReady( () => mountAll() );
