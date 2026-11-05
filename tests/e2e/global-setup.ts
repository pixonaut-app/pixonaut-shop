import type { FullConfig } from '@playwright/test';
import { RequestUtils } from '@wordpress/e2e-test-utils-playwright';

import { TEST_SUPPORT, wpEval } from './support/wp-cli';

/** wp-env's default admin credentials, confirmed with `pnpm env:cli user list`. */
const WP_ADMIN_USERNAME = 'admin';
const WP_ADMIN_PASSWORD = 'password';

/**
 * Logs in once before the suite runs and persists the session cookie to
 * `STORAGE_STATE_PATH` (set in playwright.config.ts), so every test's `page`
 * starts already authenticated. Then prepares the shop the specs visit: its
 * product, and WooCommerce out of its coming-soon mode and setup wizard
 * (`tests/Support/ShopFixtures.php`).
 */
export default async function globalSetup( config: FullConfig ): Promise< void > {
	const [ project ] = config.projects;
	const baseURL = project.use.baseURL as string;

	const requestUtils = await RequestUtils.setup( {
		user: { username: WP_ADMIN_USERNAME, password: WP_ADMIN_PASSWORD },
		storageStatePath: process.env.STORAGE_STATE_PATH,
		baseURL,
	} );

	await requestUtils.setupRest();

	wpEval( `${ TEST_SUPPORT }ShopFixtures::prepare();` );
}
