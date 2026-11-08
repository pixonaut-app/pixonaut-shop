import type { BrowserContext, Page } from '@playwright/test';
import { expect, test } from '@playwright/test';

import { TEST_SUPPORT, wpEval } from './support/wp-cli';

/**
 * The mega header (`inc/Header/MegaMenu.php`, `src/header/mega-menu.ts`):
 * the menu button opens a panel whose groups are the menu's top items, and a
 * group's categories show beside them as picture tiles; on a phone the panel
 * is a drawer whose groups open under their buttons. The menu is
 * `ShopFixtures::mega_menu()`, shown to this spec's visitor alone through a
 * cookie (`tests/e2e/mega-menu.php`), so the other specs keep the simple
 * header.
 */

/** Every test is a visitor, with no admin bar over the header, on a screen that also takes taps. */
test.use( { storageState: { cookies: [], origins: [] }, hasTouch: true } );

/** The cookie `tests/e2e/mega-menu.php` reads. */
const MEGA_MENU_COOKIE = 'pixonaut_shop_tests_mega_menu';

const DESKTOP = { width: 1440, height: 900 };
const PHONE = { width: 390, height: 844 };

const ROOT = '.pixonaut-shop-mega';
const TOGGLE = '.pixonaut-shop-mega__toggle';
const PANEL = '#pixonaut-shop-mega-panel';
const TILE_NAMES = '.pixonaut-shop-mega__children:not( [hidden] ) .pixonaut-shop-mega__name';
const ROW_ITEMS = '.pixonaut-shop-header__row > *';

/** The fixture's groups, in the menu's order, and the categories under each. */
const OCCASION = 'Occasion';
const FOR_WHOM = 'For whom';
const OCCASION_TILES = [ 'Birthday', 'Wedding' ];
const FOR_WHOM_TILES = [ 'For her', 'Without a picture' ];

let menuId = '';

test.beforeAll( () => {
	menuId = wpEval( `echo ${ TEST_SUPPORT }ShopFixtures::mega_menu();` );
} );

test.beforeEach( async ( { context, baseURL } ) => {
	await showMegaMenu( context, baseURL ?? '' );
} );

test( 'the menu button opens the panel at its first group, and Escape closes it', async ( {
	page,
} ) => {
	await page.setViewportSize( DESKTOP );
	await page.goto( '/' );

	const toggle = page.locator( TOGGLE );
	await expect( toggle ).toHaveAttribute( 'aria-expanded', 'false' );
	await expect( toggle ).toHaveAttribute( 'aria-controls', 'pixonaut-shop-mega-panel' );
	await expect( page.locator( PANEL ) ).toBeHidden();

	await toggle.click();

	await expect( toggle ).toHaveAttribute( 'aria-expanded', 'true' );
	await expect( page.locator( PANEL ) ).toBeVisible();
	await expect( group( page, OCCASION ) ).toBeFocused();
	await expect( page.locator( TILE_NAMES ) ).toHaveText( OCCASION_TILES );

	await page.keyboard.press( 'Escape' );

	await expect( page.locator( PANEL ) ).toBeHidden();
	await expect( toggle ).toHaveAttribute( 'aria-expanded', 'false' );
	await expect( toggle ).toBeFocused();
} );

test( 'pointing at a group shows its categories as picture tiles', async ( { page } ) => {
	await page.setViewportSize( DESKTOP );
	await page.goto( '/' );
	await page.locator( TOGGLE ).click();

	await group( page, FOR_WHOM ).hover();

	await expect( group( page, FOR_WHOM ) ).toHaveAttribute( 'aria-expanded', 'true' );
	await expect( group( page, OCCASION ) ).toHaveAttribute( 'aria-expanded', 'false' );
	await expect( page.locator( TILE_NAMES ) ).toHaveText( FOR_WHOM_TILES );

	const picture = page.getByRole( 'link', { name: 'For her' } ).locator( 'img' );
	await expect( picture ).toHaveAttribute( 'loading', 'lazy' );
	await expect( picture ).toHaveAttribute( 'width', /^\d+$/ );
	await expect( picture ).toHaveAttribute( 'height', /^\d+$/ );
	await expect(
		page.getByRole( 'link', { name: 'Without a picture' } ).locator( 'img' ),
	).toHaveCount( 0 );
} );

test( 'the arrow keys move between the groups, which open as they take focus', async ( {
	page,
} ) => {
	await page.setViewportSize( DESKTOP );
	await page.goto( '/' );
	await page.locator( TOGGLE ).focus();
	await page.keyboard.press( 'Enter' );
	await expect( group( page, OCCASION ) ).toBeFocused();

	await page.keyboard.press( 'ArrowDown' );

	await expect( group( page, FOR_WHOM ) ).toBeFocused();
	await expect( page.locator( TILE_NAMES ) ).toHaveText( FOR_WHOM_TILES );

	await page.keyboard.press( 'ArrowUp' );

	await expect( group( page, OCCASION ) ).toBeFocused();
	await expect( page.locator( TILE_NAMES ) ).toHaveText( OCCASION_TILES );
} );

