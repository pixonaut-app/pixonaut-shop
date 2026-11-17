import AxeBuilder from '@axe-core/playwright';
import { expect, type Page, test } from '@playwright/test';

import { TEST_SUPPORT, wpEval } from './support/wp-cli';

/**
 * An archive's filters (`patterns/product-filters.php`,
 * `inc/Archive/ProductFilters.php`, `FilterDrawer.php`, `FilterSections.php`):
 * a button in the toolbar above the grid opens them in a drawer at every
 * width, with a section for each product attribute the store has and the
 * price, and the chosen filters show as chips under the toolbar. The fixture
 * is a category of two prints, one sold framed and one not (`FilterFixtures`).
 */

const CARD = '.wc-block-product';
const PANEL = '.pixonaut-shop-archive-filters';
const CHOSEN = '.pixonaut-shop-archive-chosen';
const CHIP = '.wc-block-product-filter-removable-chips__item';
const DIALOG = '.wc-block-product-filters__overlay-dialog';
const TOOLBAR = '.pixonaut-shop-archive-toolbar';
const SECTION = '.pixonaut-shop-filter-section';
const SORTING = '.woocommerce-ordering select';
/** The axe rules the drawer is checked against: WCAG 2.x A and AA. */
const WCAG = [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa' ];
const DESKTOP = { width: 1280, height: 800 };
const PHONE = { width: 390, height: 844 };
const VIEWPORTS = [ DESKTOP, PHONE ];
const NESTED = '.pixonaut-shop-filter-nested';
/** One of a list's values, with what is nested under it. */
const ITEM = '.wc-block-product-filter-checkbox-list__item';
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
	black: string;
	hanger: string;
	/** What the plugin reads from the fixture's variations: a frame's colour applies with a hanger or a frame only. */
	dependencies: Record< string, Record< string, string[] > >;
	oakFilter: string;
	blackFilter: string;
	hangerAndFrameFilter: string;
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
		black,
		hanger,
		hangerSlug,
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
				'BLACK',
				'HANGER',
				'HANGER_SLUG',
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
		black,
		hanger,
		dependencies: {
			[ `pa_${ slugOf( frameColour ) }` ]: {
				[ `pa_${ framingSlug }` ]: [ hangerSlug, frameSlug ],
			},
		},
		oakFilter: `filter_${ slugOf( frameColour ) }=${ slugOf( oak ) }`,
		blackFilter: `filter_${ slugOf( frameColour ) }=${ slugOf( black ) }`,
		hangerAndFrameFilter: `filter_${ framingSlug }=${ hangerSlug },${ frameSlug }`,
	};
} );

/** A name as WordPress makes a slug of it, for the fixture's plain names. */
function slugOf( name: string ): string {
	return name.toLowerCase().replace( / /g, '-' );
}

/** Has the fake plugin tell this visitor's pages that the frame's colour depends on a hanger or a frame. */
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

function drawer( page: Page ) {
	return page.locator( `${ PANEL } ${ DIALOG }` );
}

/** Opens the drawer from the toolbar's button. */
async function openDrawer( page: Page ): Promise< void > {
	await page
		.locator( TOOLBAR )
		.getByRole( 'button', { name: /Show filters/ } )
		.click();
	await expect( drawer( page ) ).toBeVisible();
	// The drawer slides in; a click mid-slide can miss.
	await page.waitForFunction( ( selector ) => {
		const dialog = document.querySelector( selector );

		return dialog !== null && dialog.getBoundingClientRect().left >= 0;
	}, `${ PANEL } ${ DIALOG }` );
}

/** The drawer's section of an attribute, or of the price, opened. */
async function section( page: Page, name: string ) {
	const found = drawer( page )
		.locator( SECTION )
		.filter( {
			has: page.locator( 'summary', { hasText: name } ),
		} );

	if ( ! ( await found.evaluate( ( details: HTMLDetailsElement ) => details.open ) ) ) {
		await found.locator( 'summary' ).click();
	}

	return found;
}

/** Ticks a value in the drawer and waits for the filtered page, which opens the drawer again. */
async function tick( page: Page, group: string, value: string, address: RegExp ): Promise< void > {
	const list = await section( page, group );
	await list.getByRole( 'checkbox', { name: value, exact: true } ).check();
	await expect( page ).toHaveURL( address );
	await expect( drawer( page ) ).toBeVisible();
}

