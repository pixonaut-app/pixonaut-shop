import type { Locator, Page } from '@playwright/test';
import { expect, test } from '@playwright/test';

import { TEST_SUPPORT, wpEval } from './support/wp-cli';

/**
 * The category carousel (`inc/Blocks/CategoryCarousel.php`,
 * `src/blocks/category-carousel/view.ts`): a row of round category pictures that scrolls by
 * itself, and arrow buttons that show while it can scroll their way and scroll it when pressed,
 * by mouse or keyboard. The page and its categories are `ShopFixtures::category_rows()`: one
 * row with more categories than fit, one with two.
 */

/** Every test is a visitor, with no admin bar over the page. */
test.use( { storageState: { cookies: [], origins: [] } } );

const DESKTOP = { width: 1280, height: 800 };

const ROOT = '.wp-block-pixonaut-shop-category-carousel';
const LIST = '.wp-block-pixonaut-shop-category-carousel__list';
const LINK = '.wp-block-pixonaut-shop-category-carousel__link';
const NEXT = '.wp-block-pixonaut-shop-category-carousel__arrow.is-next';

/** The headings `ShopFixtures::category_rows()` gives its rows. */
const MANY_HEADING = 'Many categories';
const FEW_HEADING = 'Few categories';

/** `ShopFixtures::ROWS_MANY`: the categories in the row that overflows. */
const MANY = 12;

const PREVIOUS_NAME = 'Previous categories';
const NEXT_NAME = 'Next categories';

let path = '';

test.beforeAll( () => {
	path = wpEval( `echo ${ TEST_SUPPORT }ShopFixtures::category_rows();` );
} );

/** The carousel under this heading. */
function carousel( page: Page, heading: string ): Locator {
	return page.locator( ROOT ).filter( { has: page.getByRole( 'heading', { name: heading } ) } );
}

function scrolled( row: Locator ): Promise< number > {
	return row.locator( LIST ).evaluate( ( list ) => Math.abs( list.scrollLeft ) );
}

/** Waits for the frame after the next, when the script has shown or hidden the arrows. */
function settled( page: Page ): Promise< void > {
	return page.evaluate(
		() =>
			new Promise< void >( ( resolve ) =>
				requestAnimationFrame( () => requestAnimationFrame( () => resolve() ) ),
			),
	);
}

test.beforeEach( async ( { page } ) => {
	await page.setViewportSize( DESKTOP );
} );

test( 'the next arrow scrolls an overflowing row, and the previous arrow brings it back', async ( {
	page,
} ) => {
	await page.goto( path );

	const row = carousel( page, MANY_HEADING );
	const previous = row.getByRole( 'button', { name: PREVIOUS_NAME } );
	const next = row.getByRole( 'button', { name: NEXT_NAME } );

	await expect( row.locator( LINK ) ).toHaveCount( MANY );
	await expect( next ).toBeVisible();
	await expect( previous ).toBeHidden();
	await expect( next ).toHaveAttribute(
		'aria-controls',
		( await row.locator( LIST ).getAttribute( 'id' ) ) ?? '',
	);

	await next.click();

	await expect.poll( () => scrolled( row ) ).toBeGreaterThan( 0 );
	await expect( previous ).toBeVisible();

	await previous.click();

	await expect.poll( () => scrolled( row ) ).toBe( 0 );
	await expect( previous ).toBeHidden();
} );

test( 'a row that fits shows no arrows', async ( { page } ) => {
	await page.goto( path );

	const row = carousel( page, FEW_HEADING );

	await expect( row.locator( LINK ) ).toHaveCount( 2 );
	// Both are in the page, hidden.
	await expect( row.locator( 'button' ) ).toHaveCount( 2 );
	await expect( row.getByRole( 'button' ) ).toHaveCount( 0 );
} );

test( 'the keyboard reaches every category and both arrows, which scroll the row', async ( {
	page,
} ) => {
	// Instant scrolls, so each press has settled before the next.
	await page.emulateMedia( { reducedMotion: 'reduce' } );
	await page.goto( path );

	const row = carousel( page, MANY_HEADING );
	const links = row.locator( LINK );
	const next = row.getByRole( 'button', { name: NEXT_NAME } );
	const previous = row.getByRole( 'button', { name: PREVIOUS_NAME } );

	// Tab walks the row, which scrolls each category into view as it takes focus.
	await links.first().focus();
	for ( let step = 1; step < MANY; step++ ) {
		await page.keyboard.press( 'Tab' );
	}
	await expect( links.last() ).toBeFocused();
	await expect( links.last() ).toBeInViewport( { ratio: 1 } );
	await expect( next ).toBeHidden();

	// The arrows come after the row.
	await page.keyboard.press( 'Tab' );
	await expect( previous ).toBeFocused();

	// Enter until the start: the arrow goes, and focus moves to the other one.
	while ( await previous.isVisible() ) {
		const before = await scrolled( row );
		await page.keyboard.press( 'Enter' );
		await expect.poll( () => scrolled( row ) ).toBeLessThan( before );
		await settled( page );
	}
	await expect( next ).toBeFocused();

	await page.keyboard.press( 'Space' );
	await expect.poll( () => scrolled( row ) ).toBeGreaterThan( 0 );
} );

test( 'with reduced motion an arrow scrolls at once', async ( { page } ) => {
	await page.emulateMedia( { reducedMotion: 'reduce' } );
	await page.goto( path );

	const row = carousel( page, MANY_HEADING );
	await expect( row.getByRole( 'button', { name: NEXT_NAME } ) ).toBeVisible();

	const afterClick = await row.evaluate(
		( root, [ list, next ] ) => {
			( root.querySelector( next ) as HTMLButtonElement ).click();
			// Read in the same task: a smooth scroll would not have moved yet.
			return ( root.querySelector( list ) as HTMLElement ).scrollLeft;
		},
		[ LIST, NEXT ],
	);

	expect( afterClick ).toBeGreaterThan( 0 );
} );

test.describe( 'without JavaScript', () => {
	test.use( { javaScriptEnabled: false } );

	test( 'the row scrolls by itself and no arrow shows', async ( { page } ) => {
		await page.goto( path );

		const row = carousel( page, MANY_HEADING );

		await expect( row.locator( LINK ) ).toHaveCount( MANY );
		await expect( row.getByRole( 'button' ) ).toHaveCount( 0 );
		const list = row.locator( LIST );
		expect( await list.evaluate( ( element ) => element.scrollWidth > element.clientWidth ) ).toBe(
			true,
		);
		await expect( list ).toHaveCSS( 'overflow-x', 'auto' );
	} );
} );
