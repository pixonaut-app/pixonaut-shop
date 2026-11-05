import type { Page } from '@playwright/test';
import type { Admin } from '@wordpress/e2e-test-utils-playwright';

/** The REST route the block editor loads registered patterns from. */
const PATTERNS_ROUTE = '/wp/v2/block-patterns/patterns';

/**
 * Opens a post in the block editor and waits for its registered patterns.
 *
 * A Pattern block shows nothing until the editor has fetched the patterns,
 * which renders every pattern on the server and can take longer than an
 * assertion's timeout on a slow machine. Waiting for the response itself
 * leaves the assertions that follow only the canvas to wait for.
 */
export async function editPostWithPatterns(
	admin: Admin,
	page: Page,
	id: number,
): Promise< void > {
	const patterns = page.waitForResponse(
		( response ) =>
			decodeURIComponent( response.url() ).includes( PATTERNS_ROUTE ) && response.ok(),
	);

	await admin.editPost( id );
	await patterns;
}
