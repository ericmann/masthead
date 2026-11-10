const defaultConfig = require( '@wordpress/scripts/config/jest-unit.config' );

module.exports = {
	...defaultConfig,
	transform: {
		...defaultConfig.transform,
		'\\.mjs$': require.resolve( '@wordpress/scripts/config/babel-transform' ),
	},
	testMatch: [ '<rootDir>/tests/js/**/*.test.js' ],
};
