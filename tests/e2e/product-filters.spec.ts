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
};

test.beforeAll( () => {
	const [ slug, framed, unframed, framing, frame, framingSlug, frameSlug ] = wpEval(
		`${ TEST_SUPPORT }FilterFixtures::prepare(); ` +
			[
				'CATEGORY_SLUG',
				'FRAMED_PRODUCT',
				'UNFRAMED_PRODUCT',
				'FRAMING',
				'FRAME',
				'FRAMING_SLUG',
				'FRAME_SLUG',
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
	};
} );

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

test.describe( 'on a phone', () => {
	test.use( { viewport: devices[ 'iPhone 13' ].viewport } );

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
