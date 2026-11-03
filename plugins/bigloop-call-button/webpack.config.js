/**
 * Build config: extends @wordpress/scripts.
 *
 * JSX is compiled with the classic runtime (React.createElement) and `React` is provided from the
 * WordPress-bundled `react` script, so the bundle works on WordPress 6.5 (which lacks the
 * `react-jsx-runtime` script handle introduced in 6.6).
 */
const path = require( 'path' );
const webpack = require( 'webpack' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		admin: path.resolve( __dirname, 'assets/src/admin/index.js' ),
		frontend: path.resolve( __dirname, 'assets/src/frontend/index.js' ),
		block: path.resolve( __dirname, 'assets/src/block/index.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'assets/build' ),
	},
	resolve: {
		...defaultConfig.resolve,
		alias: {
			...( defaultConfig.resolve && defaultConfig.resolve.alias ),
			'@bigloop/ui': path.resolve( __dirname, '../../packages/ui/src' ),
		},
	},
	plugins: [
		...defaultConfig.plugins,
		new webpack.ProvidePlugin( { React: 'react' } ),
	],
};
