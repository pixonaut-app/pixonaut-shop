import { expect, type RequestUtils, test } from '@wordpress/e2e-test-utils-playwright';

import { editPostWithPatterns } from './support/editor';

/**
 * The front page: a page made from the front page pattern and set as the
 * homepage (`tests/Support/ShopFixtures.php`), in the front page template.
 * Its hero, the categories and the popular products show on the site, and
 * the page opens in the page editor and the Site Editor without invalid
 * blocks. Its placeholders are found here by their English source strings.
 */

/** The page the global setup makes from the pattern. */
const FRONT_PAGE_SLUG = 'front-page';

/** The category the global setup makes, with a product in it. */
const CATEGORY_NAME = 'Personalised garments';

const HERO = '.pixonaut-shop-hero';
const CATEGORY_TILE = '.wp-block-pixonaut-shop-product-categories__tile';
const CARD = '.wc-block-product';

/** The block editors render the canvas inside this named iframe. */
const EDITOR_CANVAS_IFRAME = 'iframe[name="editor-canvas"]';

/** What the block parser logs for each block whose markup fails validation. */
const VALIDATION_FAILURE = 'Block validation failed';

interface Page {
	id: number;
}

test.describe( 'The front page', () => {
	let failures: string[] = [];

	test.beforeEach( ( { page } ) => {
		failures = [];
		page.on( 'console', ( message ) => {
			if ( message.text().includes( VALIDATION_FAILURE ) ) {
				failures.push( message.text() );
			}
		} );
	} );

	test( 'shows the hero, the categories and the popular products', async ( { page } ) => {
		await page.goto( '/' );

		const main = page.locator( 'main' );
		const hero = main.locator( HERO );

		await expect(
			hero.getByRole( 'heading', { level: 1, name: 'A heading about what you sell' } ),
		).toBeVisible();
		await expect( hero.getByRole( 'link', { name: 'Shop now' } ) ).toBeVisible();

		await expect( main.getByRole( 'heading', { name: 'Shop by category' } ) ).toBeVisible();
		await expect(
			main.locator( CATEGORY_TILE ).filter( { hasText: CATEGORY_NAME } ),
		).toBeVisible();

		await expect( main.getByRole( 'heading', { name: 'Popular right now' } ) ).toBeVisible();
		await expect( main.locator( CARD ).first() ).toBeVisible();
	} );

	test( 'opens in the page editor without invalid blocks', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		const id = await frontPageId( requestUtils );

		await editPostWithPatterns( admin, page, id );
		const canvas = page.frameLocator( EDITOR_CANVAS_IFRAME );

		await expect( canvas.getByText( 'A heading about what you sell' ) ).toBeVisible();
		await expect( canvas.getByText( 'Block contains unexpected or invalid content' ) ).toHaveCount(
			0,
		);
		expect( failures ).toEqual( [] );
	} );

	test( 'opens in the Site Editor without invalid blocks', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		const id = await frontPageId( requestUtils );

		await admin.visitSiteEditor( { postType: 'page', postId: String( id ), canvas: 'edit' } );
		const canvas = page.frameLocator( EDITOR_CANVAS_IFRAME );

		await expect( canvas.getByText( 'A heading about what you sell' ) ).toBeVisible();
		await expect( canvas.getByText( 'Block contains unexpected or invalid content' ) ).toHaveCount(
			0,
		);
		expect( failures ).toEqual( [] );
	} );
} );

/** The front page's ID, which the editors open it by. */
async function frontPageId( requestUtils: RequestUtils ): Promise< number > {
	const [ page ] = await requestUtils.rest< Page[] >( {
		path: '/wp/v2/pages',
		params: { slug: FRONT_PAGE_SLUG },
	} );

	return page.id;
}
