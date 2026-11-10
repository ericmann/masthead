// jsdom lacks TextEncoder and TextDecoder, which @wordpress/core-data needs on import.
const { TextEncoder, TextDecoder } = require( 'util' );

global.TextEncoder = global.TextEncoder || TextEncoder;
global.TextDecoder = global.TextDecoder || TextDecoder;
