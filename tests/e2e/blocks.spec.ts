import { expect, test } from '@wordpress/e2e-test-utils-playwright';

import { editPostWithPatterns } from './support/editor';
import { TEST_SUPPORT, wpEval } from './support/wp-cli';

/**
 * The theme's own blocks in the block editor: the product categories block
 * shows the store's categories in the canvas, and the icon block and the
 * promises pattern built from it open without invalid blocks, an icon
 * in a circle fills the circle in the colour chosen for it, and the group
 * tiles and the category carousel come from the inserter and show the
 * category picked for them.
 */

/** The block editor renders the post canvas inside this named iframe. */
const EDITOR_CANVAS_IFRAME = 'iframe[name="editor-canvas"]';

/** What the block parser logs for each block whose markup fails validation. */
const VALIDATION_FAILURE = 'Block validation failed';

/**
 * A category made for this spec, with one product so the block shows it,
 * named apart from one another worker makes at the same time.
 */
const CATEGORY_NAME = 'Blocks spec category';

/** The icon block's circle, `--ps-icon-circle-size` (3rem) at the browser's 16px root size. */
const ICON_CIRCLE_PX = 48;

/** The palette slug given the coloured icon, any but the circle's own accent. */
const ICON_TEXT_COLOR = 'error';

/** The four promises the pattern starts a store with. */
const PROMISE_COUNT = 4;

/**
 * The group `ShopFixtures::category_rows()` makes, its first subcategory, and the label the
 * category picker gives that group, a top-level category.
 */
const ROWS_GROUP_NAME = 'Carousel spec';
const ROWS_FIRST_NAME = 'Carousel spec 1';

/** A category slug no store has. */
const UNKNOWN_SLUG = 'no-such-category';

interface Created {
	id: number;
}

