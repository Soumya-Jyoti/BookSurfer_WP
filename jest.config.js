/**
 * Jest configuration for the JS unit suite.
 *
 * BSWP-46. Extends the @wordpress/scripts preset so TS/JSX and the wp.* globals
 * work the same way they do in the build.
 *
 * @package BookSurfer
 */

const defaultConfig = require( '@wordpress/scripts/config/jest-unit.config' );

module.exports = {
	...defaultConfig,
	rootDir: __dirname,
	testMatch: [ '<rootDir>/tests/js/**/*.test.[jt]s?(x)' ],
	moduleNameMapper: {
		...( defaultConfig.moduleNameMapper || {} ),
		'^@shared/(.*)$': '<rootDir>/src/shared/$1',
	},
	moduleFileExtensions: [ 'ts', 'tsx', 'js', 'jsx', 'json' ],
	collectCoverageFrom: [ 'src/shared/**/*.{ts,tsx}' ],
};
