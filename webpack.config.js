/**
 * Build configuration.
 *
 * BSWP-39. Replaces Vite. Two entry points (block + admin) sharing a src/shared/
 * chunk. React is NOT bundled — @wordpress/scripts' DependencyExtractionWebpackPlugin
 * maps @wordpress/element to the wp.element global and emits an *.asset.php per
 * entry listing the real script dependencies and a content hash for cache busting.
 *
 * @package
 */

const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const CopyWebpackPlugin = require( 'copy-webpack-plugin' );

module.exports = {
	...defaultConfig,
	entry: {
		'block/index': path.resolve( __dirname, 'src/block/index.tsx' ),
		'admin/index': path.resolve( __dirname, 'src/admin/index.tsx' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'build' ),
		filename: '[name].js',
	},
	// Deliberately NOT splitting src/shared/ into its own chunk. The two entries are
	// never loaded on the same screen (admin settings vs. block editor), so a shared
	// chunk saves nothing — and WordPress enqueues the entry handles from their
	// *.asset.php files, so an extra chunk would simply never be loaded and the
	// bundle would break at runtime. Each entry inlines what it uses.
	plugins: [
		...defaultConfig.plugins,
		new CopyWebpackPlugin( {
			patterns: [
				{
					from: path.resolve( __dirname, 'src/block/block.json' ),
					to: path.resolve( __dirname, 'build/block/block.json' ),
				},
			],
		} ),
	],
	resolve: {
		...defaultConfig.resolve,
		extensions: [ '.ts', '.tsx', '.js', '.jsx', '.json' ],
		alias: {
			...( defaultConfig.resolve?.alias || {} ),
			'@shared': path.resolve( __dirname, 'src/shared' ),
		},
	},
};
