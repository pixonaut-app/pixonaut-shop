import AxeBuilder from '@axe-core/playwright';
import { expect, type Page, test } from '@playwright/test';

import { TEST_SUPPORT, wpEval } from './support/wp-cli';

/**
 * The header's search (`inc/Header/HeaderSearch.php`, `src/header/search.ts`):
 * the icon opens a panel under the header with the field focused; typing a
 * product's name suggests it with its picture and price, from the results
 * page's own search, a product the page lists once per card once per card;
 * Enter on a suggestion opens the product, Enter in the field the
 * results page; Escape closes the panel and focus goes back to the icon.
 * Nothing on the page moves while the panel opens, suggests and closes. The
 * fixture's products have a picture each, in a category of their own
 * (`ShopFixtures::searchable_products()`); another fixture's has a card per
 * value (`SplitCardFixtures`, `fake-split-cards.php`).
 */

const TOGGLE = '.pixonaut-shop-header .pixonaut-shop-header-search__toggle';
const PANEL = '.pixonaut-shop-header-search__panel';
const FIELD = '.pixonaut-shop-header-search__input';
const OPTION = '.pixonaut-shop-header-search__option';
const ALL = '.pixonaut-shop-header-search__all';
const NONE = '.pixonaut-shop-header-search__none';
const COUNT = '.pixonaut-shop-header-search__count';

/** The address the panel asks the results page's search for its suggestions at. */
const SUGGESTIONS = '**/?*pixonaut_shop_suggestions=*';

/** The design's sizes: a laptop's and a phone's. */
const VIEWPORTS = [
	{ width: 1280, height: 800 },
	{ width: 390, height: 844 },
];

/** The word both fixture products' names start with. */
const SHARED_WORD = 'Lantern';

/** A word in neither fixture product's name, nor any other product's. */
const UNMATCHED = 'zebrafinch';

