import { execFileSync } from 'node:child_process';

/** Where the PHP test support called through `wpEval` lives. */
export const TEST_SUPPORT = 'Pixonaut\\Shop\\Tests\\Support\\';

/**
 * Runs PHP on the tests site, which the suite visits, through wp-env's
 * tests WP-CLI container, and
 * returns what it printed. For the test support that lives on the site
 * itself: the PHP knows the names of what it reads and writes, so no name is
 * repeated on this side.
 */
export function wpEval( php: string ): string {
	return execFileSync( 'pnpm', [ '--silent', 'env:cli-tests', 'wp', 'eval', php ], {
		stdio: 'pipe',
	} )
		.toString()
		.trim();
}