/** Records every layout shift from now on, in `window.shifts`. */
async function watchShifts( page: Page ): Promise< void > {
	await page.addInitScript( () => {
		const shifts: number[] = [];
		( window as unknown as { shifts: number[] } ).shifts = shifts;
		new PerformanceObserver( ( list ) => {
			for ( const entry of list.getEntries() ) {
				shifts.push( ( entry as PerformanceEntry & { value: number } ).value );
			}
		} ).observe( { type: 'layout-shift', buffered: true } );
	} );
}

async function shifts( page: Page ): Promise< number[] > {
	return page.evaluate( () => ( window as unknown as { shifts: number[] } ).shifts );
}

for ( const viewport of VIEWPORTS ) {
	test.describe( `at ${ viewport.width }px`, () => {
		test.use( { viewport } );

		test( '"Show filters" opens the drawer; a value and "Show N products" list the filtered products with a chip', async ( {
			page,
		} ) => {
			await page.goto( fixture.path );
			await expect( cards( page ) ).toHaveCount( 2 );
			await expect( drawer( page ) ).toBeHidden();

			await openDrawer( page );
			await expect(
				drawer( page ).getByRole( 'button', { name: 'Show 2 products' } ),
			).toBeVisible();
			await tick( page, fixture.framing, fixture.frame, new RegExp( fixture.filter ) );

			await drawer( page ).getByRole( 'button', { name: 'Show 1 product' } ).click();
			await expect( drawer( page ) ).toBeHidden();
			await expect( cards( page ) ).toHaveCount( 1 );
			await expect( cards( page ) ).toContainText( fixture.framed );

			const chip = page.locator( `${ CHOSEN } ${ CHIP }` );
			await expect( chip ).toHaveText( new RegExp( `${ fixture.framing }: ${ fixture.frame }` ) );
			await expect(
				page.locator( TOOLBAR ).getByRole( 'button', { name: /Show filters/ } ),
			).toContainText( '1' );

			await chip.getByRole( 'button' ).click();
			await expect( page ).not.toHaveURL( new RegExp( fixture.filter ) );
			await expect( cards( page ) ).toHaveCount( 2 );
			await expect( page.locator( CHOSEN ) ).toBeHidden();
		} );

		test( '"Clear all" in the drawer clears every filter', async ( { page } ) => {
			await page.goto(
				`${ fixture.path }?${ fixture.filter }&min_price=${ fixture.priceBetween }`,
			);
			await expect( page.locator( `${ CHOSEN } ${ CHIP }` ) ).toHaveCount( 2 );

			await openDrawer( page );
			await drawer( page ).getByRole( 'button', { name: 'Clear all' } ).click();

			await expect( page ).not.toHaveURL( /filter_|min_price/ );
			await expect( cards( page ) ).toHaveCount( 2 );
			await expect( page.locator( CHOSEN ) ).toBeHidden();
		} );

		test( 'the sorting in the toolbar changes the order', async ( { page } ) => {
			await page.goto( fixture.path );
			await expect( cards( page ).first() ).toContainText( fixture.framed );

			await page.locator( `${ TOOLBAR } ${ SORTING }` ).selectOption( 'date' );

			await expect( page ).toHaveURL( /orderby=date/ );
			await expect( cards( page ).first() ).toContainText( fixture.unframed );
			await expect( drawer( page ).locator( SORTING ) ).toHaveCount( 0 );
		} );

		test( 'loading, opening, closing, ticking and taking a chip away shift no layout', async ( {
			page,
		} ) => {
			// The first visit loads the fonts, whose swap moves text on every page;
			// the archive's own load is measured on the next.
			await page.goto( fixture.path );
			await page.evaluate( () => document.fonts.ready );
			await watchShifts( page );
			await page.goto( fixture.path );
			await page.evaluate( () => document.fonts.ready );
			expect( await shifts( page ), 'on load' ).toEqual( [] );

			await openDrawer( page );
			await page.keyboard.press( 'Escape' );
			await expect( drawer( page ) ).toBeHidden();
			expect( await shifts( page ), 'opening and closing' ).toEqual( [] );

			await openDrawer( page );
			await tick( page, fixture.framing, fixture.frame, new RegExp( fixture.filter ) );
			await page.evaluate( () => document.fonts.ready );
			await drawer( page ).getByRole( 'button', { name: 'Show 1 product' } ).click();
			await expect( drawer( page ) ).toBeHidden();
			expect( await shifts( page ), 'ticking a value' ).toEqual( [] );

			await page.locator( `${ CHOSEN } ${ CHIP }` ).getByRole( 'button' ).click();
			await expect( cards( page ) ).toHaveCount( 2 );
			await page.evaluate( () => document.fonts.ready );
			expect( await shifts( page ), 'taking the chip away' ).toEqual( [] );
		} );
	} );
}

