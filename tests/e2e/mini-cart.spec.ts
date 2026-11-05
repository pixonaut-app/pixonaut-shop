import { expect, type Page, test } from '@playwright/test';

/**
 * The header's bag opens WooCommerce's mini-cart drawer, whose contents are
 * the theme's `parts/mini-cart.html`: the lines, with the rows Pixonaut for
 * WooCommerce adds to a personalised one (printed here by the fake plugin),
 * the subtotal and the two buttons; or, empty, the bag, WooCommerce's
 * heading and its button back to the shop. WooCommerce rewords its buttons
 * between releases, so a button is found by where it leads.
 */

/** A guest of their own in every test, so no test sees another's cart. */
test.use( { storageState: { cookies: [], origins: [] } } );

/** The product the global setup creates (`tests/Support/ShopFixtures.php`), personalised by the fake. */
const PRODUCT_PATH = '/product/fake-pixonaut-product/';
const PRODUCT_NAME = 'Personalised tee';
const PRODUCT_PRICE = '249';

const SHOP_PATH = '/shop/';
const CART_PATH = '/cart/';
const CHECKOUT_PATH = '/checkout/';

const APPROVAL = '[data-fake-pixonaut="approval"] input[type="checkbox"]';

const BAG = '.wc-block-mini-cart__button';
const DRAWER = '.wc-block-mini-cart__drawer';

/** Adds the personalised product to the cart, approving its preview as the plugin asks. */
async function addProduct( page: Page ) {
	await page.goto( PRODUCT_PATH );
	await page.locator( APPROVAL ).check();
	await page.locator( 'form.cart button[type="submit"]' ).click();
	await page.waitForURL( PRODUCT_PATH );
}

/** Opens the drawer from the header's bag on the home page. */
async function openDrawer( page: Page ) {
	await page.goto( '/' );
	await page.locator( BAG ).click();

	const drawer = page.locator( DRAWER );
	await expect( drawer ).toBeVisible();

	return drawer;
}

test( 'adding a product and opening the drawer shows its line, the subtotal and the buttons', async ( {
	page,
	baseURL,
} ) => {
	await addProduct( page );

	const drawer = await openDrawer( page );
	const line = drawer.locator( '.wc-block-cart-items__row' );

	await expect( line ).toHaveCount( 1 );
	await expect( line.locator( '.wc-block-components-product-name' ).first() ).toContainText(
		PRODUCT_NAME,
	);
	await expect( line.locator( '.wc-block-cart-item__total' ) ).toContainText( PRODUCT_PRICE );
	await expect( line.getByRole( 'link', { name: 'View proof' } ) ).toBeVisible();
	await expect( line.getByRole( 'link', { name: 'Edit design' } ) ).toBeVisible();

	const footer = drawer.locator( '.wc-block-mini-cart__footer' );

	await expect( footer.locator( '.wc-block-mini-cart__footer-subtotal' ) ).toContainText(
		'Subtotal',
	);
	await expect( footer.locator( '.wc-block-mini-cart__footer-subtotal' ) ).toContainText(
		PRODUCT_PRICE,
	);
	await expect( footer.locator( `a[href="${ baseURL }${ CHECKOUT_PATH }"]` ) ).toBeVisible();
	await expect( footer.locator( `a[href="${ baseURL }${ CART_PATH }"]` ) ).toBeVisible();
} );

test( 'an empty drawer shows the bag, the empty heading and the way back to the shop', async ( {
	page,
	baseURL,
} ) => {
	const drawer = await openDrawer( page );
	const empty = drawer.locator( '.wc-block-mini-cart__empty-cart-wrapper' );

	await expect( empty.locator( '.wp-block-pixonaut-shop-icon.has-circle svg' ) ).toBeVisible();
	await expect( empty.getByRole( 'heading' ) ).toBeVisible();
	await expect( empty.locator( '.wc-block-mini-cart__shopping-button' ) ).toHaveAttribute(
		'href',
		`${ baseURL }${ SHOP_PATH }`,
	);
	await expect( drawer.locator( '.wc-block-cart-items__row' ) ).toHaveCount( 0 );
} );

/**
 * The note's text comes from the server, in the Store API cart under
 * `extensions.pixonaut-shop` (FreeShippingNoteTest covers it there). The
 * tests site ships nowhere, so these responses carry the text a store with a
 * free-shipping minimum would send, to check the drawer shows what each cart
 * update brings and hides the note while there is none.
 */
const NOTE_FIELD = 'free_shipping_note';

/** The cart, and the batch requests WooCommerce sends the drawer's cart updates in. */
const STORE_API_CART = /\/wc\/store\/v1\/(cart|batch)(\?|$)/;

type Cart = { extensions?: Record< string, unknown > };

/** A cart with the given note, as the theme adds it to the Store API's cart. */
function withNote( cart: Cart, note: string ): Cart {
	return { ...cart, extensions: { ...cart.extensions, 'pixonaut-shop': { [ NOTE_FIELD ]: note } } };
}

test( 'the free-shipping note follows each cart update in the open drawer', async ( { page } ) => {
	let note = '';

	await page.route( STORE_API_CART, async ( route ) => {
		const response = await route.fetch();
		const json = await response.json();
		const body = Array.isArray( json.responses )
			? {
					...json,
					responses: json.responses.map( ( item: { body: Cart } ) => ( {
						...item,
						body: withNote( item.body, note ),
					} ) ),
				}
			: withNote( json, note );

		await route.fulfill( { response, json: body } );
	} );

	await addProduct( page );

	const drawer = await openDrawer( page );
	const shown = drawer.locator( '.wp-block-pixonaut-shop-free-shipping-note' );
	const plus = drawer.locator( '.wc-block-components-quantity-selector__button--plus' );

	await expect( shown ).toBeHidden();

	note = 'Add $51.00 more for free shipping';
	await plus.click();
	await expect( shown ).toBeVisible();
	await expect( shown ).toHaveText( note );

	note = 'You get free shipping';
	await plus.click();
	await expect( shown ).toHaveText( note );

	note = '';
	await plus.click();
	await expect( shown ).toBeHidden();
} );
