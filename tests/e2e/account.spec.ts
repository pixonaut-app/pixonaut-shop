import { expect, type Page, test } from '@playwright/test';

import { TEST_SUPPORT, wpEval } from './support/wp-cli';

/**
 * My account, signed out and signed in. Signed out, the login and the
 * registration are two panels side by side, stacked on phones. Signed in,
 * the navigation sits beside the dashboard, which adds its shortcuts and the
 * recent orders for the global setup's customer and order
 * (`tests/Support/ShopFixtures.php`). With registration off, the login
 * stands alone in one panel. The orders page lists the orders in a panel,
 * a personalised order by its line furthest behind, and an order's view
 * adds breadcrumbs and an overview above its details. The page itself is
 * never a panel around them, and signed in it takes the wide width.
 */

// One case turns registration off for the whole site: the file's cases run in
// order, in one worker, so none of the others sees it off.
test.describe.configure( { mode: 'default' } );

const ACCOUNT_PATH = '/my-account/';

const DESKTOP = { width: 1440, height: 900 };
const PHONE = { width: 390, height: 844 };

/** A laptop's screen, where the account's two addresses sit side by side. */
const LAPTOP = { width: 1280, height: 800 };

const LOGIN_PANEL = '#customer_login > .u-column1';
const REGISTER_PANEL = '#customer_login > .u-column2';

/** WooCommerce's login title and form, printed straight into the page while registration is off. */
const STANDALONE_LOGIN_TITLE = '.woocommerce > h2:has( + form.login )';
const STANDALONE_LOGIN_FORM = '.woocommerce > h2 + form.login';

/** A computed background colour with nothing drawn. */
const TRANSPARENT = 'rgba(0, 0, 0, 0)';

/** The page's own panel, which the account's panels sit in. */
const PAGE_PANEL = 'main .pixonaut-shop-page-panel';

/** The signed-in account: the navigation and the content, side by side. */
const ACCOUNT = '.woocommerce:has( > .woocommerce-MyAccount-navigation )';

/** The account endpoints a customer reaches from the navigation. */
const ENDPOINTS = [ '', 'orders/', 'edit-address/', 'edit-account/' ];

/** The endpoints that draw no panel of their own, so their content is one. */
const FORM_ENDPOINTS = [ 'edit-address/', 'edit-address/billing/', 'edit-account/' ];

/** The three dashboard shortcuts: the latest order, the addresses and the account details. */
const SHORTCUT_COUNT = 3;

test.describe( 'signed out', () => {
	test.use( { storageState: { cookies: [], origins: [] } } );

	test( 'shows the login and registration side by side on a wide screen', async ( { page } ) => {
		await page.setViewportSize( DESKTOP );
		await page.goto( ACCOUNT_PATH );

		const login = await box( page, LOGIN_PANEL );
		const register = await box( page, REGISTER_PANEL );

		expect( register.y ).toBe( login.y );
		expect( register.x ).toBeGreaterThanOrEqual( login.x + login.width );
	} );

	test( 'stacks the login above the registration on a phone', async ( { page } ) => {
		await page.setViewportSize( PHONE );
		await page.goto( ACCOUNT_PATH );

		const login = await box( page, LOGIN_PANEL );
		const register = await box( page, REGISTER_PANEL );

		expect( register.x ).toBe( login.x );
		expect( register.y ).toBeGreaterThanOrEqual( login.y + login.height );
	} );

	test( 'shows the login alone in a panel when registration is off', async ( { page } ) => {
		offerRegistration( false );

		try {
			await page.setViewportSize( DESKTOP );
			await page.goto( ACCOUNT_PATH );

			await expect( page.locator( '#customer_login' ) ).toHaveCount( 0 );

			const title = await look( page, STANDALONE_LOGIN_TITLE );
			const form = await look( page, STANDALONE_LOGIN_FORM );

			expect( form.background ).not.toBe( TRANSPARENT );
			expect( form.borderBottom ).toBeGreaterThan( 0 );
			expect( form.paddingBottom ).toBeGreaterThan( 0 );
			// The title is the panel's top: the same background, bordered above.
			expect( title.background ).toBe( form.background );
			expect( title.borderTop ).toBeGreaterThan( 0 );
		} finally {
			offerRegistration( true );
		}
	} );

	test( 'draws no panel around the login and registration', async ( { page } ) => {
		await page.setViewportSize( DESKTOP );
		await page.goto( ACCOUNT_PATH );

		await expectNoPanel( page, PAGE_PANEL );
	} );

	test( 'shows the lost-password form in a panel of its own', async ( { page } ) => {
		await page.setViewportSize( DESKTOP );
		await page.goto( `${ ACCOUNT_PATH }lost-password/` );

		await expectNoPanel( page, PAGE_PANEL );
		await expectPanel( page, 'form.lost_reset_password' );
	} );
} );

