import { devices, expect, type Page, test } from '@playwright/test';

import { TEST_SUPPORT, wpEval } from './support/wp-cli';

/**
 * The filters beside an archive's product grid (`patterns/product-filters.php`,
 * `inc/Archive/ProductFilters.php`): a group for each product attribute the
 * store has, the chosen filters as chips above the grid, and on a phone a
 * button that opens the filters in a dialog. The fixture is a category of
 * two prints, one sold framed and one not (`FilterFixtures`).
 */

const CARD = '.wc-block-product';
const PANEL = '.pixonaut-shop-archive-filters';
const CHOSEN = '.pixonaut-shop-archive-chosen';
const CHIP = '.wc-block-product-filter-removable-chips__item';
const OPEN_BUTTON = '.wc-block-product-filters__open-overlay';
const DIALOG = '.wc-block-product-filters__overlay-dialog';
const NESTED = '.pixonaut-shop-filter-nested';
/** The fake plugin's cookie that names the attribute dependencies (`fake-pixonaut.php`). */
const DEPENDENCIES_COOKIE = 'fake_attribute_dependencies';

// One worker makes the fixture and removes it once, in order, rather than a
// worker removing it under another one's tests.
test.describe.configure( { mode: 'default' } );

let fixture: {
	path: string;
	framed: string;
	unframed: string;
	framing: string;
	frame: string;
	filter: string;
	priceBetween: string;
	frameColour: string;
	noFrame: string;
	oak: string;
	/** What the plugin reads from the fixture's variations: a frame's colour applies with a frame only. */
	dependencies: Record< string, Record< string, string[] > >;
	oakFilter: string;
};

test.beforeAll( () => {
	const [
		slug,
		framed,
		unframed,
		framing,
		frame,
		framingSlug,
		frameSlug,
		priceBetween,
		frameColour,
		noFrame,
		oak,
	] = wpEval(
		`${ TEST_SUPPORT }FilterFixtures::prepare(); ` +
			[
				'CATEGORY_SLUG',
				'FRAMED_PRODUCT',
				'UNFRAMED_PRODUCT',
				'FRAMING',
				'FRAME',
				'FRAMING_SLUG',
				'FRAME_SLUG',
				'PRICE_BETWEEN',
				'FRAME_COLOUR',
				'NO_FRAME',
				'OAK',
			]
				.map( ( name ) => `echo ${ TEST_SUPPORT }FilterFixtures::${ name }, "\\n";` )
				.join( ' ' ),
	).split( '\n' );
	fixture = {
		path: `/product-category/${ slug }/`,
		framed,
		unframed,
		framing,
		frame,
		filter: `filter_${ framingSlug }=${ frameSlug }`,
		priceBetween,
		frameColour,
		noFrame,
		oak,
		dependencies: {
			[ `pa_${ slugOf( frameColour ) }` ]: { [ `pa_${ framingSlug }` ]: [ frameSlug ] },
		},
		oakFilter: `filter_${ slugOf( frameColour ) }=${ slugOf( oak ) }`,
	};
} );

/** A name as WordPress makes a slug of it, for the fixture's plain names. */
function slugOf( name: string ): string {
	return name.toLowerCase().replace( / /g, '-' );
}

/** Has the fake plugin tell this visitor's pages that the frame's colour depends on a frame. */
async function withDependencies( page: Page ): Promise< void > {
	await page.context().addCookies( [
		{
			name: DEPENDENCIES_COOKIE,
			value: encodeURIComponent( JSON.stringify( fixture.dependencies ) ),
			url: process.env.WP_BASE_URL,
		},
	] );
}

test.afterAll( () => {
	wpEval( `${ TEST_SUPPORT }FilterFixtures::forget();` );
} );

function cards( page: Page ) {
	return page.locator( `main .pixonaut-shop-archive-results ${ CARD }` );
}

