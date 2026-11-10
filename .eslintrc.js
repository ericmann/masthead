module.exports = {
	extends: [ 'plugin:@wordpress/eslint-plugin/recommended' ],
	root: true,
	ignorePatterns: [ 'build/', 'dist/', 'vendor/', 'node_modules/' ],
	rules: {
		'@wordpress/i18n-text-domain': [ 'error', { allowedTextDomain: 'masthead' } ],
	},
};