test( 'the drawer is a dialog the keyboard enters, stays in and leaves to its button', async ( {
	page,
} ) => {
	await page.goto( fixture.path );
	const button = page.locator( TOOLBAR ).getByRole( 'button', { name: /Show filters/ } );

	await button.focus();
	await page.keyboard.press( 'Enter' );
	const dialog = page.getByRole( 'dialog', { name: 'Filters' } );
	await expect( dialog ).toBeVisible();
	await expect( button ).toHaveAttribute( 'aria-expanded', 'true' );
	await expect( dialog.getByRole( 'button', { name: 'Close' } ) ).toBeFocused();

	// Shift+Tab from the first control goes round to the last, not out to the page.
	await page.keyboard.press( 'Shift+Tab' );
	await expect( dialog.getByRole( 'button', { name: 'Show 2 products' } ) ).toBeFocused();
	await page.keyboard.press( 'Tab' );
	await expect( dialog.getByRole( 'button', { name: 'Close' } ) ).toBeFocused();

	await page.keyboard.press( 'Escape' );
	await expect( dialog ).toBeHidden();
	await expect( button ).toBeFocused();
	await expect( button ).toHaveAttribute( 'aria-expanded', 'false' );
} );

test( 'a section opens and closes from the keyboard, and one with a chosen value is open', async ( {
	page,
} ) => {
	await page.goto( `${ fixture.path }?${ fixture.filter }` );
	await openDrawer( page );

	const framing = drawer( page )
		.locator( SECTION )
		.filter( { has: page.locator( 'summary', { hasText: fixture.framing } ) } );
	await expect( framing ).toHaveAttribute( 'open', '' );
	await expect(
		framing.getByRole( 'checkbox', { name: fixture.frame, exact: true } ),
	).toBeChecked();

	const price = drawer( page )
		.locator( SECTION )
		.filter( { has: page.locator( 'summary', { hasText: 'Price' } ) } );
	await expect( price ).not.toHaveAttribute( 'open', /.*/ );
	await price.locator( 'summary' ).focus();
	await page.keyboard.press( 'Enter' );
	await expect( price ).toHaveAttribute( 'open', '' );
	await expect( price.locator( 'input[type="text"].min' ) ).toBeVisible();
} );

test( 'the open drawer passes the accessibility check', async ( { page } ) => {
	await page.goto( `${ fixture.path }?${ fixture.filter }` );
	await openDrawer( page );

	const results = await new AxeBuilder( { page } )
		.include( `${ PANEL } ${ DIALOG }` )
		.withTags( WCAG )
		.analyze();

	expect( results.violations ).toEqual( [] );
} );

test( 'an address with a filter opens the archive filtered', async ( { page } ) => {
	await page.goto( `${ fixture.path }?${ fixture.filter }` );

	await expect( cards( page ) ).toHaveCount( 1 );
	await openDrawer( page );
	await expect(
		drawer( page ).getByRole( 'checkbox', { name: fixture.frame, exact: true } ),
	).toBeChecked();
	await page.keyboard.press( 'Escape' );

	await page.locator( CHOSEN ).getByRole( 'button', { name: 'Clear all' } ).click();

	await expect( page ).not.toHaveURL( new RegExp( fixture.filter ) );
	await expect( cards( page ) ).toHaveCount( 2 );
} );

test( 'the drawer opens again where it was, with the same sections open and the focus on the value', async ( {
	page,
} ) => {
	await page.goto( fixture.path );
	await openDrawer( page );
	await section( page, 'Price' );

	const value = ( await section( page, fixture.framing ) ).getByRole( 'checkbox', {
		name: fixture.frame,
		exact: true,
	} );
	await value.focus();
	await page.keyboard.press( 'Space' );

	await expect( page ).toHaveURL( new RegExp( fixture.filter ) );
	await expect( drawer( page ) ).toBeVisible();
	const price = drawer( page )
		.locator( SECTION )
		.filter( { has: page.locator( 'summary', { hasText: 'Price' } ) } );
	await expect( price ).toHaveAttribute( 'open', '' );
	await expect(
		drawer( page ).getByRole( 'checkbox', { name: fixture.frame, exact: true } ),
	).toBeFocused();
} );

