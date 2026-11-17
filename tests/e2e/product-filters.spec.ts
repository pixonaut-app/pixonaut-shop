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

/** Ticks a value in the drawer and waits for the filtered results, with the drawer still open. */
async function tick( page: Page, group: string, value: string, address: RegExp ): Promise< void > {
	const list = await section( page, group );
	await list.getByRole( 'checkbox', { name: value, exact: true } ).check();
	await expect( page ).toHaveURL( address );
	await expect( drawer( page ) ).toBeVisible();
}

/** The page's root, which carries the class for results on their way (`src/archive/filters-loading.ts`). */
const LOADING = /\bis-loading-products\b/;
const RESULTS = 'main .pixonaut-shop-archive-results';
/** How long the Interactivity API's router waits before it shows that a page is on its way. */
const ROUTER_LOADING_DELAY_MS = 400;

/**
 * Holds back the archive's new results that `matches` picks until the
 * returned function is called. WooCommerce's router fetches them as a page.
 */
async function holdResults( page: Page, matches: ( url: URL ) => boolean ): Promise< () => void > {
	let release = (): void => {};
	const released = new Promise< void >( ( resolve ) => {
		release = resolve;
	} );

	await page.route( matches, async ( route ) => {
		await released;
		await route.continue();
	} );

	return release;
}

/** Marks the page, so a test can tell it was never loaded again. */
async function markThePage( page: Page ): Promise< void > {
	await page.evaluate( () => {
		( window as unknown as { notLoadedAgain: boolean } ).notLoadedAgain = true;
	} );
}

