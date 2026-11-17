import { expect, type Page, type Request, test } from '@playwright/test';

import { TEST_SUPPORT, wpEval } from './support/wp-cli';

/**
 * An archive filtered in the browser (`src/archive/client-archive.ts`)
 * from the index of its cards, which the fake plugin serves while a suite
 * sets its cookie (`fake-pixonaut.php`). The fixture is a category of two
 * prints, one sold framed and one not (`FilterFixtures`).
 */

const CARD =
	'main .pixonaut-shop-archive-results .wc-block-product-template:not(.pixonaut-shop-archive-feed__skeletons) > li';
const PANEL = '.pixonaut-shop-archive-filters';
const DIALOG = '.wc-block-product-filters__overlay-dialog';
const TOOLBAR = '.pixonaut-shop-archive-toolbar';
const SECTION = '.pixonaut-shop-filter-section';
const CHIP = '.pixonaut-shop-archive-chosen-client .wc-block-product-filter-removable-chips__item';
const RESULT_COUNT = 'main .woocommerce-result-count';
const ACTIVE = 'data-pixonaut-shop-client-archive';
const INDEX_COOKIE = 'fake_archive_index';
const DEPENDENCIES_COOKIE = 'fake_attribute_dependencies';
const DESKTOP = { width: 1280, height: 800 };
const PHONE = { width: 390, height: 844 };

test.describe.configure( { mode: 'default' } );

let fixture: {
	path: string;
	framed: string;
	unframed: string;
	framing: string;
	frame: string;
	noFrame: string;
	frameColour: string;
	black: string;
	framingSlug: string;
	frameSlug: string;
	noFrameSlug: string;
	frameColourSlug: string;
	blackSlug: string;
	dependencies: Record< string, Record< string, string[] > >;
};

/** The slug WordPress gives a name of the fixture's: lower case, a hyphen for each space. */
function slugOf( name: string ): string {
	return name.toLowerCase().replace( / /g, '-' );
}

