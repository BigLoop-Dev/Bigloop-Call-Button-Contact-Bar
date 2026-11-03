/**
 * Babel config. Classic JSX runtime for WordPress 6.5 compatibility (see webpack.config.js).
 * Plugins run before presets, so this transform handles JSX before the preset's automatic runtime would.
 */
module.exports = ( api ) => {
	api.cache( true );
	return {
		presets: [ require.resolve( '@wordpress/babel-preset-default' ) ],
		plugins: [
			[
				require.resolve( '@babel/plugin-transform-react-jsx' ),
				{ runtime: 'classic' },
			],
		],
	};
};
