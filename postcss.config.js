/**
 * PostCSS chain consumed by @wordpress/scripts.
 *
 * BSWP-39/27. Replaces the Framer plugin's @tailwindcss/vite plugin.
 *
 * @package
 */

module.exports = {
	plugins: {
		tailwindcss: {},
		autoprefixer: {},
	},
};
