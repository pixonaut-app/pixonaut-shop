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