test.describe( "The theme's blocks", () => {
	let failures: string[] = [];

	/** What a test made through the REST API, deleted after it: each path takes `?force=true`. */
	let made: string[] = [];

	test.beforeEach( ( { page } ) => {
		failures = [];
		made = [];
		page.on( 'console', ( message ) => {
			if ( message.text().includes( VALIDATION_FAILURE ) ) {
				failures.push( message.text() );
			}
		} );
	} );

	test.afterEach( async ( { page, requestUtils } ) => {
		for ( const path of made.reverse() ) {
			await requestUtils.rest( { path, method: 'DELETE', params: { force: true } } );
		}

		const canvas = page.frameLocator( EDITOR_CANVAS_IFRAME );

		await expect( canvas.getByText( 'Block contains unexpected or invalid content' ) ).toHaveCount(
			0,
		);
		expect( failures ).toEqual( [] );
	} );

	test( "the product categories block shows the store's categories in the canvas", async ( {
		admin,
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

		const { id } = await requestUtils.createPage( {
			title: 'Product categories',
			status: 'draft',
			content:
				'<!-- wp:pixonaut-shop/product-categories /-->' +
				'<!-- wp:pixonaut-shop/product-categories {"layout":"buttons"} /-->',
		} );
		made.push( `/wp/v2/pages/${ id }` );

		await admin.editPost( id );

		const canvas = page.frameLocator( EDITOR_CANVAS_IFRAME );
		// The whole name: worker 1's category would otherwise match worker 11's as well.
		const wholeName = new RegExp( `^${ name }$` );

		await expect(
			canvas.locator( '.wp-block-pixonaut-shop-product-categories__name', {
				hasText: wholeName,
			} ),
		).toBeVisible();
		await expect(
			canvas.locator( '.wp-block-button__link', { hasText: wholeName } ),
		).toBeVisible();
	} );

	test( 'the promises pattern shows an icon, a heading and a sentence per promise', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		const { id } = await requestUtils.createPage( {
			title: 'Promises',
			status: 'draft',
			content: '<!-- wp:pattern {"slug":"pixonaut-shop/promises"} /-->',
		} );
		made.push( `/wp/v2/pages/${ id }` );

		await editPostWithPatterns( admin, page, id );

		const canvas = page.frameLocator( EDITOR_CANVAS_IFRAME );

		await expect( canvas.locator( '.wp-block-pixonaut-shop-icon svg' ) ).toHaveCount(
			PROMISE_COUNT,
		);
		await expect( canvas.locator( 'h3', { hasText: 'A short promise' } ) ).toHaveCount(
			PROMISE_COUNT,
		);
		await expect( canvas.getByText( 'One sentence about it.' ) ).toHaveCount( PROMISE_COUNT );
	} );

	test( 'an icon in a circle takes the whole circle and the colour chosen for it', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		const { id } = await requestUtils.createPage( {
			title: 'Icons',
			status: 'draft',
			content:
				'<!-- wp:pixonaut-shop/icon {"circle":true} /-->' +
				`<!-- wp:pixonaut-shop/icon {"circle":true,"textColor":"${ ICON_TEXT_COLOR }"} /-->`,
		} );
		made.push( `/wp/v2/pages/${ id }` );

		await admin.editPost( id );

		const canvas = page.frameLocator( EDITOR_CANVAS_IFRAME );
		const icons = canvas.locator( '[data-type="pixonaut-shop/icon"]' );

		await expect( icons.locator( 'svg' ) ).toHaveCount( 2 );

		const plain = icons.first();
		const coloured = icons.last();

		expect( await plain.boundingBox() ).toMatchObject( {
			width: ICON_CIRCLE_PX,
			height: ICON_CIRCLE_PX,
		} );

		const svgColour = ( icon: typeof plain ) =>
			icon.locator( 'svg' ).evaluate( ( svg ) => getComputedStyle( svg ).color );
		const presetColour = ( slug: string ) =>
			plain.evaluate(
				( element, name ) =>
					getComputedStyle( element ).getPropertyValue( `--wp--preset--color--${ name }` ).trim(),
				slug,
			);
		const resolved = ( value: string ) =>
			plain.evaluate( ( element, colour ) => {
				const probe = element.ownerDocument.createElement( 'span' );
				probe.style.color = colour;
				element.ownerDocument.body.append( probe );
				const computed = getComputedStyle( probe ).color;
				probe.remove();
				return computed;
			}, value );

		expect( await svgColour( plain ) ).toBe( await resolved( await presetColour( 'accent' ) ) );
		expect( await svgColour( coloured ) ).toBe(
			await resolved( await presetColour( ICON_TEXT_COLOR ) ),
		);
	} );

	test( 'the group tiles and the category carousel come from the inserter and show the category picked', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		wpEval( `${ TEST_SUPPORT }ShopFixtures::category_rows();` );

		const { id } = await requestUtils.createPage( {
			title: 'Broad front page',
			status: 'draft',
			// Not empty, so the editor opens on the page rather than its starter patterns.
			content: '<!-- wp:paragraph --><p>Gifts for every occasion</p><!-- /wp:paragraph -->',
		} );
		made.push( `/wp/v2/pages/${ id }` );

		await admin.editPost( id );

		const canvas = page.frameLocator( EDITOR_CANVAS_IFRAME );
		const library = page.getByRole( 'region', { name: 'Block Library' } );
		const insert = async ( title: string ) => {
			// The inserter may stay open after the block before.
			if ( ! ( await library.isVisible() ) ) {
				await page.getByRole( 'button', { name: 'Block Inserter', exact: true } ).click();
			}
			await library.getByRole( 'searchbox', { name: 'Search' } ).fill( title );
			await library.getByRole( 'option', { name: title, exact: true } ).click();
		};

		await insert( 'Group tiles' );
		const tiles = canvas.locator( '[data-type="pixonaut-shop/group-tiles"]' );
		await tiles
			.getByRole( 'combobox', { name: 'Category' } )
			.selectOption( { label: ROWS_GROUP_NAME } );

		await expect( tiles.getByRole( 'heading', { name: ROWS_GROUP_NAME } ) ).toBeVisible();
		await expect(
			tiles.locator( '.wp-block-pixonaut-shop-group-tiles__name', {
				hasText: new RegExp( `^${ ROWS_FIRST_NAME }$` ),
			} ),
		).toBeVisible();

		await insert( 'Category carousel' );
		const carousel = canvas.locator( '[data-type="pixonaut-shop/category-carousel"]' );
		await expect(
			carousel.locator( '.wp-block-pixonaut-shop-category-carousel__name', {
				hasText: new RegExp( `^${ ROWS_GROUP_NAME }$` ),
			} ),
		).toBeVisible();

		// The sidebar's picker narrows the carousel to the group's subcategories.
		await page
			.getByRole( 'region', { name: 'Editor settings' } )
			.getByRole( 'combobox', { name: 'Category' } )
			.selectOption( { label: ROWS_GROUP_NAME } );
		await expect(
			carousel.locator( '.wp-block-pixonaut-shop-category-carousel__name', {
				hasText: new RegExp( `^${ ROWS_FIRST_NAME }$` ),
			} ),
		).toBeVisible();
	} );

	test( 'a category slug the store does not have says so in the editor and offers the picker', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		wpEval( `${ TEST_SUPPORT }ShopFixtures::category_rows();` );

		const { id } = await requestUtils.createPage( {
			title: 'Unknown category',
			status: 'draft',
			content:
				`<!-- wp:pixonaut-shop/group-tiles {"parent":"${ UNKNOWN_SLUG }"} /-->` +
				`<!-- wp:pixonaut-shop/category-carousel {"parent":"${ UNKNOWN_SLUG }"} /-->`,
		} );
		made.push( `/wp/v2/pages/${ id }` );

		await admin.editPost( id );

		const canvas = page.frameLocator( EDITOR_CANVAS_IFRAME );

		for ( const name of [ 'group-tiles', 'category-carousel' ] ) {
			const block = canvas.locator( `[data-type="pixonaut-shop/${ name }"]` );

			await expect(
				block.getByText( `No category with the slug "${ UNKNOWN_SLUG }" in this store.` ),
			).toBeVisible();
			await expect( block.getByRole( 'combobox', { name: 'Category' } ) ).toBeVisible();
		}

		// Picking one shows its subcategories.
		const tiles = canvas.locator( '[data-type="pixonaut-shop/group-tiles"]' );
		await tiles
			.getByRole( 'combobox', { name: 'Category' } )
			.selectOption( { label: ROWS_GROUP_NAME } );
		await expect(
			tiles.locator( '.wp-block-pixonaut-shop-group-tiles__name', {
				hasText: new RegExp( `^${ ROWS_FIRST_NAME }$` ),
			} ),
		).toBeVisible();
	} );
} );