test.describe( 'signed in', () => {
	test.use( { storageState: { cookies: [], origins: [] } } );

	test( 'shows the navigation beside the dashboard, its shortcuts and recent orders', async ( {
		page,
	} ) => {
		await page.setViewportSize( DESKTOP );
		await signIn( page );

		const navigation = page.locator( '.woocommerce-MyAccount-navigation' );
		const content = page.locator( '.woocommerce-MyAccount-content' );

		await expect( navigation ).toBeVisible();
		await expect( navigation.locator( 'li.is-active a' ) ).toHaveAttribute(
			'aria-current',
			'page',
		);
		await expect( content.locator( '.ps-account-shortcut' ) ).toHaveCount( SHORTCUT_COUNT );
		await expect( content.locator( '.ps-recent-orders .woocommerce-orders-table' ) ).toBeVisible();

		const nav = await box( page, '.woocommerce-MyAccount-navigation' );
		const main = await box( page, '.woocommerce-MyAccount-content' );

		expect( main.x ).toBeGreaterThanOrEqual( nav.x + nav.width );
	} );

	for ( const endpoint of ENDPOINTS ) {
		test( `frames ${ endpoint || 'the dashboard' } once, at the wide width`, async ( { page } ) => {
			await page.setViewportSize( DESKTOP );
			await signIn( page, `${ ACCOUNT_PATH }${ endpoint }` );

			await expectNoPanel( page, PAGE_PANEL );

			const account = await box( page, ACCOUNT );
			const wideSize = await page.evaluate( () =>
				Number.parseFloat(
					getComputedStyle( document.body ).getPropertyValue( '--wp--style--global--wide-size' ),
				),
			);

			expect( account.width ).toBe( wideSize );
		} );
	}

	for ( const endpoint of FORM_ENDPOINTS ) {
		test( `shows ${ endpoint } as one panel`, async ( { page } ) => {
			await page.setViewportSize( DESKTOP );
			await signIn( page, `${ ACCOUNT_PATH }${ endpoint }` );

			await expectPanel( page, '.woocommerce-MyAccount-content' );
		} );
	}

	/*
	 * On a laptop each edit link sits on its heading's line, across from it.
	 * On a phone it may wrap under the heading, starting where the heading
	 * does; either way it comes before the address.
	 */
	for ( const { viewport, mayWrap } of [
		{ viewport: LAPTOP, mayWrap: false },
		{ viewport: PHONE, mayWrap: true },
	] ) {
		test( `puts each address's edit link with its heading at ${ viewport.width }px`, async ( {
			page,
		} ) => {
			await page.setViewportSize( viewport );
			await signIn( page, `${ ACCOUNT_PATH }edit-address/` );

			const addresses = page.locator( '.woocommerce-Address' );

			expect( await addresses.count() ).toBeGreaterThan( 0 );

			for ( const address of await addresses.all() ) {
				const heading = await address.locator( '.woocommerce-Address-title h2' ).boundingBox();
				const link = await address.locator( '.woocommerce-Address-title .edit' ).boundingBox();
				const body = await address.locator( 'address' ).boundingBox();

				if ( ! heading || ! link || ! body ) {
					throw new Error( 'An address is missing its heading, edit link or address.' );
				}

				const besideHeading =
					link.x >= heading.x + heading.width &&
					link.y < heading.y + heading.height &&
					link.y + link.height > heading.y;
				const underHeading = Math.round( link.x ) === Math.round( heading.x );

				expect( besideHeading || ( mayWrap && underHeading ) ).toBe( true );
				expect( link.y + link.height ).toBeLessThanOrEqual( body.y );
			}
		} );
	}
} );

