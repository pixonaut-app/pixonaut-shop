import type { Page } from '@playwright/test';
import { expect, test } from '@wordpress/e2e-test-utils-playwright';

/**
 * The header's menu collapses into the menu button well before core's own
 * 600px breakpoint, at the width set in `src/styles/_header.css`, so it
 * never wraps or crowds the title and the icons.
 */

/** A width at which the menu has collapsed: a small laptop or a tablet held sideways. */
const COLLAPSED = { width: 1024, height: 768 };

/** The width the design is drawn at, where the menu sits inline on one line. */
const INLINE = { width: 1440, height: 900 };

/** As many items as a store's main menu is drawn with, and some longer than one word. */
const ITEMS = [ 'Posters', 'Clothing', 'Gifts', 'Business', 'Gift cards', 'About us' ];

const MENU = '.pixonaut-shop-header__menu';

let menuId: number | undefined;

test.beforeAll( async ( { requestUtils } ) => {
	// The header's menu names no saved menu, so core shows the most recently
	// published one: this one, for the length of the spec.
	const menu = await requestUtils.createNavigationMenu( {
		title: 'Header spec menu',
		content: ITEMS.map(
			( label ) =>
				`<!-- wp:navigation-link {"label":"${ label }","url":"/#${ encodeURIComponent( label ) }","kind":"custom"} /-->`,
		).join( '' ),
	} );
	menuId = ( menu as { id: number } ).id;
} );

test.afterAll( async ( { requestUtils } ) => {
	if ( menuId !== undefined ) {
		await requestUtils.rest( {
			method: 'DELETE',
			path: `/wp/v2/navigation/${ menuId }`,
			params: { force: true },
		} );
	}
} );

test( 'below the menu breakpoint the menu is behind the menu button', async ( { page } ) => {
	await page.setViewportSize( COLLAPSED );
	await page.goto( '/' );

	const header = page.locator( 'header' );
	const toggle = header.locator( `${ MENU } .wp-block-navigation__responsive-container-open` );

	await expect( toggle ).toBeVisible();
	await expect( header.getByRole( 'link', { name: ITEMS[ 0 ], exact: true } ) ).toBeHidden();

	await toggle.click();

	await expect( header.getByRole( 'link', { name: ITEMS[ 0 ], exact: true } ) ).toBeVisible();
} );

test( 'at the design width the menu is inline, on one line', async ( { page } ) => {
	await page.setViewportSize( INLINE );
	await page.goto( '/' );

	const header = page.locator( 'header' );

	await expect(
		header.locator( `${ MENU } .wp-block-navigation__responsive-container-open` ),
	).toBeHidden();

	const tops = await Promise.all(
		ITEMS.map( async ( label ) => {
			const link = header.getByRole( 'link', { name: label, exact: true } );
			await expect( link ).toBeVisible();
			return ( await link.boundingBox() )?.y;
		} ),
	);

	expect( new Set( tops ).size ).toBe( 1 );
} );

/**
 * The header's search is core's search block in its icon-only form: its
 * button opens the field, and closes it on Escape or when focus leaves it.
 * The field opens over the header (`src/styles/_header.css`) and an empty
 * search keeps it open (`src/header/search.ts`).
 */
const SEARCH = '.pixonaut-shop-header .wp-block-search';
const SEARCH_BUTTON = `${ SEARCH } .wp-block-search__button`;
const SEARCH_FIELD = `${ SEARCH } .wp-block-search__input`;

/** The row's items besides the search: the logo, title, menu, account and mini-cart. */
const ROW_ITEMS = '.pixonaut-shop-header__row > :not( .wp-block-search )';

/** The design's width, a laptop's, where the menu has collapsed, and a phone's. */
const SEARCH_VIEWPORTS = [ INLINE, { width: 1280, height: 800 }, { width: 390, height: 844 } ];

for ( const viewport of SEARCH_VIEWPORTS ) {
	test( `opening and closing the search moves nothing else in the header at ${ viewport.width }px`, async ( {
		page,
	} ) => {
		await page.setViewportSize( viewport );
		await page.goto( '/' );

		const before = await rowItemBoxes( page );

		await page.locator( SEARCH_BUTTON ).click();
		await expect( page.locator( SEARCH_FIELD ) ).toBeFocused();
		await expect( page.locator( SEARCH_FIELD ) ).toBeVisible();
		await settled( page );
		await expect.poll( () => rowItemBoxes( page ) ).toEqual( before );

		const field = await page.locator( SEARCH_FIELD ).boundingBox();
		expect( field?.x ).toBeGreaterThanOrEqual( 0 );
		expect( ( field?.x ?? 0 ) + ( field?.width ?? 0 ) ).toBeLessThanOrEqual( viewport.width );

		await page.keyboard.press( 'Escape' );
		await expect( page.locator( SEARCH_FIELD ) ).toBeHidden();
		expect( await rowItemBoxes( page ) ).toEqual( before );
	} );
}

test( 'closing the search leaves no frame behind', async ( { page } ) => {
	await page.goto( '/' );
	await page.locator( SEARCH_BUTTON ).click();
	await expect( page.locator( SEARCH_FIELD ) ).toBeVisible();
	await settled( page );

	await page.keyboard.press( 'Escape' );

	// Two frames after Escape: the field is gone at once, not shrinking away.
	const shown = await page.locator( SEARCH_FIELD ).evaluate(
		( field ) =>
			new Promise< boolean >( ( resolve ) =>
				requestAnimationFrame( () =>
					requestAnimationFrame( () => {
						const { width } = field.getBoundingClientRect();
						resolve( width > 0 && getComputedStyle( field ).visibility !== 'hidden' );
					} ),
				),
			),
	);

	expect( shown ).toBe( false );
} );

test( 'an empty search keeps the field open, without the browser’s validation message', async ( {
	page,
} ) => {
	await page.goto( '/' );
	await page.locator( SEARCH_BUTTON ).click();

	const field = page.locator( SEARCH_FIELD );
	await expect( field ).toBeFocused();
	await field.evaluate( ( input ) => {
		input.addEventListener( 'invalid', () => input.setAttribute( 'data-invalid', '' ) );
	} );
	const url = page.url();

	await page.locator( SEARCH_BUTTON ).click();

	await expect( field ).toBeFocused();
	await expect( field ).toBeVisible();
	await expect( field ).not.toHaveAttribute( 'data-invalid' );
	expect( page.url() ).toBe( url );
} );

test( 'a search with words in it searches the products', async ( { page } ) => {
	await page.goto( '/' );
	await page.locator( SEARCH_BUTTON ).click();
	await page.locator( SEARCH_FIELD ).fill( 'tee' );
	await page.locator( SEARCH_BUTTON ).click();

	await expect( page ).toHaveURL( /[?&]s=tee(&|$)/ );
	await expect( page ).toHaveURL( /[?&]post_type=product(&|$)/ );
} );

/** Where each header item other than the search sits. */
function rowItemBoxes( page: Page ) {
	return page.locator( ROW_ITEMS ).evaluateAll( ( items ) =>
		items.map( ( item ) => {
			const { x, y, width, height } = item.getBoundingClientRect();
			return { x, y, width, height };
		} ),
	);
}

/** Waits for the search field's transitions to finish. */
async function settled( page: Page ): Promise< void > {
	await page
		.locator( SEARCH_FIELD )
		.evaluate( ( field ) =>
			Promise.all( field.getAnimations().map( ( { finished } ) => finished ) ),
		);
}
