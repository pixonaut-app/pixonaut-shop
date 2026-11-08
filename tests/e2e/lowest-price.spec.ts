import { expect, test } from '@playwright/test';

import { TEST_SUPPORT, wpEval } from './support/wp-cli';

/**
 * The lowest price of the 30 days before a reduction
 * (`inc/Product/LowestPrice.php`), beside the reduced price everywhere a
 * shopper sees it: the product page's price row, the product's card in its
 * category, and its line in the cart. The fixture's product is reduced from
 * its regular price, with a history in which it cost less than that before
 * the reduction (`ShopFixtures::reduced_product()`). Its words are found by
 * their English source strings.
 */

/** Every test is a visitor with a cart of their own, not the shared admin's. */
test.use( { storageState: { cookies: [], origins: [] } } );

const LABEL = 'Lowest price in the last 30 days';
const NOTE = '.pixonaut-shop-lowest-price';
const PRICE_ROW = '.pixonaut-shop-buy-row .pixonaut-shop-price';
const CARD = '.wc-block-product';
const CART_PATH = '/cart/';
const CART_LINE = '.wc-block-cart-items__row';
const DETAIL_ROW = '.wc-block-components-product-details > *';

let product: { path: string; categoryPath: string; lowestPrice: string };

test.beforeAll( () => {
	const [ slug, category, lowestPrice ] = wpEval(
		`${ TEST_SUPPORT }ShopFixtures::reduced_product(); ` +
			[ 'REDUCED_PRODUCT_SLUG', 'REDUCED_CATEGORY_SLUG', 'REDUCED_LOWEST_PRICE' ]
				.map( ( name ) => `echo ${ TEST_SUPPORT }ShopFixtures::${ name }, "\\n";` )
				.join( ' ' ),
	).split( '\n' );
	product = {
		path: `/product/${ slug }/`,
		categoryPath: `/product-category/${ category }/`,
		lowestPrice: lowestPrice,
	};
} );

test.afterAll( () => {
	wpEval( `${ TEST_SUPPORT }ShopFixtures::forget_reduced_product();` );
} );

test( 'the product page shows the lowest price under the reduced price', async ( { page } ) => {
	await page.goto( product.path );

	const note = page.locator( `${ PRICE_ROW } ${ NOTE }` );

	await expect( note ).toBeVisible();
	await expect( note ).toContainText( `${ LABEL }: ` );
	await expect( note ).toContainText( product.lowestPrice );
} );

test( "the product's card shows the lowest price under the reduced price", async ( { page } ) => {
	await page.goto( product.categoryPath );

	const note = page.locator( `main ${ CARD } ${ NOTE }` );

	await expect( note ).toHaveCount( 1 );
	await expect( note ).toBeVisible();
	await expect( note ).toContainText( product.lowestPrice );
} );

test( "the product's cart line shows the lowest price among its details", async ( { page } ) => {
	await page.goto( product.path );
	await page.locator( 'form.cart button[type="submit"]' ).click();
	await page.goto( CART_PATH );

	const row = page.locator( `${ CART_LINE } ${ DETAIL_ROW }` ).filter( { hasText: LABEL } );

	await expect( row ).toBeVisible();
	await expect( row ).toContainText( product.lowestPrice );
} );
