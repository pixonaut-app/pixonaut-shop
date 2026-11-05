import { expect, test } from '@playwright/test';

/**
 * The product search's template (`templates/product-search-results.html`):
 * the query again in its panel and what matched it, or, when nothing did, a
 * notice that names the query, the store's categories as buttons and the
 * popular products. Its own words are found here by their English source
 * strings.
 */

/** The products the global setup makes (`tests/Support/ShopFixtures.php`) are both tees. */
const MATCHING_QUERY = 'tee';
const UNMATCHED_QUERY = 'nothingmatchesthis';

/** A query a page would run as markup if it printed it unescaped. */
const MARKUP_QUERY = '<b class="injected">bold</b>';

/** The category the fixtures' products are in. */
const CATEGORY = 'Personalised garments';

const CARD = '.wc-block-product';
const RESULTS_COUNT = '.woocommerce-result-count';
const SORTING = 'select.orderby';
const SEARCH_FIELD = '.pixonaut-shop-search-panel input[type="search"]';
const NO_RESULTS = '.pixonaut-shop-search-no-results';
const POPULAR = '.pixonaut-shop-archive-popular';

function productSearch( query: string ): string {
	return `/?${ new URLSearchParams( { s: query, post_type: 'product' } ) }`;
}

test( 'a matching query lists its products, with their count and sorting', async ( { page } ) => {
	await page.goto( productSearch( MATCHING_QUERY ) );

	const main = page.locator( 'main' );

	await expect( main.getByRole( 'heading', { level: 1, name: 'Search results' } ) ).toBeVisible();
	await expect( main.locator( SEARCH_FIELD ) ).toHaveValue( MATCHING_QUERY );
	await expect( main.locator( CARD ).first() ).toBeVisible();
	await expect( main.locator( RESULTS_COUNT ) ).toBeVisible();
	await expect( main.locator( SORTING ) ).toBeVisible();
	await expect( main.locator( NO_RESULTS ) ).toHaveCount( 0 );
	await expect( main.locator( POPULAR ) ).toHaveCount( 0 );
} );

test( 'a query without a match names the query and offers the categories and the popular products', async ( {
	page,
} ) => {
	await page.goto( productSearch( UNMATCHED_QUERY ) );

	const noResults = page.locator( `main ${ NO_RESULTS }` );

	await expect(
		noResults.getByRole( 'heading', { name: `No results for “${ UNMATCHED_QUERY }”` } ),
	).toBeVisible();
	await expect( noResults.getByRole( 'link', { name: CATEGORY } ) ).toBeVisible();

	const popular = page.locator( `main ${ POPULAR }` );

	await expect( popular.getByRole( 'heading', { name: 'Popular right now' } ) ).toBeVisible();
	await expect( popular.locator( CARD ).first() ).toBeVisible();
} );

test( 'a query containing markup is shown as text', async ( { page } ) => {
	await page.goto( productSearch( MARKUP_QUERY ) );

	const main = page.locator( 'main' );

	await expect(
		main.getByRole( 'heading', { name: `No results for “${ MARKUP_QUERY }”` } ),
	).toBeVisible();
	await expect( main.locator( SEARCH_FIELD ) ).toHaveValue( MARKUP_QUERY );
	await expect( page.locator( '.injected' ) ).toHaveCount( 0 );
} );