test.describe( 'orders', () => {
	test.use( { storageState: { cookies: [], origins: [] } } );

	/**
	 * The plugin's label for the state of the line furthest behind among the
	 * fixture's personalised order's lines, one in production and one approved.
	 */
	const LEAST_ADVANCED_LABEL = 'Design approved';

	const OVERVIEW_FACTS = 4;

	let orderId = '';

	test.beforeAll( () => {
		orderId = wpEval( `echo ${ TEST_SUPPORT }ShopFixtures::personalised_order();` );
	} );

	test.afterAll( () => {
		wpEval( `${ TEST_SUPPORT }ShopFixtures::delete_order(${ Number( orderId ) });` );
	} );

	test( 'lists the orders in a panel, a personalised one by its production state', async ( {
		page,
	} ) => {
		await page.setViewportSize( DESKTOP );
		await signIn( page, `${ ACCOUNT_PATH }orders/` );

		const row = orderRow( page, orderId );

		await expect( row.locator( 'mark.order-status' ) ).toHaveText( LEAST_ADVANCED_LABEL );

		const table = await look( page, '.woocommerce-MyAccount-content > .woocommerce-orders-table' );

		expect( table.background ).not.toBe( TRANSPARENT );
		expect( table.borderBottom ).toBeGreaterThan( 0 );

		const number = await row
			.locator( '.woocommerce-orders-table__cell-order-number' )
			.boundingBox();
		const date = await row.locator( '.woocommerce-orders-table__cell-order-date' ).boundingBox();

		expect( date?.y ).toBe( number?.y );
		expect( date?.x ).toBeGreaterThanOrEqual( ( number?.x ?? 0 ) + ( number?.width ?? 0 ) );
	} );

	test( 'stacks each order as a card on a phone', async ( { page } ) => {
		await page.setViewportSize( PHONE );
		await signIn( page, `${ ACCOUNT_PATH }orders/` );

		const row = orderRow( page, orderId );
		const number = await row
			.locator( '.woocommerce-orders-table__cell-order-number' )
			.boundingBox();
		const status = await row
			.locator( '.woocommerce-orders-table__cell-order-status' )
			.boundingBox();
		const total = await row.locator( '.woocommerce-orders-table__cell-order-total' ).boundingBox();

		expect( status?.x ).toBe( number?.x );
		expect( status?.y ).toBeGreaterThan( number?.y ?? 0 );
		expect( total?.x ).toBeGreaterThan( ( number?.x ?? 0 ) + ( number?.width ?? 0 ) );
	} );

	test( 'shows an order with its breadcrumbs, overview and details in a panel', async ( {
		page,
	} ) => {
		await page.setViewportSize( DESKTOP );
		await signIn( page, `${ ACCOUNT_PATH }view-order/${ orderId }/` );

		const content = page.locator( '.woocommerce-MyAccount-content' );

		await expect( content.locator( '.woocommerce-breadcrumb a' ) ).toHaveCount( 2 );
		await expect( content.locator( '.woocommerce-breadcrumb [aria-current="page"]' ) ).toHaveText(
			`Order #${ orderId }`,
		);
		await expect(
			content.locator( '.ps-order-overview .woocommerce-order-overview__status strong' ),
		).toHaveText( LEAST_ADVANCED_LABEL );
		await expect( content.locator( '.ps-order-panel .woocommerce-order-details' ) ).toBeVisible();
		await expect(
			content.locator( '.ps-order-panel .woocommerce-customer-details' ),
		).toBeVisible();

		const facts = content.locator( '.ps-order-overview > li' );

		await expect( facts ).toHaveCount( OVERVIEW_FACTS );

		const first = await facts.first().boundingBox();
		const last = await facts.last().boundingBox();

		// Four facts in a row on a wide screen, two to a row on a phone.
		expect( last?.y ).toBe( first?.y );

		await page.setViewportSize( PHONE );

		const firstOnPhone = await facts.first().boundingBox();
		const lastOnPhone = await facts.last().boundingBox();

		expect( lastOnPhone?.y ).toBeGreaterThan( firstOnPhone?.y ?? 0 );
	} );
} );

