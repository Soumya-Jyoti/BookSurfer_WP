/**
 * Tailwind configuration — scoped so it cannot fight wp-admin.
 *
 * BSWP-27. Three defences, all mandatory:
 *   1. preflight: false  — Tailwind's global reset would wreck wp-admin and the
 *      editor canvas. This is the single biggest style-bleed risk.
 *   2. prefix: 'bs-'     — every utility is namespaced, so a generic class name
 *      can never collide with core or another plugin.
 *   3. important: '.booksurfer-root' — every rule is scoped under our wrapper, so
 *      our styles cannot escape it (and win inside it against admin resets).
 *
 * The front end ships ZERO Tailwind — the booking form is an iframe.
 *
 * @package
 */

/** @type {import('tailwindcss').Config} */
module.exports = {
	content: [ './src/**/*.{ts,tsx,js,jsx}' ],
	prefix: 'bs-',
	important: '.booksurfer-root',
	corePlugins: {
		preflight: false,
	},
	theme: {
		extend: {
			colors: {
				brand: {
					DEFAULT: '#0D9488',
					50: '#f0fdfa',
					100: '#ccfbf1',
					500: '#14b8a6',
					600: '#0d9488',
					700: '#0f766e',
				},
			},
		},
	},
	plugins: [],
};
