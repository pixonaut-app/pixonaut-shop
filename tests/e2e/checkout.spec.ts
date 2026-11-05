import { expect, type Locator, type Page, test } from '@playwright/test';

/**
 * The checkout template (`templates/page-checkout.html`) around the store's
 * Checkout page, which holds the checkout pattern's content
 * (`patterns/checkout.php`, applied by `tests/Support/ShopFixtures.php`):
 * WooCommerce's Checkout block with its step numbers and a terms box. The
 * tests site ships nothing, so the form asks for contact, billing and
 * payment only. Words are found by their English source strings. A second
 * page holds the same content with the step numbers off, as WooCommerce's
 * default Checkout block has them.
 */

/** Every test is a visitor with a cart of their own, not the shared admin's. */
test.use( { storageState: { cookies: [], origins: [] } } );

/** The product the global setup creates (`tests/Support/ShopFixtures.php`). */
const PRODUCT_PATH = '/product/fake-pixonaut-product/';
const CHECKOUT_PATH = '/checkout/';
/** The page the global setup fills with the checkout pattern, its step numbers off. */
const CHECKOUT_WITHOUT_STEP_NUMBERS_PATH = '/checkout-without-step-numbers/';

const STEP = '.wc-block-components-checkout-step--with-step-number';
/** Any step of the form, numbered or not; the order note belongs to the last panel. */
const ANY_STEP = '.wc-block-components-checkout-step:not(.wc-block-checkout__order-notes)';
const ACTIONS_ROW = '.wc-block-checkout__actions_row';
const STEP_TITLE = '.wc-block-components-checkout-step__title';
const SIDEBAR = '.wc-block-checkout__sidebar';
const MAIN = '.wc-block-checkout__main';
/** The whole summary WooCommerce prints in the place-order area on a narrow block. */
const SUMMARY_BELOW = '.checkout-order-summary-block-fill-wrapper';
const PLACE_ORDER = '.wc-block-components-checkout-place-order-button';
const TERMS = '.wc-block-checkout__terms .wc-block-components-checkbox';
const NOTICE = '.wc-block-components-notice-banner.is-error';
const DETAIL_NAME = '.wc-block-components-product-details__name';

/** The side the fake plugin lists first among a personalised line's rows. */
const SIDE = 'Front';

/** The steps the tests site's checkout shows, in order. */
const STEPS = [ 'Contact information', 'Billing address', 'Payment options' ];

const DESKTOP = { width: 1440, height: 900 };
const PHONE = { width: 390, height: 844 };

/** A country whose postcodes WooCommerce checks: five digits. */
const COUNTRY = 'SE';
const BAD_EMAIL = 'not-an-email';
const BAD_POSTCODE = '4113';

/**
 * An address WooCommerce's form accepts, with an email address the browser
 * accepts and WordPress refuses for its domain without a dot, so the order
 * comes back with a notice.
 */
const REFUSED_ORDER = {
	email: 'customer@example',
	'billing-first_name': 'Alex',
	'billing-last_name': 'Example',
	'billing-address_1': '1 Example Street',
	'billing-postcode': '411 13',
	'billing-city': 'Example City',
};

/** Adds the fake's personalised product to this visitor's cart and opens a checkout page. */
async function openCheckout( page: Page, path = CHECKOUT_PATH ): Promise< void > {
	await page.goto( PRODUCT_PATH );
	await page.getByLabel( 'I approve the preview' ).check();
	await page.locator( 'form.cart button[type="submit"]' ).click();
	await expect(
		page.locator( '.woocommerce-message, .wc-block-components-notice-banner' ).first(),
	).toBeVisible();
	await page.goto( path );
	await expect( page.locator( PLACE_ORDER ) ).toBeVisible();
}

/** A locator's box, which a visible element always has. */
async function box(
	locator: Locator,
): Promise< { x: number; y: number; width: number; height: number } > {
	const found = await locator.boundingBox();

	expect( found ).not.toBeNull();

	return found as NonNullable< typeof found >;
}

/** The colour a theme.json preset resolves to on this page, as computed styles print colours. */
async function presetColour( page: Page, slug: string ): Promise< string > {
	return page.evaluate( ( name ) => {
		const probe = document.createElement( 'span' );
		probe.style.color = `var(--wp--preset--color--${ name })`;
		document.body.append( probe );
		const colour = getComputedStyle( probe ).color;
		probe.remove();

		return colour;
	}, slug );
}

test( 'the checkout has a title row with the way back to the cart', async ( { page, baseURL } ) => {
	await openCheckout( page );

	const main = page.locator( 'main' );

	await expect( main.getByRole( 'heading', { level: 1, name: 'Checkout' } ) ).toBeVisible();
	await expect( main.getByRole( 'link', { name: 'Return to Cart' } ) ).toHaveAttribute(
		'href',
		`${ baseURL }/cart/`,
	);
} );

