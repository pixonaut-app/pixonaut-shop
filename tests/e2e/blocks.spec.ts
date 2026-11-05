import { expect, test } from '@wordpress/e2e-test-utils-playwright';

import { editPostWithPatterns } from './support/editor';

/**
 * The theme's own blocks in the block editor: the product categories block
 * shows the store's categories in the canvas, and the icon block and the
 * promises pattern built from it open without invalid blocks, and an icon
 * in a circle fills the circle in the colour chosen for it.
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
} );