/** The axe rules the panel and the results page are checked against: WCAG 2.x A and AA. */
const WCAG = [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa' ];

// One worker makes the fixture's products and removes them once.
test.describe.configure( { mode: 'default' } );

let product: { slug: string; name: string; hidden: string };
let split: { query: string; name: string; cards: string[] };

test.beforeAll( () => {
	const [ slug, name, hidden ] = wpEval(
		`${ TEST_SUPPORT }ShopFixtures::searchable_products(); ` +
			[ 'SEARCHABLE_PRODUCT_SLUG', 'SEARCHABLE_PRODUCT_NAME', 'HIDDEN_PRODUCT_NAME' ]
				.map( ( constant ) => `echo ${ TEST_SUPPORT }ShopFixtures::${ constant }, "\\n";` )
				.join( ' ' ),
	).split( '\n' );
	product = { slug, name, hidden };

	const fixtures = `${ TEST_SUPPORT }SplitCardFixtures`;
	const [ query, splitName, cards ] = wpEval(
		`${ fixtures }::make(); echo ${ fixtures }::QUERY, "\\n", ${ fixtures }::SPLIT_NAME, "\\n", implode( ",", ${ fixtures }::CARDS ), "\\n";`,
	).split( '\n' );
	split = { query, name: splitName, cards: cards.split( ',' ) };
} );

test.afterAll( () => {
	wpEval( `${ TEST_SUPPORT }ShopFixtures::forget_searchable_products();` );
	wpEval( `${ TEST_SUPPORT }SplitCardFixtures::forget();` );
} );

async function open( page: Page ): Promise< void > {
	await page.goto( '/' );
	await page.locator( TOGGLE ).click();
	await expect( page.locator( FIELD ) ).toBeFocused();
}

async function suggest( page: Page, query: string ): Promise< void > {
	await page.locator( FIELD ).pressSequentially( query );
	await expect( page.locator( OPTION ).first() ).toBeVisible();
}

test( 'opening the search focuses its field', async ( { page } ) => {
	await open( page );

	await expect( page.locator( PANEL ) ).toBeVisible();
	await expect( page.locator( TOGGLE ) ).toHaveAttribute( 'aria-expanded', 'true' );
	await expect( page.getByRole( 'combobox', { name: 'Search products' } ) ).toBeFocused();
} );

test( 'typing a product’s name lists it with its picture and price', async ( { page } ) => {
	await open( page );
	await suggest( page, product.name );

	const option = page.getByRole( 'option', { name: new RegExp( product.name ) } );
	await expect( option ).toBeVisible();
	await expect( option.locator( 'img' ) ).toHaveAttribute( 'src', /\.(png|jpe?g|webp)/ );
	await expect( option.locator( '.pixonaut-shop-header-search__price' ) ).toContainText( /\d/ );
	await expect( page.locator( FIELD ) ).toHaveAttribute( 'aria-expanded', 'true' );
	await expect( page.locator( ALL ) ).toHaveAttribute( 'href', /[?&]post_type=product(&|$)/ );
} );

test( 'a product hidden from the search is never suggested, nor counted', async ( { page } ) => {
	await open( page );
	await suggest( page, SHARED_WORD );

	await expect( page.locator( OPTION ) ).toHaveCount( 2 );
	await expect( page.getByRole( 'option', { name: new RegExp( product.hidden ) } ) ).toHaveCount(
		0,
	);
	await expect( page.locator( COUNT ) ).toHaveText( '2 products' );
} );

test( 'a product the results page lists once per card is suggested and counted per card', async ( {
	page,
} ) => {
	await open( page );
	await suggest( page, split.query );

	// The split product's cards, and the plain product the query also finds.
	await expect( page.locator( COUNT ) ).toHaveText( `${ split.cards.length + 1 } products` );
	await expect( page.locator( OPTION ) ).toHaveCount( split.cards.length + 1 );
	await expect( page.locator( ALL ) ).toHaveText( `Show all ${ split.cards.length + 1 } results` );

	for ( const card of split.cards ) {
		const option = page.getByRole( 'option', { name: `${ split.name } – ${ card }` } );
		await expect( option ).toBeVisible();
		await option.click();
		await expect.poll( () => new URL( page.url() ).searchParams.get( 'fake_card' ) ).toBe( card );
		await open( page );
		await suggest( page, split.query );
	}
} );

test( 'Enter after the query changed searches the new query, not the suggestion picked before', async ( {
	page,
} ) => {
	await open( page );
	await suggest( page, product.name );
	const field = page.locator( FIELD );
	await field.press( 'ArrowDown' );

	// The new query's suggestions never arrive, so only what was listed before is in view.
	await page.route( SUGGESTIONS, () => {} );
	// A query nothing matches, so WooCommerce shows its results page rather than one product's.
	const query = `${ product.name } ${ UNMATCHED }`;
	await field.pressSequentially( query.slice( product.name.length ) );
	await expect( field ).not.toHaveAttribute( 'aria-activedescendant', /.+/ );
	const allHref = ( await page.locator( ALL ).getAttribute( 'href' ) ) ?? '';
	expect( new URL( allHref ).searchParams.get( 's' ) ).toBe( query );
	await field.press( 'Enter' );

	await expect.poll( () => new URL( page.url() ).searchParams.get( 's' ) ).toBe( query );
} );

test( 'Enter on a suggestion opens its product', async ( { page } ) => {
	await open( page );
	await suggest( page, product.name );

	const field = page.locator( FIELD );
	await field.press( 'ArrowDown' );
	await expect( page.locator( OPTION ).first() ).toHaveAttribute( 'aria-selected', 'true' );
	await expect( field ).toHaveAttribute( 'aria-activedescendant', /.+/ );
	await field.press( 'Enter' );

	await expect( page ).toHaveURL( new RegExp( `/${ product.slug }/` ) );
} );

test( 'Enter in the field opens the results page', async ( { page } ) => {
	await open( page );
	// Both fixture products match, so WooCommerce lists them rather than opening the one.
	await page.locator( FIELD ).fill( SHARED_WORD );
	await page.locator( FIELD ).press( 'Enter' );

	await expect( page ).toHaveURL( /[?&]post_type=product(&|$)/ );
	await expect(
		page.locator( 'main' ).getByRole( 'heading', { level: 1, name: 'Search results' } ),
	).toBeVisible();
} );

test( 'a query without a match says so', async ( { page } ) => {
	await open( page );
	await page.locator( FIELD ).pressSequentially( UNMATCHED );

	await expect( page.locator( NONE ) ).toHaveText( `No products match “${ UNMATCHED }”` );
	await expect( page.locator( OPTION ) ).toHaveCount( 0 );
	await expect( page.locator( FIELD ) ).toHaveAttribute( 'aria-expanded', 'false' );
} );

test( 'an empty search keeps the panel open, without the browser’s validation message', async ( {
	page,
} ) => {
	await open( page );
	const url = page.url();

	await page.locator( FIELD ).press( 'Enter' );

	await expect( page.locator( PANEL ) ).toBeVisible();
	await expect( page.locator( FIELD ) ).toBeFocused();
	expect( page.url() ).toBe( url );
} );

test( 'Escape closes the search and focus goes back to the icon', async ( { page } ) => {
	await open( page );
	await suggest( page, product.name );

	await page.keyboard.press( 'Escape' );

	await expect( page.locator( PANEL ) ).toBeHidden();
	await expect( page.locator( TOGGLE ) ).toBeFocused();
	await expect( page.locator( TOGGLE ) ).toHaveAttribute( 'aria-expanded', 'false' );
} );

test( 'a click outside closes the search', async ( { page } ) => {
	await open( page );

	// The window's bottom corner, on the scrim, well clear of the panel.
	const { height } = page.viewportSize() ?? { height: 0 };
	await page.mouse.click( 1, height - 1 );

	await expect( page.locator( PANEL ) ).toBeHidden();
} );

test( 'the close button closes the search', async ( { page } ) => {
	await open( page );

	await page.getByRole( 'button', { name: 'Close search' } ).click();

	await expect( page.locator( PANEL ) ).toBeHidden();
	await expect( page.locator( TOGGLE ) ).toBeFocused();
} );

for ( const viewport of VIEWPORTS ) {
	test( `opening, typing and closing shifts no layout at ${ viewport.width }px`, async ( {
		page,
	} ) => {
		await page.setViewportSize( viewport );
		await page.addInitScript( () => {
			const shifts: number[] = [];
			( window as unknown as { shifts: number[] } ).shifts = shifts;
			new PerformanceObserver( ( list ) => {
				for ( const entry of list.getEntries() ) {
					shifts.push( ( entry as PerformanceEntry & { value: number } ).value );
				}
			} ).observe( { type: 'layout-shift', buffered: true } );
		} );
		await page.goto( '/' );
		await page.evaluate( () => document.fonts.ready );
		// The page's own loading may shift it; only what the search does counts.
		await page.evaluate(
			() => ( ( window as unknown as { shifts: number[] } ).shifts.length = 0 ),
		);

		await page.locator( TOGGLE ).click();
		await expect( page.locator( FIELD ) ).toBeFocused();
		await suggest( page, product.name );
		// From suggestions to none, and the list gives way to the "nothing found" line.
		await page.locator( FIELD ).pressSequentially( ` ${ UNMATCHED }` );
		await expect( page.locator( NONE ) ).toBeVisible();
		await page.keyboard.press( 'Escape' );
		await expect( page.locator( PANEL ) ).toBeHidden();

		const shifts = await page.evaluate(
			() => ( window as unknown as { shifts: number[] } ).shifts,
		);
		expect( shifts ).toEqual( [] );
	} );
}

test( 'the open search passes the accessibility check', async ( { page } ) => {
	await open( page );
	await suggest( page, product.name );
	await page.locator( FIELD ).press( 'ArrowDown' );

	const results = await new AxeBuilder( { page } )
		.include( '.pixonaut-shop-header-search' )
		.withTags( WCAG )
		.analyze();

	expect( results.violations ).toEqual( [] );
} );

test( 'the results page passes the accessibility check', async ( { page } ) => {
	await page.goto( `/?${ new URLSearchParams( { s: SHARED_WORD, post_type: 'product' } ) }` );
	await expect( page.locator( 'main .wc-block-product' ).first() ).toBeVisible();

	// The page's own content: the footer's and the mini cart's markup are core's and WooCommerce's.
	const results = await new AxeBuilder( { page } ).include( 'main' ).withTags( WCAG ).analyze();

	expect( results.violations ).toEqual( [] );
} );