test( 'each step is a panel with its number in a badge before its title', async ( { page } ) => {
	await page.setViewportSize( DESKTOP );
	await openCheckout( page );

	const steps = page.locator( STEP );

	await expect( steps ).toHaveCount( STEPS.length );

	for ( const [ index, title ] of STEPS.entries() ) {
		const step = steps.nth( index );
		const heading = step.locator( STEP_TITLE );

		await expect( heading ).toHaveText( title );
		await expect( step ).not.toHaveCSS( 'box-shadow', 'none' );

		const badge = await heading.evaluate( ( element ) => {
			const style = getComputedStyle( element, '::before' );

			return { content: style.content, radius: style.borderRadius, position: style.position };
		} );

		// The counter's own text is not in computed styles; the badge is round and in the title's line.
		expect( badge.content ).toContain( 'counter(checkout-step)' );
		expect( badge.radius ).toBe( '50%' );
		expect( badge.position ).toBe( 'static' );
	}

	await expect( page.locator( '.pixonaut-shop-secure-payment' ) ).toHaveText( 'Secure payment' );
} );

test( 'with the step numbers off each step and the place-order row are still panels', async ( {
	page,
} ) => {
	await page.setViewportSize( DESKTOP );
	await openCheckout( page, CHECKOUT_WITHOUT_STEP_NUMBERS_PATH );

	const steps = page.locator( ANY_STEP );

	await expect( page.locator( STEP ) ).toHaveCount( 0 );
	await expect( steps ).toHaveCount( STEPS.length );

	for ( const index of STEPS.keys() ) {
		await expect( steps.nth( index ) ).not.toHaveCSS( 'box-shadow', 'none' );
	}

	await expect( page.locator( ACTIONS_ROW ) ).not.toHaveCSS( 'box-shadow', 'none' );
} );

test( 'on a wide screen the summary is a sticky panel beside the form', async ( { page } ) => {
	await page.setViewportSize( DESKTOP );
	await openCheckout( page );

	const sidebar = page.locator( SIDEBAR );

	await expect( sidebar ).toHaveCSS( 'position', 'sticky' );
	// The line's personalisation, from the rows the plugin adds.
	await expect( sidebar.locator( DETAIL_NAME ).first() ).toHaveText( `${ SIDE }:` );

	const main = await box( page.locator( MAIN ) );
	const summary = await box( sidebar );

	expect( summary.x ).toBeGreaterThanOrEqual( main.x + main.width );
	expect( summary.y ).toBeLessThan( main.y + main.height );
} );

test( 'on a phone the whole summary is a panel below the form', async ( { page } ) => {
	await page.setViewportSize( PHONE );
	await openCheckout( page );

	const below = page.locator( SUMMARY_BELOW );

	await expect( below ).toBeVisible();
	await expect( below ).not.toHaveCSS( 'box-shadow', 'none' );

	const button = await box( page.locator( PLACE_ORDER ) );
	const summary = await box( below );

	expect( summary.y ).toBeGreaterThanOrEqual( button.y + button.height );
} );

test( 'a wrong email and postcode, and no terms ticked, are marked under each', async ( {
	page,
} ) => {
	await page.setViewportSize( DESKTOP );
	await openCheckout( page );

	await page.locator( '#billing-country' ).selectOption( COUNTRY );
	await page.locator( '#email' ).fill( BAD_EMAIL );
	await page.locator( '#billing-postcode' ).fill( BAD_POSTCODE );
	await page.locator( PLACE_ORDER ).click();

	const error = await presetColour( page, 'error' );

	for ( const id of [ 'email', 'billing-postcode' ] ) {
		const field = page.locator( `#${ id }` );

		await expect( field ).toHaveAttribute( 'aria-invalid', 'true' );
		await expect( field ).toHaveCSS( 'border-top-color', error );
		await expect(
			page.locator( `#${ await field.getAttribute( 'aria-errormessage' ) }` ),
		).toBeVisible();
	}

	const terms = page.locator( TERMS );

	await expect( terms ).toHaveClass( /has-error/ );
	await expect( terms.locator( 'input[type="checkbox"]' ) ).toHaveCSS( 'border-top-color', error );
	await expect(
		page.locator( '.wc-block-checkout__terms .wc-block-components-validation-error' ),
	).toBeVisible();
} );

test( 'an order WooCommerce refuses comes back with a notice above the form', async ( {
	page,
} ) => {
	await page.setViewportSize( DESKTOP );
	await openCheckout( page );

	await page.locator( '#billing-country' ).selectOption( COUNTRY );

	for ( const [ id, value ] of Object.entries( REFUSED_ORDER ) ) {
		await page.locator( `#${ id }` ).fill( value );
	}

	await page.locator( TERMS ).locator( 'input[type="checkbox"]' ).check();
	await page.locator( PLACE_ORDER ).click();

	const notice = page.locator( NOTICE ).first();

	await expect( notice ).toBeVisible();
	await expect( notice ).toHaveCSS( 'border-left-color', await presetColour( page, 'error' ) );
} );
