const defaultConfig = require( '@wordpress/scripts/config/jest-unit.config' );

module.exports = {
	...defaultConfig,
	transform: {
		...defaultConfig.transform,
		'\\.mjs$':
			require.resolve( '@wordpress/scripts/config/babel-transform' ),
	},
	moduleNameMapper: {
		...( defaultConfig.moduleNameMapper || {} ),
		'^@wordpress/core-data$': '<rootDir>/tests/js/stubs/core-data.js',
	},
	setupFiles: [
		...( defaultConfig.setupFiles || [] ),
		'<rootDir>/tests/js/setup.js',
	],
	testMatch: [ '<rootDir>/tests/js/**/*.test.js' ],
};
