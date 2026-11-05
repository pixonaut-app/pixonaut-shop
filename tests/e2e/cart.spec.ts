import { expect, type Locator, type Page, test } from '@playwright/test';
import { FAKE_FIELD_ROWS, FAKE_SIDE } from './support/fake-pixonaut';

/**
 * The cart template (`templates/page-cart.html`) around the store's Cart
 * page, which holds WooCommerce's Cart block. A personalised line lists the
 * rows the plugin adds through `woocommerce_get_item_data`, printed here by
 * the fake plugin (`tests/e2e/fake-pixonaut.php`): the side with its proof,
 * the fields the customer filled in, and the edit link after them. Its own
 * words, and WooCommerce's, are found by their English source strings.
 */

/** Every test is a visitor with a cart of their own, not the shared admin's. */
test.use( { storageState: { cookies: [], origins: [] } } );

/** The product the global setup creates (`tests/Support/ShopFixtures.php`). */
const PRODUCT_PATH = '/product/fake-pixonaut-product/';
const CART_PATH = '/cart/';
const SHOP_PATH = '/shop/';

const LINE = '.wc-block-cart-items__row';
const LINES_PANEL = '.wc-block-cart-items';
const TOTALS_PANEL = '.wc-block-cart__sidebar';
const DETAIL_ROW = '.wc-block-components-product-details > *';
const DETAIL_NAME = '.wc-block-components-product-details__name';
const PROOF_LINK = 'a.pixonaut-proof-link';
const EDIT_LINK = 'a.pixonaut-edit-design';
const PRODUCT_CELL = '.wc-block-cart-item__product';
const TOTAL_CELL = '.wc-block-cart-item__total';
const EMPTY_CART = '.wp-block-woocommerce-empty-cart-block';

/**
 * Widths where WooCommerce lays the lines out as its own narrow grid: a
 * tablet, where the cart is too narrow for the totals beside the lines, and
 * a phone.
 */
const TABLET = { width: 900, height: 1000 };
const PHONE = { width: 390, height: 844 };

/** Adds the fake's personalised product to this visitor's cart, as a customer does. */
async function addPersonalisedLine( page: Page ): Promise< void > {
	await page.goto( PRODUCT_PATH );
	await page.getByLabel( 'I approve the preview' ).check();
	await page.locator( 'form.cart button[type="submit"]' ).click();
}

/** A locator's box, which a visible element always has. */
async function box(
	locator: Locator,
): Promise< { x: number; y: number; width: number; height: number } > {
	const found = await locator.boundingBox();

	expect( found ).not.toBeNull();

	return found as NonNullable< typeof found >;
}
const EMPTY_TITLE = '.wc-block-cart__empty-cart__title';

test( 'the cart has a title row with the way back to the shop', async ( { page, baseURL } ) => {
	await page.goto( CART_PATH );

	const main = page.locator( 'main' );

	await expect( main.getByRole( 'heading', { level: 1, name: 'Cart' } ) ).toBeVisible();
	await expect( main.getByRole( 'link', { name: 'Continue shopping' } ) ).toHaveAttribute(
		'href',
		`${ baseURL }${ SHOP_PATH }`,
	);
} );

test( 'a personalised line shows its side, its fields and the edit link after them, in panels', async ( {
	page,
} ) => {
	await addPersonalisedLine( page );
	await page.goto( CART_PATH );

	const line = page.locator( LINE ).first();
	const rows = line.locator( DETAIL_ROW );

	await expect( rows ).toHaveCount( 1 + FAKE_FIELD_ROWS.length );
	await expect( rows.first().locator( DETAIL_NAME ) ).toHaveText( `${ FAKE_SIDE }:` );
	await expect( rows.first().locator( PROOF_LINK ) ).toBeVisible();

	for ( const [ index, [ label, value ] ] of FAKE_FIELD_ROWS.entries() ) {
		await expect( rows.nth( index + 1 ) ).toContainText( `${ label }: ${ value }` );
	}

	const editLink = rows.last().locator( EDIT_LINK );
	const lastValue = rows.last().locator( DETAIL_NAME );

	await expect( editLink ).toBeVisible();
	// The edit link sits on a line of its own, below the last field.
	expect( ( await editLink.boundingBox() )?.y ).toBeGreaterThan(
		( await lastValue.boundingBox() )?.y ?? Infinity,
	);

	for ( const panel of [ LINES_PANEL, TOTALS_PANEL ] ) {
		await expect( page.locator( panel ) ).not.toHaveCSS( 'box-shadow', 'none' );
	}
} );

test( 'on a tablet the totals go below the lines, and a line keeps its total beside its details', async ( {
	page,
} ) => {
	await page.setViewportSize( TABLET );
	await addPersonalisedLine( page );
	await page.goto( CART_PATH );

	const line = page.locator( LINE ).first();

	await expect( line.locator( PRODUCT_CELL ) ).toBeVisible();

	const lines = await box( page.locator( LINES_PANEL ) );
	const totals = await box( page.locator( TOTALS_PANEL ) );
	const product = await box( line.locator( PRODUCT_CELL ) );
	const total = await box( line.locator( TOTAL_CELL ) );

	expect( totals.y ).toBeGreaterThanOrEqual( lines.y + lines.height );
	expect( product.x + product.width ).toBeLessThanOrEqual( total.x );
} );

test( 'on a phone a line is its picture and its details, inside the panel', async ( { page } ) => {
	await page.setViewportSize( PHONE );
	await addPersonalisedLine( page );
	await page.goto( CART_PATH );

	const line = page.locator( LINE ).first();

	await expect( line.locator( PRODUCT_CELL ) ).toBeVisible();
	await expect( line.locator( TOTAL_CELL ) ).toBeHidden();

	const row = await box( line );
	const product = await box( line.locator( PRODUCT_CELL ) );

	expect( product.x + product.width ).toBeLessThanOrEqual( row.x + row.width );
} );

test( 'an empty cart shows its empty state, with the way back to the shop', async ( {
	page,
	baseURL,
} ) => {
	await page.goto( CART_PATH );

	const empty = page.locator( EMPTY_CART );

	const title = empty.locator( EMPTY_TITLE );

	await expect( title ).toBeVisible();
	// The bag's tint circle, which the theme draws above WooCommerce's heading.
	expect(
		await title.evaluate( ( heading ) => getComputedStyle( heading, '::before' ).borderRadius ),
	).toBe( '50%' );
	// WooCommerce words this link differently from one release to the next; where it leads is what counts.
	await expect( empty.locator( `a[href="${ baseURL }${ SHOP_PATH }"]` ) ).toBeVisible();
	await expect( empty.locator( LINE ) ).toHaveCount( 0 );
} );
