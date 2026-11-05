import { defineConfig, devices } from '@playwright/test';

/**
 * wp-env's tests site port from .wp-env.json, or `WP_ENV_TESTS_PORT` per
 * checkout. The suite runs on the tests site, which always runs the fake
 * Pixonaut for WooCommerce; the development site runs whichever build of the
 * real plugin a developer chose.
 */
const DEFAULT_WP_ENV_TESTS_PORT = '8852';

const port = process.env.WP_ENV_TESTS_PORT ?? DEFAULT_WP_ENV_TESTS_PORT;
const baseURL = `http://localhost:${ port }`;

// `@wordpress/e2e-test-utils-playwright`'s internals (REST root discovery in
// `RequestUtils.setupRest`) read this variable rather than Playwright's own
// config, so it must name this checkout's site or they fall back to the
// package's baked-in default.
process.env.WP_BASE_URL ??= baseURL;

// The `requestUtils` fixture logs in through the REST API and saves the
// session cookie here; pointing `use.storageState` at the same file is what
// makes the `page` fixture, and anything that opens wp-admin, signed in too.
const STORAGE_STATE_PATH = `${ process.cwd() }/artifacts/storage-states/admin.json`;
process.env.STORAGE_STATE_PATH ??= STORAGE_STATE_PATH;

export default defineConfig( {
	testDir: './tests/e2e',
	globalSetup: './tests/e2e/global-setup.ts',
	timeout: 30_000,
	expect: { timeout: 5_000 },
	fullyParallel: true,
	forbidOnly: !! process.env.CI,
	retries: process.env.CI ? 1 : 0,
	reporter: process.env.CI ? 'github' : 'list',
	use: {
		baseURL,
		storageState: STORAGE_STATE_PATH,
		trace: 'on-first-retry',
	},
	projects: [
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
	],
} );
