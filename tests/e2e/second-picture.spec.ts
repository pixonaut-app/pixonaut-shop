import { devices, expect, type Locator, type Page, test } from '@playwright/test';

import { TEST_SUPPORT, wpEval } from './support/wp-cli';

/**
 * A product card's second picture (`inc/Product/SecondPicture.php`): its
 * next gallery picture, faded in over the first while the card is hovered
 * or holds the focus. The fixture has a product with a gallery picture and
 * one with its one picture alone, in a category of their own
 * (`ShopFixtures::pictured_products()`).
 */

const CARD = '.wc-block-product';
const FIRST_PICTURE = '.wc-block-components-product-image img[data-testid="product-image"]';
const SECOND_PICTURE = '.wc-block-components-product-image .pixonaut-shop-second-picture';
/** A background that paints nothing. */
const TRANSPARENT = 'rgba(0, 0, 0, 0)';
const SHOWN = '1';
const HIDDEN = '0';
/** The second picture's file in every size, as the fixture names it. */
const SECOND_PICTURE_NAME = 'card-second-picture';
const SECOND_PICTURE_FILES = `**/*${ SECOND_PICTURE_NAME }*`;

// One worker makes the fixture's products and removes them once, in order,
// rather than a worker removing them under another one's tests.
test.describe.configure( { mode: 'default' } );

let category: { path: string; twoPictures: string; onePicture: string };

test.beforeAll( () => {
	const [ slug, twoPictures, onePicture ] = wpEval(
		`${ TEST_SUPPORT }ShopFixtures::pictured_products(); ` +
			[ 'PICTURED_CATEGORY_SLUG', 'TWO_PICTURES_PRODUCT_SLUG', 'ONE_PICTURE_PRODUCT_SLUG' ]
				.map( ( name ) => `echo ${ TEST_SUPPORT }ShopFixtures::${ name }, "\\n";` )
				.join( ' ' ),
	).split( '\n' );
	category = { path: `/product-category/${ slug }/`, twoPictures, onePicture };
} );

test.afterAll( () => {
	wpEval( `${ TEST_SUPPORT }ShopFixtures::forget_pictured_products();` );
} );

/** The card that links to the product. */
function card( page: Page, slug: string ): Locator {
	return page
		.locator( `main ${ CARD }` )
		.filter( { has: page.locator( `a[href*="/${ slug }/"]` ) } );
}

test( 'hovering a card with two pictures shows the second over the first', async ( { page } ) => {
	await page.goto( category.path );

	const product = card( page, category.twoPictures );
	const first = product.locator( FIRST_PICTURE );
	const second = product.locator( SECOND_PICTURE );

	await expect( second ).toHaveCount( 1 );
	await expect( second ).toHaveCSS( 'opacity', HIDDEN );

	const before = await product.boundingBox();
	await product.hover();

	await expect( second ).toHaveCSS( 'opacity', SHOWN );
	// Clear while it loads, so the first shows through rather than a blank box.
	await expect( second ).toHaveCSS( 'background-color', TRANSPARENT );
	// Over the first picture's box, so the card keeps its size.
	expect( await second.boundingBox() ).toEqual( await first.boundingBox() );
	expect( await product.boundingBox() ).toEqual( before );
} );

test( 'a second picture that fails to load leaves the first in view', async ( { page } ) => {
	await page.route( SECOND_PICTURE_FILES, ( route ) => route.abort() );
	await page.goto( category.path );

	const product = card( page, category.twoPictures );
	const picture = product.locator( '.wc-block-components-product-image' );

	// Taken out of the card once its file fails, so nothing shows over the first.
	await expect( product.locator( SECOND_PICTURE ) ).toHaveCount( 0 );

	const atRest = await picture.screenshot();
	await product.hover();

	await expect( product.locator( FIRST_PICTURE ) ).toHaveCSS( 'opacity', SHOWN );
	expect( ( await picture.screenshot() ).equals( atRest ) ).toBe( true );
} );

test( 'focusing a card with two pictures shows the second', async ( { page } ) => {
	await page.goto( category.path );

	const product = card( page, category.twoPictures );

	await product.getByRole( 'link' ).first().focus();

	await expect( product.locator( SECOND_PICTURE ) ).toHaveCSS( 'opacity', SHOWN );
} );

test( 'a card with one picture stays as it is', async ( { page } ) => {
	await page.goto( category.path );

	const product = card( page, category.onePicture );

	await expect( product.locator( SECOND_PICTURE ) ).toHaveCount( 0 );
	await product.hover();
	await expect( product.locator( FIRST_PICTURE ) ).toHaveCSS( 'opacity', SHOWN );
} );

test( 'the second picture loads only once its card is near the window', async ( { page } ) => {
	await page.goto( category.path );

	const second = card( page, category.twoPictures ).locator( SECOND_PICTURE );

	await expect( second ).toHaveAttribute( 'loading', 'lazy' );
	await expect( second ).toHaveAttribute( 'width', /^\d+$/ );
	await expect( second ).toHaveAttribute( 'height', /^\d+$/ );
} );

test.describe( 'with reduced motion', () => {
	test.use( { reducedMotion: 'reduce' } );

	test( 'the pictures swap without a fade', async ( { page } ) => {
		await page.goto( category.path );

		const product = card( page, category.twoPictures );

		await expect( product.locator( SECOND_PICTURE ) ).toHaveCSS( 'transition-duration', '0s' );

		await product.hover();

		await expect( product.locator( SECOND_PICTURE ) ).toHaveCSS( 'opacity', SHOWN );
	} );
} );

test.describe( 'on a touch device', () => {
	const { viewport, userAgent, deviceScaleFactor, isMobile, hasTouch } = devices[ 'Pixel 7' ];
	test.use( { viewport, userAgent, deviceScaleFactor, isMobile, hasTouch } );

	test( 'a card shows its first picture only and never loads the second', async ( { page } ) => {
		const requested: string[] = [];
		page.on( 'request', ( request ) => requested.push( request.url() ) );

		await page.goto( category.path );

		const product = card( page, category.twoPictures );
		const second = product.locator( SECOND_PICTURE );

		await expect( product.locator( FIRST_PICTURE ) ).toBeVisible();
		await expect( second ).toBeHidden();

		await product.tap( { trial: true } );
		await page.waitForLoadState( 'networkidle' );

		const source = await second.evaluate( ( image: HTMLImageElement ) => image.currentSrc || '' );
		expect( source ).toBe( '' );
		expect( requested.filter( ( url ) => url.includes( SECOND_PICTURE_NAME ) ) ).toEqual( [] );
	} );
} );