async function wasLoadedAgain( page: Page ): Promise< boolean > {
	return page.evaluate(
		() => ( window as unknown as { notLoadedAgain?: boolean } ).notLoadedAgain !== true,
	);
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

/**
 * Forgets the shifts so far, such as a section the customer opened. The
 * browser reports a shift a frame or so after it, so those frames pass first.
 */
async function forgetShifts( page: Page ): Promise< void > {
	await page.evaluate( async () => {
		for ( const _frame of [ 1, 2 ] ) {
			await new Promise( requestAnimationFrame );
		}
		( window as unknown as { shifts: number[] } ).shifts.length = 0;
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
			await section( page, 'Price' );
			await drawer( page ).getByRole( 'button', { name: 'Clear all' } ).click();

			await expect( page ).not.toHaveURL( /filter_|min_price/ );
			// The drawer stays as it was, for new choices.
			await expect( drawer( page ) ).toBeVisible();
			await expect( drawer( page ).getByRole( 'button', { name: 'Clear all' } ) ).toBeDisabled();
			await expect(
				drawer( page )
					.locator( SECTION )
					.filter( { has: page.locator( 'summary', { hasText: 'Price' } ) } ),
			).toHaveAttribute( 'open', '' );
			await page.keyboard.press( 'Escape' );
			await expect( cards( page ) ).toHaveCount( 2 );
			await expect( page.locator( CHOSEN ) ).toBeHidden();
		} );

		test( "the sorting in the toolbar, a button like the filters' one, changes the order in place", async ( {
			page,
		} ) => {
			await page.goto( fixture.path );
			await markThePage( page );
			await expect( cards( page ).first() ).toContainText( fixture.framed );

			const sorting = page.locator( `${ TOOLBAR } ${ SORTING }` );
			const button = page.locator( TOOLBAR ).getByRole( 'button', { name: /Show filters/ } );
			const looks = ( element: Element ) => {
				const style = getComputedStyle( element );

				return [
					element.getBoundingClientRect().height,
					style.borderRadius,
					style.borderTopWidth,
					style.fontWeight,
				];
			};
			expect( await sorting.locator( 'xpath=..' ).evaluate( looks ) ).toEqual(
				await button.evaluate( looks ),
			);

			await sorting.selectOption( 'date' );

			await expect( page ).toHaveURL( /orderby=date/ );
			await expect( cards( page ).first() ).toContainText( fixture.unframed );
			await expect( drawer( page ).locator( SORTING ) ).toHaveCount( 0 );
			expect( await wasLoadedAgain( page ) ).toBe( false );

			// The longest order stays in its own place, clear of the filters' button.
			await sorting.selectOption( 'price-desc' );
			await expect( page ).toHaveURL( /orderby=price-desc/ );
			const buttonBox = await button.boundingBox();
			const sortingBox = await sorting.locator( 'xpath=..' ).boundingBox();
			expect( sortingBox?.x ).toBeGreaterThanOrEqual(
				( buttonBox?.x ?? 0 ) + ( buttonBox?.width ?? 0 ),
			);
			expect( ( sortingBox?.x ?? 0 ) + ( sortingBox?.width ?? 0 ) ).toBeLessThanOrEqual(
				viewport.width,
			);
		} );

		test( 'loading, opening and closing shift no layout, nor do the skeletons while new results are on their way', async ( {
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

			// The new results themselves may move the grid: they hold other cards.
			const release = await holdResults( page, ( url ) => url.search.includes( fixture.filter ) );
			await openDrawer( page );
			const framing = await section( page, fixture.framing );
			await forgetShifts( page );
			await framing.getByRole( 'checkbox', { name: fixture.frame, exact: true } ).check();
			await expect( page.locator( 'html' ) ).toHaveClass( LOADING );
			await page.evaluate( () => new Promise( requestAnimationFrame ) );
			expect( await shifts( page ), 'showing the skeletons' ).toEqual( [] );
			release();
			await expect( page.locator( 'html' ) ).not.toHaveClass( LOADING );
		} );

		test( 'ticking three values filters in place: the drawer stays open and the grid shows skeletons until each result is in', async ( {
			page,
		} ) => {
			await page.goto( fixture.path );
			await markThePage( page );
			await openDrawer( page );

			const release = await holdResults( page, ( url ) => url.search.includes( fixture.filter ) );
			const frame = ( await section( page, fixture.framing ) ).getByRole( 'checkbox', {
				name: fixture.frame,
				exact: true,
			} );
			await frame.check();

			await expect( page.locator( 'html' ) ).toHaveClass( LOADING );
			await expect( page.locator( RESULTS ) ).toHaveAttribute( 'aria-busy', 'true' );
			await expect( cards( page ) ).toHaveCount( 2 );
			await expect( drawer( page ) ).toBeVisible();

			release();
			await expect( page ).toHaveURL( new RegExp( fixture.filter ) );
			await expect( page.locator( 'html' ) ).not.toHaveClass( LOADING );
			await expect( page.locator( RESULTS ) ).not.toHaveAttribute( 'aria-busy', /.*/ );
			await expect( cards( page ) ).toHaveCount( 1 );
			await expect( drawer( page ) ).toBeVisible();
			await expect( frame ).toBeFocused();

			for ( const value of [ fixture.hanger, fixture.noFrame ] ) {
				await ( await section( page, fixture.framing ) )
					.getByRole( 'checkbox', { name: value, exact: true } )
					.check();
				await expect( page.locator( 'html' ) ).not.toHaveClass( LOADING );
				await expect( drawer( page ) ).toBeVisible();
			}

			await expect( cards( page ) ).toHaveCount( 2 );
			await expect(
				page.locator( TOOLBAR ).getByRole( 'button', { name: /Show filters/ } ),
			).toContainText( '3' );
			expect( await wasLoadedAgain( page ) ).toBe( false );
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

test( 'a value ticked from the keyboard keeps the drawer where it was, with the same sections open and the focus on the value', async ( {
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

test( 'a later choice wins over an earlier one still on its way', async ( { page } ) => {
	await page.goto( fixture.path );
	await markThePage( page );
	await openDrawer( page );
	const framing = await section( page, fixture.framing );

	// Unframed alone lists one product; with the frame too, both.
	const releaseNoFrame = await holdResults(
		page,
		( url ) =>
			url.searchParams.get( `filter_${ slugOf( fixture.framing ) }` ) === slugOf( fixture.noFrame ),
	);
	await framing.getByRole( 'checkbox', { name: fixture.noFrame, exact: true } ).check();
	await expect( page.locator( 'html' ) ).toHaveClass( LOADING );
	await framing.getByRole( 'checkbox', { name: fixture.frame, exact: true } ).check();

	await expect( page.locator( 'html' ) ).not.toHaveClass( LOADING );
	await expect( cards( page ) ).toHaveCount( 2 );
	releaseNoFrame();
	await page.waitForLoadState( 'networkidle' );

	await expect( cards( page ) ).toHaveCount( 2 );
	await expect(
		framing.getByRole( 'checkbox', { name: fixture.frame, exact: true } ),
	).toBeChecked();
	expect( await wasLoadedAgain( page ) ).toBe( false );
} );

test.describe( 'a page of the filtered results', () => {
	/** A lowest price both of the fixture's products are over, so the filter lists two pages of one. */
	const LOWEST_PRICE = 50;
	let kept = '';

	test.beforeAll( () => {
		kept = wpEval( `echo implode( ",", ${ TEST_SUPPORT }FilterFixtures::one_product_a_page() );` );
	} );

	test.afterAll( () => {
		wpEval( `${ TEST_SUPPORT }FilterFixtures::keep_page_size( ${ kept } );` );
	} );

	test( 'opens in place and keeps the filter', async ( { page } ) => {
		await page.goto( `${ fixture.path }?min_price=${ LOWEST_PRICE }` );
		await markThePage( page );
		await expect( cards( page ) ).toHaveCount( 1 );
		const first = await cards( page ).innerText();

		await page
			.locator( RESULTS )
			.locator( '.wp-block-query-pagination' )
			.getByRole( 'link', { name: '2' } )
			.click();

		await expect( page ).toHaveURL( new RegExp( `/page/2/.*min_price=${ LOWEST_PRICE }` ) );
		await expect( cards( page ) ).toHaveCount( 1 );
		await expect( cards( page ) ).not.toHaveText( first );
		expect( await wasLoadedAgain( page ) ).toBe( false );
	} );
} );

test( 'a picture still on its way shows the skeleton colour in its box, not the card', async ( {
	page,
} ) => {
	await page.goto( fixture.path );

	const background = await cards( page )
		.first()
		.locator( '.wc-block-components-product-image img' )
		.evaluate( ( picture ) => getComputedStyle( picture ).backgroundColor );
	const card = await cards( page )
		.first()
		.evaluate( ( element ) => getComputedStyle( element ).backgroundColor );

	expect( background ).not.toBe( 'rgba(0, 0, 0, 0)' );
	expect( background ).not.toBe( card );
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

test( 'a price that asks for the results already shown shows no skeletons', async ( { page } ) => {
	await page.goto( `${ fixture.path }?min_price=${ fixture.priceBetween }` );
	await openDrawer( page );
	const lowest = ( await section( page, 'Price' ) ).locator( 'input[type="text"].min' );

	// Typed again as it is, then left: WooCommerce has nothing to fetch.
	await lowest.fill( fixture.priceBetween );
	await lowest.blur();

	// Longer than the router waits before it shows that a page is on its way.
	await page.waitForTimeout( ROUTER_LOADING_DELAY_MS * 2 );
	await expect( page.locator( 'html' ) ).not.toHaveClass( LOADING );
	await expect( page.locator( RESULTS ) ).not.toHaveAttribute( 'aria-busy', /.*/ );
} );

test( 'sections opened and closed while results are on their way stay so once they are in', async ( {
	page,
} ) => {
	await page.goto( fixture.path );
	await openDrawer( page );
	const framing = await section( page, fixture.framing );

	const release = await holdResults( page, ( url ) => url.search.includes( fixture.filter ) );
	await framing.getByRole( 'checkbox', { name: fixture.frame, exact: true } ).check();
	await expect( page.locator( 'html' ) ).toHaveClass( LOADING );

	// The framing has a chosen value, so the new drawer opens it; the customer closed it.
	await framing.locator( 'summary' ).click();
	await section( page, 'Price' );
	release();

	await expect( page ).toHaveURL( new RegExp( fixture.filter ) );
	await expect( page.locator( 'html' ) ).not.toHaveClass( LOADING );
	await expect( cards( page ) ).toHaveCount( 1 );
	await expect(
		drawer( page )
			.locator( SECTION )
			.filter( { has: page.locator( 'summary', { hasText: fixture.framing } ) } ),
	).not.toHaveAttribute( 'open', /.*/ );
	await expect(
		drawer( page )
			.locator( SECTION )
			.filter( { has: page.locator( 'summary', { hasText: 'Price' } ) } ),
	).toHaveAttribute( 'open', '' );
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
			await expect(
				item( page, fixture.frame ).getByRole( 'checkbox', { name: fixture.frame, exact: true } ),
			).toBeChecked();
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

	test( 'unticking the value it shows under clears its chosen values, and the value stays ticked meanwhile', async ( {
		page,
	} ) => {
		await page.goto( fixture.path );
		await openDrawer( page );
		await tick( page, fixture.framing, fixture.frame, new RegExp( fixture.filter ) );

		const frame = item( page, fixture.frame ).getByRole( 'checkbox', {
			name: fixture.frame,
			exact: true,
		} );
		await item( page, fixture.frame )
			.locator( NESTED )
			.getByRole( 'checkbox', { name: fixture.black, exact: true } )
			.check();
		await expect( page ).toHaveURL( new RegExp( fixture.blackFilter ) );
		await expect( frame ).toBeChecked();

		await frame.uncheck();
		await expect( page ).not.toHaveURL( new RegExp( fixture.filter ) );
		await expect( page ).not.toHaveURL( new RegExp( fixture.blackFilter ) );
		await expect( page.locator( PANEL ).locator( NESTED ) ).toHaveCount( 0 );
		await expect( frame ).not.toBeChecked();
	} );

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
