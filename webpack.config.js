/**
 * WordPress webpack configuration for PodLoom blocks.
 */

const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		'store/index': path.resolve( __dirname, 'src/store/index.js' ),
		'episode-block/index': path.resolve( __dirname, 'src/episode-block/index.js' ),
		'subscribe-block/index': path.resolve( __dirname, 'src/subscribe-block/index.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'build' ),
	},
};