test( 'ticking a value narrows the grid, puts the filter in the address and shows it as a chip', async ( {
	page,
} ) => {
	await page.goto( fixture.path );
	await expect( cards( page ) ).toHaveCount( 2 );

	const group = page.locator( PANEL ).getByRole( 'group', { name: fixture.framing } );
	await group.getByRole( 'checkbox', { name: fixture.frame, exact: true } ).check();

	await expect( page ).toHaveURL( new RegExp( fixture.filter ) );
	await expect( cards( page ) ).toHaveCount( 1 );
	await expect( cards( page ) ).toContainText( fixture.framed );

	const chip = page.locator( `${ CHOSEN } ${ CHIP }` );
	await expect( chip ).toHaveText( new RegExp( `${ fixture.framing }: ${ fixture.frame }` ) );

	await chip.getByRole( 'button' ).click();

	await expect( page ).not.toHaveURL( new RegExp( fixture.filter ) );
	await expect( cards( page ) ).toHaveCount( 2 );
	await expect( page.locator( CHOSEN ) ).toBeHidden();
} );

test( 'an address with a filter opens the archive filtered', async ( { page } ) => {
	await page.goto( `${ fixture.path }?${ fixture.filter }` );

	await expect( cards( page ) ).toHaveCount( 1 );
	await expect(
		page.locator( PANEL ).getByRole( 'checkbox', { name: fixture.frame, exact: true } ),
	).toBeChecked();

	await page.locator( CHOSEN ).getByRole( 'button', { name: 'Clear all' } ).click();

	await expect( page ).not.toHaveURL( new RegExp( fixture.filter ) );
	await expect( cards( page ) ).toHaveCount( 2 );
} );

test( 'the keyboard reaches a value and ticks it', async ( { page } ) => {
	await page.goto( fixture.path );

	const value = page.locator( PANEL ).getByRole( 'checkbox', { name: fixture.frame, exact: true } );
	await value.focus();
	await expect( value ).toBeFocused();
	await page.keyboard.press( 'Space' );

	await expect( page ).toHaveURL( new RegExp( fixture.filter ) );
	await expect( cards( page ) ).toHaveCount( 1 );
} );

test.describe( 'with an attribute that applies under a value of another', () => {
	test.beforeEach( async ( { page } ) => withDependencies( page ) );

	test( 'it shows folded under that value only, and the keyboard opens it and ticks a value', async ( {
		page,
	} ) => {
		await page.goto( fixture.path );

		const panel = page.locator( PANEL );
		await expect(
			panel.locator( '.wp-block-woocommerce-product-filter-attribute h3', {
				hasText: fixture.frameColour,
			} ),
		).toHaveCount( 0 );

		const framing = panel.getByRole( 'group', { name: fixture.framing } );
		const noFrame = framing
			.locator( '.wc-block-product-filter-checkbox-list__item' )
			.filter( { has: page.getByRole( 'checkbox', { name: fixture.noFrame, exact: true } ) } );
		await expect( noFrame.locator( NESTED ) ).toHaveCount( 0 );

		const nested = framing.locator( NESTED );
		await expect( nested ).toHaveCount( 1 );
		const oak = nested.getByRole( 'checkbox', { name: fixture.oak, exact: true } );
		await expect( oak ).toBeHidden();

		const toggle = nested.locator( 'summary', { hasText: fixture.frameColour } );
		await toggle.focus();
		await page.keyboard.press( 'Enter' );
		await expect( oak ).toBeVisible();

		await page.keyboard.press( 'Tab' );
		await expect( oak ).toBeFocused();
		await page.keyboard.press( 'Space' );

		await expect( page ).toHaveURL( new RegExp( fixture.oakFilter ) );
		await expect( cards( page ) ).toHaveCount( 1 );
		await expect( cards( page ) ).toContainText( fixture.framed );
		await expect( page.locator( `${ CHOSEN } ${ CHIP }` ) ).toHaveText(
			new RegExp( `${ fixture.frameColour }: ${ fixture.oak }` ),
		);
		// The page opens with the chosen value in view.
		await expect(
			page.locator( PANEL ).getByRole( 'checkbox', { name: fixture.oak, exact: true } ),
		).toBeChecked();
		await expect(
			page.locator( PANEL ).getByRole( 'checkbox', { name: fixture.oak, exact: true } ),
		).toBeVisible();

		await page.locator( CHOSEN ).getByRole( 'button', { name: 'Clear all' } ).click();
		await expect( page ).not.toHaveURL( new RegExp( fixture.oakFilter ) );
		await expect( cards( page ) ).toHaveCount( 2 );
	} );

	test.describe( 'on a phone', () => {
		test.use( { viewport: devices[ 'iPhone 13' ].viewport } );

		test( 'the dialog shows it nested too, and a nested value ticks', async ( { page } ) => {
			await page.goto( fixture.path );
			await page.locator( `${ PANEL } ${ OPEN_BUTTON }` ).click();

			const dialog = page.locator( `${ PANEL } ${ DIALOG }` );
			const nested = dialog.locator( NESTED );
			await nested.locator( 'summary', { hasText: fixture.frameColour } ).click();
			await nested.getByRole( 'checkbox', { name: fixture.oak, exact: true } ).check();

			await expect( page ).toHaveURL( new RegExp( fixture.oakFilter ) );
			await expect( page.locator( `${ PANEL } ${ DIALOG }` ) ).toBeVisible();
			await expect(
				page
					.locator( `${ PANEL } ${ DIALOG } ${ NESTED }` )
					.getByRole( 'checkbox', { name: fixture.oak, exact: true } ),
			).toBeChecked();
		} );
	} );
} );