test.beforeAll( () => {
	const [
		slug,
		framed,
		unframed,
		framing,
		frame,
		noFrame,
		frameColour,
		black,
		framingSlug,
		frameSlug,
		hangerSlug,
	] = wpEval(
		`${ TEST_SUPPORT }FilterFixtures::prepare(); ` +
			[
				'CATEGORY_SLUG',
				'FRAMED_PRODUCT',
				'UNFRAMED_PRODUCT',
				'FRAMING',
				'FRAME',
				'NO_FRAME',
				'FRAME_COLOUR',
				'BLACK',
				'FRAMING_SLUG',
				'FRAME_SLUG',
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
		noFrame,
		frameColour,
		black,
		framingSlug,
		frameSlug,
		noFrameSlug: slugOf( noFrame ),
		frameColourSlug: slugOf( frameColour ),
		blackSlug: slugOf( black ),
		dependencies: {
			[ `pa_${ slugOf( frameColour ) }` ]: {
				[ `pa_${ framingSlug }` ]: [ hangerSlug, frameSlug ],
			},
		},
	};
} );

test.afterAll( () => {
	wpEval( `${ TEST_SUPPORT }FilterFixtures::forget();` );
} );

async function withIndex( page: Page, mode = '1' ): Promise< void > {
	await page.context().addCookies( [
		{ name: INDEX_COOKIE, value: mode, url: process.env.WP_BASE_URL },
		{
			name: DEPENDENCIES_COOKIE,
			value: encodeURIComponent( JSON.stringify( fixture.dependencies ) ),
			url: process.env.WP_BASE_URL,
		},
	] );
}

/** Opens the archive and waits until it filters in the browser. */
async function openArchive( page: Page, query = '' ): Promise< void > {
	await page.goto( fixture.path + query );
	await expect( page.locator( 'html' ) ).toHaveAttribute( ACTIVE, '' );
}

function drawer( page: Page ) {
	return page.locator( `${ PANEL } ${ DIALOG }` );
}

async function openDrawer( page: Page ): Promise< void > {
	await page
		.locator( TOOLBAR )
		.getByRole( 'button', { name: /Show filters/ } )
		.click();
	await expect( drawer( page ) ).toBeVisible();
	await page.waitForFunction( ( selector ) => {
		const dialog = document.querySelector( selector );

		return dialog !== null && dialog.getBoundingClientRect().left >= 0;
	}, `${ PANEL } ${ DIALOG }` );
}

async function section( page: Page, name: string ) {
	const found = drawer( page )
		.locator( SECTION )
		.filter( { has: page.locator( 'summary', { hasText: name } ) } );

	if ( ! ( await found.evaluate( ( details: HTMLDetailsElement ) => details.open ) ) ) {
		await found.locator( 'summary' ).click();
	}

	return found;
}

/** The requests the page makes for pages or data, not pictures, fonts or styles. */
function watchRequests( page: Page ): Request[] {
	const made: Request[] = [];
	page.on( 'request', ( request ) => {
		if ( [ 'document', 'fetch', 'xhr' ].includes( request.resourceType() ) ) {
			made.push( request );
		}
	} );

	return made;
}

for ( const viewport of [ DESKTOP, PHONE ] ) {
	test( `ticking values filters the grid, the counts and the chips at once, with no request, at ${ viewport.width }px`, async ( {
		page,
	} ) => {
		await page.setViewportSize( viewport );
		await withIndex( page );
		await openArchive( page );
		await expect( page.locator( CARD ) ).toHaveCount( 2 );

		const made = watchRequests( page );
		await openDrawer( page );
		const framing = await section( page, fixture.framing );
		const frame = framing.getByRole( 'checkbox', { name: fixture.frame, exact: true } );
		await frame.check();

		await expect( page ).toHaveURL(
			new RegExp( `[?&]${ fixture.framingSlug }=${ fixture.frameSlug }` ),
		);
		await expect( frame ).toBeChecked();
		await expect( drawer( page ).getByRole( 'button', { name: 'Show 1 product' } ) ).toBeVisible();
		await expect( page.locator( RESULT_COUNT ) ).toHaveText( '1 product' );
		await expect( page.locator( CARD ) ).toHaveCount( 1 );
		await expect( page.locator( CARD ) ).toContainText( fixture.framed );

		// The frame's colours show under "Frame", ticked or not, and tick like any other value.
		const black = framing.getByRole( 'checkbox', { name: fixture.black, exact: true } );
		await black.check();
		await expect( frame ).toBeChecked();
		await expect( black ).toBeChecked();

		// Unticking "Frame" clears the colour it opened.
		await frame.uncheck();
		await expect(
			framing.getByRole( 'checkbox', { name: fixture.black, exact: true } ),
		).toHaveCount( 0 );
		await expect( page.locator( CARD ) ).toHaveCount( 2 );
		await expect( page ).not.toHaveURL( /\?./ );

		expect( made.map( ( request ) => request.url() ) ).toEqual( [] );
	} );
}

/** Records every text the result count shows from the first paint on, in `window.pixonautShopCounts`. */
async function recordCounts( page: Page ): Promise< void > {
	await page.addInitScript( ( selector ) => {
		const seen: string[] = [];
		( window as unknown as { pixonautShopCounts: string[] } ).pixonautShopCounts = seen;
		const note = () => {
			const text = document.querySelector( selector )?.textContent?.trim();

			if ( text && seen.at( -1 ) !== text ) {
				seen.push( text );
			}
		};
		new MutationObserver( note ).observe( document, {
			subtree: true,
			childList: true,
			characterData: true,
		} );
	}, RESULT_COUNT );
}

test( 'the result count reads the same from the first paint, with or without JavaScript, as once the browser takes over', async ( {
	browser,
	page,
} ) => {
	const addresses: Record< string, [ string, string ] > = {
		unfiltered: [ '', '2 products' ],
		'one value': [ `?filter_${ fixture.framingSlug }=${ fixture.frameSlug }`, '1 product' ],
		'several values of one attribute': [
			`?filter_${ fixture.framingSlug }=${ fixture.frameSlug },${ fixture.noFrameSlug }&query_type_${ fixture.framingSlug }=or`,
			'2 products',
		],
		'two attributes': [
			`?filter_${ fixture.framingSlug }=${ fixture.frameSlug }&filter_${ fixture.frameColourSlug }=${ fixture.blackSlug }`,
			'1 product',
		],
	};
	const withoutScripts = await browser.newContext( { javaScriptEnabled: false } );
	await withIndex( await withoutScripts.newPage() );
	await withIndex( page );
	await recordCounts( page );

	for ( const [ name, [ query, expected ] ] of Object.entries( addresses ) ) {
		const server = await withoutScripts.newPage();
		await server.goto( fixture.path + query );
		await expect( server.locator( RESULT_COUNT ), `${ name }, with no JavaScript` ).toHaveText(
			expected,
		);
		await server.close();

		await openArchive( page, query );
		await expect( page.locator( RESULT_COUNT ), name ).toHaveText( expected );
		expect(
			await page.evaluate(
				() => ( window as unknown as { pixonautShopCounts: string[] } ).pixonautShopCounts,
			),
			`${ name }: the count never changes`,
		).toEqual( [ expected ] );
	}

	await withoutScripts.close();
} );

test( 'a chip and "Clear all" take filters away, and back and forward step through the choices', async ( {
	page,
} ) => {
	await withIndex( page );
	// WooCommerce's own name for the filter, which the server reads without the plugin's short names.
	await openArchive( page, `?filter_${ fixture.framingSlug }=${ fixture.frameSlug }` );
	await expect( page.locator( CARD ) ).toHaveCount( 1 );
	await expect( page.locator( CHIP ) ).toHaveCount( 1 );

	await page.locator( CHIP ).getByRole( 'button' ).click();
	await expect( page.locator( CARD ) ).toHaveCount( 2 );
	await expect( page.locator( CHIP ) ).toHaveCount( 0 );
	await expect( page ).not.toHaveURL( /\?./ );

	await page.goBack();
	await expect( page ).toHaveURL( new RegExp( `${ fixture.framingSlug }=${ fixture.frameSlug }` ) );
	await expect( page.locator( CARD ) ).toHaveCount( 1 );
	await expect( page.locator( CHIP ) ).toHaveCount( 1 );

	await page.goForward();
	await expect( page.locator( CARD ) ).toHaveCount( 2 );
	await expect( page.locator( 'html' ) ).toHaveAttribute( ACTIVE, '' );
} );

test( 'the sorting reorders the cards in place, and the address names it', async ( { page } ) => {
	await withIndex( page );
	await openArchive( page );
	const made = watchRequests( page );

	// The framed print's dearest variation costs more than the unframed print's only one.
	await page.locator( 'main .wc-block-catalog-sorting select' ).selectOption( 'price-desc' );
	await expect( page ).toHaveURL( /orderby=price-desc/ );
	await expect( page.locator( CARD ).first() ).toContainText( fixture.framed );
	await expect( page.locator( CARD ).last() ).toContainText( fixture.unframed );

	await page.locator( 'main .wc-block-catalog-sorting select' ).selectOption( 'menu_order' );
	await expect( page ).not.toHaveURL( /orderby/ );
	expect( made ).toEqual( [] );
} );

test.describe( 'with one product a page', () => {
	let kept = '';

	test.beforeAll( () => {
		kept = wpEval( `echo implode( ",", ${ TEST_SUPPORT }FilterFixtures::one_product_a_page() );` );
	} );

	test.afterAll( () => {
		wpEval( `${ TEST_SUPPORT }FilterFixtures::keep_page_size( ${ kept } );` );
	} );

	test( 'the grid loads the next cards as the shopper scrolls, and says so at its end, with no pages', async ( {
		page,
	} ) => {
		await withIndex( page );
		await openArchive( page );
		await expect( page.locator( 'main .wp-block-query-pagination' ) ).toBeHidden();

		await page.locator( 'main .pixonaut-shop-archive-feed' ).scrollIntoViewIfNeeded();
		await expect( page.locator( CARD ) ).toHaveCount( 2 );
		await expect( page.getByText( 'You have seen all 2 products' ) ).toBeVisible();
	} );
} );

test( 'without an index the archive filters on the server, as it always has', async ( {
	page,
} ) => {
	await withIndex( page, '404' );
	await page.goto( fixture.path );
	await openDrawer( page );
	const framing = await section( page, fixture.framing );
	await framing.getByRole( 'checkbox', { name: fixture.frame, exact: true } ).check();

	await expect( page ).toHaveURL( new RegExp( `${ fixture.framingSlug }=${ fixture.frameSlug }` ) );
	await expect( page.locator( 'html' ) ).not.toHaveAttribute( ACTIVE, '' );
	await expect( drawer( page ).getByRole( 'button', { name: /Show 1 product/ } ) ).toBeVisible();
} );