/** The orders table's row for an order, by the number its first cell links to. */
function orderRow( page: Page, orderId: string ) {
	return page.locator( '.woocommerce-orders-table__row' ).filter( {
		has: page.locator( '.woocommerce-orders-table__cell-order-number a', {
			hasText: `#${ orderId }`,
		} ),
	} );
}

async function box( page: Page, selector: string ) {
	const found = await page.locator( selector ).boundingBox();

	expect( found, `${ selector } is on the page` ).not.toBeNull();

	return found as NonNullable< typeof found >;
}

/** What makes an element look like a panel, as computed. */
async function look( page: Page, selector: string ) {
	return page.locator( selector ).evaluate( ( element ) => {
		const style = getComputedStyle( element );

		return {
			background: style.backgroundColor,
			borderTop: Number.parseFloat( style.borderTopWidth ),
			borderBottom: Number.parseFloat( style.borderBottomWidth ),
			paddingBottom: Number.parseFloat( style.paddingBottom ),
		};
	} );
}

/** The element is drawn as no panel: no background, border, shadow or padding. */
async function expectNoPanel( page: Page, selector: string ): Promise< void > {
	const element = page.locator( selector );

	await expect( element ).toHaveCSS( 'background-color', TRANSPARENT );
	await expect( element ).toHaveCSS( 'border-top-width', '0px' );
	await expect( element ).toHaveCSS( 'box-shadow', 'none' );
	await expect( element ).toHaveCSS( 'padding-top', '0px' );
}

/** The element is drawn as a panel: a background, a border and padding. */
async function expectPanel( page: Page, selector: string ): Promise< void > {
	const panel = await look( page, selector );

	expect( panel.background ).not.toBe( TRANSPARENT );
	expect( panel.borderTop ).toBeGreaterThan( 0 );
	expect( panel.paddingBottom ).toBeGreaterThan( 0 );
}

/** Offers registration beside the login on My account, or the login alone. */
function offerRegistration( offered: boolean ): void {
	wpEval( `${ TEST_SUPPORT }ShopFixtures::offer_registration(${ offered ? 'true' : 'false' });` );
}

/**
 * Signs in through My account's own login form, as the global setup's
 * customer, then opens the given account page, the dashboard by default.
 */
async function signIn( page: Page, path = ACCOUNT_PATH ): Promise< void > {
	const [ login, password ] = wpEval(
		`echo ${ TEST_SUPPORT }ShopFixtures::CUSTOMER_LOGIN, "\\n", ${ TEST_SUPPORT }ShopFixtures::CUSTOMER_PASSWORD;`,
	).split( '\n' );

	await page.goto( ACCOUNT_PATH );
	await page.locator( '#username' ).fill( login );
	await page.locator( '#password' ).fill( password );
	await page.locator( 'form.login button[type="submit"]' ).click();
	await expect( page.locator( '.woocommerce-MyAccount-navigation' ) ).toBeVisible();

	if ( path !== ACCOUNT_PATH ) {
		await page.goto( path );
	}
}