test.describe( 'on a phone', () => {
	test.use( { viewport: devices[ 'iPhone 13' ].viewport } );

	test( 'the dialog stays open over a price typed into its field', async ( { page } ) => {
		await page.goto( fixture.path );
		await page.locator( `${ PANEL } ${ OPEN_BUTTON }` ).click();

		const lowest = page.locator( `${ PANEL } ${ DIALOG } input[type="text"].min` );
		await lowest.fill( fixture.priceBetween );

		// WooCommerce loads the filtered page a moment after the last key.
		await expect( page ).toHaveURL( new RegExp( `min_price=${ fixture.priceBetween }` ) );
		await expect( page.locator( `${ PANEL } ${ DIALOG }` ) ).toBeVisible();
		await page
			.locator( `${ PANEL } ${ DIALOG }` )
			.getByRole( 'button', { name: 'Show products' } )
			.click();
		await expect( cards( page ) ).toHaveCount( 1 );
	} );

	test( 'a button opens the filters in a dialog, which stays open over a choice and its button and Escape close', async ( {
		page,
	} ) => {
		await page.goto( fixture.path );

		const dialog = page.locator( `${ PANEL } ${ DIALOG }` );
		await expect( dialog ).toBeHidden();

		await page.locator( `${ PANEL } ${ OPEN_BUTTON }` ).click();
		await expect( dialog ).toBeVisible();

		await dialog.getByRole( 'checkbox', { name: fixture.frame, exact: true } ).check();
		await expect( page ).toHaveURL( new RegExp( fixture.filter ) );
		// The filtered page opens with the dialog still open, for a second choice.
		await expect( page.locator( `${ PANEL } ${ DIALOG }` ) ).toBeVisible();

		await page
			.locator( `${ PANEL } ${ DIALOG }` )
			.getByRole( 'button', { name: 'Show products' } )
			.click();
		await expect( page.locator( `${ PANEL } ${ DIALOG }` ) ).toBeHidden();
		await expect( cards( page ) ).toHaveCount( 1 );
		await expect( page.locator( `${ CHOSEN } ${ CHIP }` ) ).toBeVisible();

		await page.locator( `${ PANEL } ${ OPEN_BUTTON }` ).click();
		await expect( page.locator( `${ PANEL } ${ DIALOG }` ) ).toBeVisible();
		await page.keyboard.press( 'Escape' );
		await expect( page.locator( `${ PANEL } ${ DIALOG }` ) ).toBeHidden();
	} );
} );
