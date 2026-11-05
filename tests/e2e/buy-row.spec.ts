import { expect, type Locator, type Page, test } from '@playwright/test';

/**
 * The add-to-cart form's last row holds a Personalise button as it holds Add
 * to cart, and the fallback for the theme's typeface takes up the room the
 * typeface does. The fake plugin (`tests/e2e/fake-pixonaut.php`) prints the
 * button on a product it otherwise leaves alone when the address asks for it,
 * so a page with the button and one without differ by the button alone.
 */

const PLAIN_PRODUCT_PATH = '/product/plain-product/';
const VARIABLE_PRODUCT_PATH = '/product/swatches-product/';
const CHOSEN_VARIATION = 'attribute_size=M&attribute_colour=White';

/** The address's argument for the fake's button, and its values (`tests/e2e/fake-pixonaut.php`). */
const BUTTON_ARG = 'fake_personalise_button';
const BUTTON_SHOWN = 'shown';
const BUTTON_ONE_AT_A_TIME = 'one-at-a-time';
const BUTTON_UNAVAILABLE = 'unavailable';
const BUTTON_DESIGNED = 'designed';
const BUTTON_KEEP_DESIGN = 'keep-design';

const ROW = 'form.cart .pixonaut-shop-buy-row';
const PRICE = `${ ROW } [data-pixonaut-price]`;
const QUANTITY = `${ ROW } input.qty:not([type="hidden"])`;
const VISIBLE_BUTTON = `${ ROW } button[type="submit"]:visible`;
const MESSAGE = `${ ROW } [role="alert"]`;
const PERSONALISED_BOX = 'form.cart .pixonaut-personalised';
const TITLE = '.pixonaut-shop-product-info .wp-block-post-title';

/**
 * Without this, Linux's hinting rounds each glyph's advance, which a store's
 * customers on other systems never see and which says nothing of the faces.
 */
test.use( { launchOptions: { args: [ '--font-render-hinting=none' ] } } );

const VIEWPORTS = [
	{ name: 'desktop', width: 1280, height: 900 },
	{ name: 'tablet', width: 900, height: 900 },
	{ name: 'phone', width: 390, height: 844 },
];

/** How far apart two pages' boxes may be, in pixels: rounding of fractional layouts. */
const SAME_BOX_TOLERANCE = 1;

/**
 * How much of its width text may change when the typeface replaces its
 * fallback. A price sets this: Arial's digits are the furthest from Inter's.
 */
const SWAP_WIDTH_TOLERANCE = 0.075;

type Box = NonNullable< Awaited< ReturnType< Locator[ 'boundingBox' ] > > >;

async function box( locator: Locator ): Promise< Box > {
	const found = await locator.boundingBox();

	if ( found === null ) {
		throw new Error( 'The element has no box.' );
	}

	return found;
}

function expectSameBox( actual: Box, expected: Box, part: string ): void {
	for ( const side of [ 'x', 'y', 'width', 'height' ] as const ) {
		expect(
			Math.abs( actual[ side ] - expected[ side ] ),
			`${ part }'s ${ side }: ${ actual[ side ] } against ${ expected[ side ] }`,
		).toBeLessThanOrEqual( SAME_BOX_TOLERANCE );
	}
}

async function rowBoxes( page: Page ): Promise< { price: Box; quantity: Box; button: Box } > {
	return {
		price: await box( page.locator( PRICE ) ),
		quantity: await box( page.locator( QUANTITY ) ),
		button: await box( page.locator( VISIBLE_BUTTON ) ),
	};
}