test( 'a price typed into its field keeps the drawer open', async ( { page } ) => {
	await page.goto( fixture.path );
	await openDrawer( page );

	const lowest = ( await section( page, 'Price' ) ).locator( 'input[type="text"].min' );
	await lowest.fill( fixture.priceBetween );

	// WooCommerce loads the filtered page a moment after the last key.
	await expect( page ).toHaveURL( new RegExp( `min_price=${ fixture.priceBetween }` ) );
	await expect( drawer( page ) ).toBeVisible();
	await drawer( page ).getByRole( 'button', { name: 'Show 1 product' } ).click();
	await expect( cards( page ) ).toHaveCount( 1 );
} );

test.describe( 'with an attribute that applies under a value of another', () => {
	test.beforeEach( async ( { page } ) => withDependencies( page ) );

	/** The framing's value, with what is nested under it. */
	function item( page: Page, value: string ) {
		return drawer( page )
			.getByRole( 'group', { name: fixture.framing } )
			.locator( ITEM )
			.filter( { has: page.getByRole( 'checkbox', { name: value, exact: true } ) } );
	}

	for ( const viewport of VIEWPORTS ) {
		test( `it shows only under a chosen value it applies under, and its values tick like any other, at ${ viewport.width }px`, async ( {
			page,
		} ) => {
			await page.setViewportSize( viewport );
			await page.goto( fixture.path );
			await openDrawer( page );

			await expect(
				drawer( page ).locator( SECTION ).filter( { hasText: fixture.frameColour } ),
			).toHaveCount( 0 );
			await expect( drawer( page ).locator( NESTED ) ).toHaveCount( 0 );

			await tick( page, fixture.framing, fixture.frame, new RegExp( fixture.filter ) );

			const nested = item( page, fixture.frame ).locator( NESTED );
			await expect( nested ).toHaveCount( 1 );
			await expect( nested.getByRole( 'group', { name: fixture.frameColour } ) ).toBeVisible();
			await expect( item( page, fixture.hanger ).locator( NESTED ) ).toHaveCount( 0 );

			await nested.getByRole( 'checkbox', { name: fixture.black, exact: true } ).check();
			await expect( page ).toHaveURL( new RegExp( fixture.blackFilter ) );
			await expect( drawer( page ) ).toBeVisible();
			await expect(
				item( page, fixture.frame )
					.locator( NESTED )
					.getByRole( 'checkbox', { name: fixture.black, exact: true } ),
			).toBeChecked();
			await drawer( page )
				.getByRole( 'button', { name: /Show 1 product/ } )
				.click();
			await expect( cards( page ) ).toHaveCount( 1 );
			await expect(
				page.locator( `${ CHOSEN } ${ CHIP }`, {
					hasText: new RegExp( `${ fixture.frameColour }: ${ fixture.black }` ),
				} ),
			).toBeVisible();
		} );
	}

	test( 'it shows once under several chosen values it applies under', async ( { page } ) => {
		await page.goto( `${ fixture.path }?${ fixture.hangerAndFrameFilter }` );
		await openDrawer( page );

		await expect( page.locator( PANEL ).locator( NESTED ) ).toHaveCount( 1 );
		await expect( item( page, fixture.frame ).locator( NESTED ) ).toHaveCount( 1 );
		await expect(
			page.locator( PANEL ).getByRole( 'checkbox', { name: fixture.oak, exact: true } ),
		).toHaveCount( 1 );
	} );

	test( 'a chosen value of it shows without a chosen parent, so it can be cleared', async ( {
		page,
	} ) => {
		await page.goto( `${ fixture.path }?${ fixture.oakFilter }` );
		await openDrawer( page );

		const oak = drawer( page ).getByRole( 'checkbox', { name: fixture.oak, exact: true } );
		await expect( oak ).toBeChecked();
		await oak.uncheck();
		await expect( page ).not.toHaveURL( new RegExp( fixture.oakFilter ) );
		await expect( page.locator( PANEL ).locator( NESTED ) ).toHaveCount( 0 );
	} );
} );