test( 'Tab stays in the open panel, round from its last link to the menu button', async ( {
	page,
} ) => {
	await page.setViewportSize( DESKTOP );
	await page.goto( '/' );
	await page.locator( TOGGLE ).click();

	// More presses than the panel has links and buttons, so focus has gone round.
	const presses =
		( await page.locator( `${ ROOT } a:visible, ${ ROOT } button:visible` ).count() ) + 2;

	for ( let press = 0; press < presses; press++ ) {
		await page.keyboard.press( 'Tab' );
		expect( await focusIsInMegaMenu( page ) ).toBe( true );
	}

	await page.locator( TOGGLE ).focus();
	await page.keyboard.press( 'Shift+Tab' );
	expect( await focusIsInMegaMenu( page ) ).toBe( true );
	await expect( page.locator( TOGGLE ) ).not.toBeFocused();
} );

test( 'opening the panel moves nothing in the header', async ( { page } ) => {
	await page.setViewportSize( DESKTOP );
	await page.goto( '/' );
	const before = await rowItemBoxes( page );

	await page.locator( TOGGLE ).click();
	await expect( page.locator( PANEL ) ).toBeVisible();

	expect( await rowItemBoxes( page ) ).toEqual( before );
} );

test( 'on a phone the panel is a drawer whose groups open under their buttons', async ( {
	page,
} ) => {
	await page.setViewportSize( PHONE );
	await page.goto( '/' );

	await page.locator( TOGGLE ).tap();

	await expect( page.locator( PANEL ) ).toBeVisible();
	await expect( group( page, OCCASION ) ).toHaveAttribute( 'aria-expanded', 'false' );
	await expect( page.locator( TILE_NAMES ) ).toHaveCount( 0 );

	await group( page, OCCASION ).tap();

	await expect( group( page, OCCASION ) ).toHaveAttribute( 'aria-expanded', 'true' );
	await expect( page.locator( TILE_NAMES ) ).toHaveText( OCCASION_TILES );
	const birthday = await page.getByRole( 'link', { name: 'Birthday' } ).boundingBox();
	const forWhom = await group( page, FOR_WHOM ).boundingBox();
	expect( birthday?.y ).toBeLessThan( forWhom?.y ?? 0 );

	await group( page, OCCASION ).tap();

	await expect( page.locator( TILE_NAMES ) ).toHaveCount( 0 );

	await page.getByRole( 'button', { name: 'Close menu' } ).tap();

	await expect( page.locator( PANEL ) ).toBeHidden();
	await expect( page.locator( TOGGLE ) ).toBeFocused();
} );

test( 'on a phone the keyboard opens a group in the drawer', async ( { page } ) => {
	await page.setViewportSize( PHONE );
	await page.goto( '/' );
	await page.locator( TOGGLE ).focus();
	await page.keyboard.press( 'Enter' );
	await expect( group( page, OCCASION ) ).toBeFocused();

	await page.keyboard.press( 'Enter' );

	await expect( page.locator( TILE_NAMES ) ).toHaveText( OCCASION_TILES );
} );

test( 'without the mega header the header keeps its menu in the row', async ( {
	page,
	context,
} ) => {
	await context.clearCookies();
	await page.goto( '/' );

	await expect( page.locator( ROOT ) ).toHaveCount( 0 );
	await expect( page.locator( 'nav.pixonaut-shop-header__menu' ) ).toHaveCount( 1 );
} );

/** The header shows the fixture's menu as the mega header's, to this test's visitor. */
async function showMegaMenu( context: BrowserContext, baseURL: string ): Promise< void > {
	await context.addCookies( [ { name: MEGA_MENU_COOKIE, value: menuId, url: baseURL } ] );
}

function group( page: Page, name: string ) {
	return page.locator( PANEL ).getByRole( 'button', { name, exact: true } );
}

function focusIsInMegaMenu( page: Page ): Promise< boolean > {
	return page.evaluate( ( root ) => !! document.activeElement?.closest( root ), ROOT );
}

/** Where each item of the header's row sits. */
function rowItemBoxes( page: Page ) {
	return page.locator( ROW_ITEMS ).evaluateAll( ( items ) =>
		items.map( ( item ) => {
			const { x, y, width, height } = item.getBoundingClientRect();
			return { x, y, width, height };
		} ),
	);
}
