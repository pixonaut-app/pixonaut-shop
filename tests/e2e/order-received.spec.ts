import { expect, test } from '@playwright/test';
import { FAKE_FIELD_ROWS } from './support/fake-pixonaut';

/**
 * A guest places an order for the personalised product and lands on the
 * order received page (templates/order-confirmation.html): the band with the
 * order's number, the overview, and the order's details with the line's
 * personalisation and state as the fake plugin (`tests/e2e/fake-pixonaut.php`)
 * prints them. The global setup turns cheque payments on and shipping off
 * (`tests/Support/ShopFixtures.php`), so the checkout needs a billing
 * address only.
 */

/** The product the global setup creates. */
const PRODUCT_PATH = '/product/fake-pixonaut-product/';

const CHECKOUT_PATH = '/checkout/';

/** Where WooCommerce's checkout sends a customer once the order is placed. */
const ORDER_RECEIVED_URL = /\/order-received\/\d+\/\?key=/;

/** A billing address WooCommerce accepts for the store's default country. */
const BILLING = {
	email: 'guest@example.test',
	'billing-first_name': 'Alex',
	'billing-last_name': 'Doe',
	'billing-address_1': '1 Main Street',
	'billing-city': 'San Francisco',
	'billing-postcode': '94103',
} as const;

/** A line's field rows as they read: `label: value`. */
const FIELD_LINES = FAKE_FIELD_ROWS.map( ( [ label, value ] ) => `${ label }: ${ value }` );

// A guest, as most customers are: the order is theirs by its key alone.
test.use( { storageState: { cookies: [], origins: [] } } );

test( 'placing an order lands on the order received page with the band, overview and details', async ( {
	page,
} ) => {
	await page.goto( PRODUCT_PATH );
	await page.locator( '.pixonaut-approval__box' ).check();
	await page.locator( 'form.cart button[type="submit"]' ).click();
	await expect(
		page.locator( '.woocommerce-message, .wc-block-components-notice-banner' ).first(),
	).toBeVisible();

	await page.goto( CHECKOUT_PATH );

	// The checkout's summary lists the line's field rows as `label: value`
	// lines, one under the other.
	const summaryRows = page.locator(
		'.wc-block-components-order-summary-item .wc-block-components-product-details > *',
	);

	await expect( summaryRows ).toContainText( FIELD_LINES );

	const tops = await summaryRows.evaluateAll( ( rows ) =>
		rows.map( ( row ) => row.getBoundingClientRect().top ),
	);

	expect( tops ).toEqual( [ ...tops ].sort( ( a, b ) => a - b ) );
	expect( new Set( tops ).size, 'each row on a line of its own' ).toBe( tops.length );

	for ( const [ id, value ] of Object.entries( BILLING ) ) {
		await page.locator( `#${ id }` ).fill( value );
	}

	// The Checkout page holds the checkout pattern, whose terms are a box to tick.
	await page.locator( '#terms-and-conditions' ).check();
	await page.locator( '.wc-block-components-checkout-place-order-button' ).click();
	await page.waitForURL( ORDER_RECEIVED_URL );

	const band = page.locator( '.pixonaut-shop-order-received' );

	await expect( band.locator( '.pixonaut-shop-order-number' ) ).toHaveText(
		/^Order \d+ received$/,
	);
	await expect( band.locator( 'h1' ) ).toBeVisible();
	await expect( band.locator( '.wc-block-order-confirmation-summary-list' ) ).toBeVisible();

	const details = page.locator( '.pixonaut-shop-order-details' );

	await expect( details.locator( '.wc-block-order-confirmation-totals__table' ) ).toContainText(
		'Personalised tee',
	);
	await expect( details.locator( '.wc-item-meta li' ) ).toContainText( FIELD_LINES );
	await expect( details.locator( '.pixonaut-shop-line-state' ) ).toHaveText( 'Design approved' );
	await expect(
		page.locator( '.pixonaut-shop-order-actions .wp-block-button__link' ),
	).toBeVisible();
} );
