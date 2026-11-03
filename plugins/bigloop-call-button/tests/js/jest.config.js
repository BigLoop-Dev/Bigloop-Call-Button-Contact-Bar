/**
 * Jest config. Self-contained (wp-scripts 37 no longer ships Jest defaults).
 * Uses the plugin's babel.config.js (classic JSX runtime).
 */
const path = require( 'path' );
const root = path.resolve( __dirname, '../..' );

module.exports = {
	rootDir: root,
	testEnvironment: 'jsdom',
	testMatch: [ '<rootDir>/tests/js/**/*.test.js' ],
	transform: {
		'^.+\\.[jt]sx?$': [ 'babel-jest', { configFile: path.join( root, 'babel.config.js' ) } ],
	},
	moduleNameMapper: {
		'^@bigloop/ui$': '<rootDir>/../../packages/ui/src',
		'\\.(css|scss)$': '<rootDir>/tests/js/style-mock.js',
	},
	setupFiles: [ '<rootDir>/tests/js/setup.js' ],
};
