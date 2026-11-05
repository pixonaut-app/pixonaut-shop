import { expect, test } from '@playwright/test';

/**
 * The shop template (`templates/archive-product.html`), which the category
 * and tag archives fall back to: a category's products with their count and
 * sorting, or, for a category without any, a panel that offers the product
 * search and the way back to the shop, and the popular products below it.
 * Its own words are found here by their English source strings.
 */

/** The categories the global setup makes (`tests/Support/ShopFixtures.php`). */
const CATEGORY_PATH = '/product-category/personalised-garments/';
const EMPTY_CATEGORY_PATH = '/product-category/empty-category/';

const CARD = '.wc-block-product';
const RESULTS_COUNT = '.woocommerce-result-count';
const SORTING = 'select.orderby';
const ADD_TO_CART = '.add_to_cart_button';
const NO_PRODUCTS = '.pixonaut-shop-no-products';
const PRODUCT_SEARCH = 'input[type="search"]';
const POPULAR = '.pixonaut-shop-archive-popular';

test( 'a category with products shows them in the grid, with their count and sorting', async ( {
	page,
} ) => {
	await page.goto( CATEGORY_PATH );

	const main = page.locator( 'main' );

	await expect( main.locator( CARD ).first() ).toBeVisible();
	await expect( main.locator( RESULTS_COUNT ) ).toBeVisible();
	await expect( main.locator( SORTING ) ).toBeVisible();
	await expect( main.locator( ADD_TO_CART ) ).toHaveCount( 0 );
	await expect( main.locator( NO_PRODUCTS ) ).toHaveCount( 0 );
	await expect( main.locator( POPULAR ) ).toHaveCount( 0 );
} );

test( 'a category without products offers the search, the shop and the popular products', async ( {
	page,
	baseURL,
} ) => {
	await page.goto( EMPTY_CATEGORY_PATH );

	const panel = page.locator( `main ${ NO_PRODUCTS }` );

	await expect( panel ).toBeVisible();
	await expect( panel.locator( PRODUCT_SEARCH ) ).toBeVisible();
	await expect( panel.getByRole( 'link', { name: 'Back to the shop' } ) ).toHaveAttribute(
		'href',
		`${ baseURL }/shop/`,
	);

	const popular = page.locator( `main ${ POPULAR }` );

	await expect( popular.getByRole( 'heading', { name: 'Popular right now' } ) ).toBeVisible();
	await expect( popular.locator( CARD ).first() ).toBeVisible();
} );