for ( const { name, width, height } of VIEWPORTS ) {
	test.describe( `at ${ name } width`, () => {
		test.use( { viewport: { width, height } } );

		test( 'a simple product lays Personalise out as it does Add to cart', async ( { page } ) => {
			await page.goto( PLAIN_PRODUCT_PATH );
			await expect( page.locator( VISIBLE_BUTTON ) ).toHaveText( 'Add to cart' );
			const addToCart = await rowBoxes( page );

			await page.goto( `${ PLAIN_PRODUCT_PATH }?${ BUTTON_ARG }=${ BUTTON_SHOWN }` );
			await expect( page.locator( VISIBLE_BUTTON ) ).toHaveText( 'Personalise' );
			const personalise = await rowBoxes( page );

			expectSameBox( personalise.price, addToCart.price, 'the price' );
			expectSameBox( personalise.quantity, addToCart.quantity, 'the quantity' );
			expectSameBox( personalise.button, addToCart.button, 'the button' );
		} );

		test( "a variable product's chosen variation lays Personalise out as it does Add to cart", async ( {
			page,
		} ) => {
			await page.goto( `${ VARIABLE_PRODUCT_PATH }?${ CHOSEN_VARIATION }` );
			await expect( page.locator( VISIBLE_BUTTON ) ).toHaveText( 'Add to cart' );
			const addToCart = await rowBoxes( page );

			await page.goto(
				`${ VARIABLE_PRODUCT_PATH }?${ CHOSEN_VARIATION }&${ BUTTON_ARG }=${ BUTTON_SHOWN }`,
			);
			await expect( page.locator( VISIBLE_BUTTON ) ).toHaveText( 'Personalise' );
			const personalise = await rowBoxes( page );

			expectSameBox( personalise.price, addToCart.price, 'the price' );
			expectSameBox( personalise.quantity, addToCart.quantity, 'the quantity' );
			expectSameBox( personalise.button, addToCart.button, 'the button' );
		} );

		test( 'with a hidden quantity the row holds the price and the button', async ( { page } ) => {
			await page.goto( `${ PLAIN_PRODUCT_PATH }?${ BUTTON_ARG }=${ BUTTON_ONE_AT_A_TIME }` );

			await expect( page.locator( VISIBLE_BUTTON ) ).toHaveText( 'Personalise' );
			await expect( page.locator( PRICE ) ).toBeVisible();
			await expect( page.locator( `${ ROW } .quantity` ) ).toBeHidden();
		} );

		test( "an unavailable product shows the plugin's message where the button goes", async ( {
			page,
		} ) => {
			await page.goto( PLAIN_PRODUCT_PATH );
			const addToCart = await rowBoxes( page );

			await page.goto( `${ PLAIN_PRODUCT_PATH }?${ BUTTON_ARG }=${ BUTTON_UNAVAILABLE }` );

			await expect( page.locator( VISIBLE_BUTTON ) ).toHaveCount( 0 );
			const message = await box( page.locator( MESSAGE ) );
			const quantity = await box( page.locator( QUANTITY ) );

			// The message may take two lines, so the row's height and the quantity's place in it may differ.
			expect( quantity.x ).toBeCloseTo( addToCart.quantity.x, 0 );
			expect( quantity.width ).toBeCloseTo( addToCart.quantity.width, 0 );
			expect( message.x ).toBeGreaterThanOrEqual( quantity.x + quantity.width );
			expect( message.y ).toBeLessThan( quantity.y + quantity.height );
			expect( message.x + message.width ).toBeLessThanOrEqual(
				addToCart.button.x + addToCart.button.width + SAME_BOX_TOLERANCE,
			);
		} );

		test( 'a returned design leaves both buttons whole and inside the row', async ( { page } ) => {
			await page.goto( PLAIN_PRODUCT_PATH );
			const addToCart = await rowBoxes( page );

			await page.goto( `${ PLAIN_PRODUCT_PATH }?${ BUTTON_ARG }=${ BUTTON_KEEP_DESIGN }` );

			const buttons = page.locator( VISIBLE_BUTTON );
			await expect( buttons ).toHaveText( [ 'Use my design', 'Personalise' ] );
			const row = await box( page.locator( ROW ) );
			const quantity = await box( page.locator( QUANTITY ) );

			expect( quantity.x ).toBeGreaterThanOrEqual( row.x );

			for ( const button of await buttons.all() ) {
				const found = await box( button );

				expect( found.height ).toBeCloseTo( addToCart.button.height, 0 );
				expect( found.x ).toBeGreaterThanOrEqual( row.x );
				expect( found.x + found.width ).toBeLessThanOrEqual(
					row.x + row.width + SAME_BOX_TOLERANCE,
				);
			}
		} );

		test( "a design that waits shows Add to cart in the row, under the plugin's box", async ( {
			page,
		} ) => {
			await page.goto( PLAIN_PRODUCT_PATH );
			const addToCart = await rowBoxes( page );

			await page.goto( `${ PLAIN_PRODUCT_PATH }?${ BUTTON_ARG }=${ BUTTON_DESIGNED }` );

			await expect( page.locator( VISIBLE_BUTTON ) ).toHaveText( 'Add to cart' );
			const designed = await rowBoxes( page );
			const personalised = await box( page.locator( PERSONALISED_BOX ) );
			const row = await box( page.locator( ROW ) );

			// The box pushes the row down, so only its height and each part's across may match.
			for ( const part of [ 'price', 'quantity', 'button' ] as const ) {
				expect( designed[ part ].x ).toBeCloseTo( addToCart[ part ].x, 0 );
				expect( designed[ part ].width ).toBeCloseTo( addToCart[ part ].width, 0 );
				expect( designed[ part ].height ).toBeCloseTo( addToCart[ part ].height, 0 );
			}
			expect( personalised.y + personalised.height ).toBeLessThanOrEqual( row.y );
			expect( personalised.width ).toBeCloseTo( row.width, 0 );
		} );

		test( 'the fallback takes up the room the typeface does', async ( { page } ) => {
			await page.goto( PLAIN_PRODUCT_PATH );
			await page.evaluate( () => document.fonts.ready );
			expect( await page.evaluate( () => document.fonts.check( '700 16px "Inter"' ) ) ).toBe(
				true,
			);
			const typeface = {
				row: await box( page.locator( ROW ) ),
				title: await box( page.locator( TITLE ) ),
				price: await textWidth( page.locator( PRICE ) ),
				titleText: await textWidth( page.locator( TITLE ) ),
			};

			await page.route( /\.woff2/, ( route ) => route.abort() );
			await page.reload();
			await page.evaluate( () => document.fonts.ready );

			expect( await page.evaluate( () => document.fonts.check( '700 16px "Inter"' ) ) ).toBe(
				false,
			);

			const row = await box( page.locator( ROW ) );
			const title = await box( page.locator( TITLE ) );

			expect( row.height ).toBeCloseTo( typeface.row.height, 0 );
			expect( title.height ).toBeCloseTo( typeface.title.height, 0 );
			expectWithin( await textWidth( page.locator( PRICE ) ), typeface.price, 'the price' );
			expectWithin( await textWidth( page.locator( TITLE ) ), typeface.titleText, 'the title' );
		} );
	} );
}

test( 'text stays visible while the typeface loads', async ( { page } ) => {
	await page.goto( PLAIN_PRODUCT_PATH );

	const displays = await page.evaluate( () =>
		[ ...document.fonts ]
			.filter( ( face ) => face.family.replaceAll( '"', '' ) === 'Inter' )
			.map( ( face ) => face.display ),
	);

	expect( displays.length ).toBeGreaterThan( 0 );
	expect( new Set( displays ) ).toEqual( new Set( [ 'swap' ] ) );
} );

/** The width of an element's text, whatever width its box is laid out at. */
function textWidth( locator: Locator ): Promise< number > {
	return locator.evaluate( ( element ) => {
		const range = document.createRange();
		range.selectNodeContents( element );

		return range.getBoundingClientRect().width;
	} );
}

function expectWithin( actual: number, expected: number, part: string ): void {
	expect(
		Math.abs( actual - expected ) / expected,
		`${ part }'s width: ${ actual } against ${ expected }`,
	).toBeLessThanOrEqual( SWAP_WIDTH_TOLERANCE );
}
