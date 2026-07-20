/**
 * Playwright configuration.
 *
 * BSWP-47. Runs against wp-env with the BSWP-5 stub standing in for the SaaS, so
 * the suite makes no real network calls and can drive the negative paths
 * deterministically.
 *
 * @package
 */

import { defineConfig, devices } from '@playwright/test';

const WP_BASE_URL = process.env.WP_BASE_URL || 'http://localhost:8888';
const STUB_URL = process.env.BOOKSURFER_STUB_URL || 'http://127.0.0.1:5199';

export default defineConfig( {
	testDir: './tests/e2e',
	outputDir: './test-results',
	fullyParallel: false, // One connection per site: tests share global plugin state.
	workers: 1,
	forbidOnly: !! process.env.CI,
	retries: process.env.CI ? 1 : 0,
	reporter: process.env.CI ? [ [ 'html' ], [ 'list' ] ] : 'list',
	timeout: 60000,
	expect: { timeout: 10000 },
	use: {
		baseURL: WP_BASE_URL,
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		video: 'retain-on-failure',
		...devices[ 'Desktop Chrome' ],
	},
	metadata: { stubUrl: STUB_URL },
	projects: [
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
	],
} );
