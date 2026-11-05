import { expect, test } from '@wordpress/e2e-test-utils-playwright';

import { NOT_FOUND } from './support/http-status';

/**
 * The 404 template's content (`patterns/not-found.php`): a panel that says
 * what happened, in the theme's own words, found here by their English
 * source strings, with the product search and the way back, and the
 * store's categories below it.
 */

/** An address no page has. */
const MISSING_PATH = '/no-such-page-here/';

const HEADING = 'Page not found';

const HOME_LINK = 'Go to the home page';

const CATEGORIES_HEADING = 'Shop by category';

/** The status code the panel's pill shows. */
const STATUS_PILL = '404';

/**
 * A category made for this spec, with one product so the categories show
 * it, named apart from one another worker makes at the same time.
 */
const CATEGORY_NAME = 'Not found spec category';

interface Created {
	id: number;
}

test.describe( 'The 404 page', () => {
	/** What a test made through the REST API, deleted after it: each path takes `?force=true`. */
	let made: string[] = [];

	test.beforeEach( () => {
		made = [];
	} );

	test.afterEach( async ( { requestUtils } ) => {
		for ( const path of made.reverse() ) {
			await requestUtils.rest( { path, method: 'DELETE', params: { force: true } } );
		}
	} );

	test( 'a missing address answers 404, says so in a panel, and offers the search and the home page', async ( {
		page,
	} ) => {
		const response = await page.goto( MISSING_PATH );

		expect( response?.status() ).toBe( NOT_FOUND );

		const panel = page.locator( 'main .is-style-panel' );

		await expect( page.locator( 'h1' ) ).toHaveCount( 1 );
		await expect( panel.locator( '.is-style-pill' ) ).toHaveText( STATUS_PILL );
		await expect( panel.locator( 'h1' ) ).toHaveText( HEADING );
		await expect( panel.getByRole( 'searchbox' ) ).toBeVisible();

		const home = panel.getByRole( 'link', { name: HOME_LINK } );
		await expect( home ).toBeVisible();

		await home.click();
		await expect( page ).toHaveURL( /\/$/ );
	} );

	test( "it shows the store's categories below the panel", async ( {
		page,
		requestUtils,
	}, testInfo ) => {
		const name = `${ CATEGORY_NAME } ${ testInfo.parallelIndex }`;
		const category = await requestUtils.rest< Created >( {
			path: '/wc/v3/products/categories',
			method: 'POST',
			data: { name },
		} );
		made.push( `/wc/v3/products/categories/${ category.id }` );

		const product = await requestUtils.rest< Created >( {
			path: '/wc/v3/products',
			method: 'POST',
			data: {
				name: `${ name } product`,
				status: 'publish',
				categories: [ { id: category.id } ],
			},
		} );
		made.push( `/wc/v3/products/${ product.id }` );

		await page.goto( MISSING_PATH );

		const categories = page.locator( 'main .pixonaut-shop-not-found-categories' );

		await expect( categories.getByRole( 'heading', { name: CATEGORIES_HEADING } ) ).toBeVisible();
		await expect(
			categories.locator( '.wp-block-pixonaut-shop-product-categories__name', { hasText: name } ),
		).toBeVisible();
	} );
} );
