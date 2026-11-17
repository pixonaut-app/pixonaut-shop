import { expect, test } from '@playwright/test';

import { TEST_SUPPORT, wpEval } from './support/wp-cli';

/**
 * The swatches on a card of a design the archive shows in several chosen
 * values (`inc/Archive/CardSwatches.php`, `src/archive/card-swatches.ts`),
 * as the fake plugin names them while a suite sets its cookie
 * (`fake-pixonaut.php`). The fixture is a category of two prints
 * (`FilterFixtures`).
 */

const SWATCHES_COOKIE = 'fake_card_swatches';
const PICTURE = { srcset: '', width: 600, height: 600 };

let fixture: { path: string; framed: string };

test.beforeAll( () => {
	const [ slug, framed ] = wpEval(
		`${ TEST_SUPPORT }FilterFixtures::prepare(); echo ${ TEST_SUPPORT }FilterFixtures::CATEGORY_SLUG, "\\n", ${ TEST_SUPPORT }FilterFixtures::FRAMED_PRODUCT, "\\n";`,
	).split( '\n' );
	fixture = { path: `/product-category/${ slug }/`, framed };
} );

test.afterAll( () => {
	wpEval( `${ TEST_SUPPORT }FilterFixtures::forget();` );
} );

for ( const viewport of [
	{ width: 1280, height: 800 },
	{ width: 390, height: 844 },
] ) {
	test( `choosing a swatch shows the design in its value, picture and link, in place, at ${ viewport.width }px`, async ( {
		page,
	} ) => {
		await page.setViewportSize( viewport );
		const base = process.env.WP_BASE_URL ?? '';
		await page.context().addCookies( [
			{
				name: SWATCHES_COOKIE,
				value: encodeURIComponent(
					JSON.stringify( {
						[ fixture.framed ]: [
							{
								values: [ { key: 'framing', id: 'hanger', label: 'Hanger' } ],
								url: `${ base }/?hanger`,
								image: { src: `${ base }/hanger.png`, ...PICTURE },
								current: true,
							},
							{
								values: [ { key: 'framing', id: 'frame', label: 'Frame' } ],
								url: `${ base }/?frame`,
								image: { src: `${ base }/frame.png`, ...PICTURE },
							},
						],
					} ),
				),
				url: base,
			},
		] );
		await page.goto( fixture.path );

		const card = page.locator( 'main .wc-block-product', { hasText: fixture.framed } );
		const swatches = card.getByRole( 'group', { name: `Versions of ${ fixture.framed }` } );
		await expect( swatches.getByRole( 'button', { name: 'Hanger' } ) ).toHaveAttribute(
			'aria-pressed',
			'true',
		);

		const made: string[] = [];
		page.on( 'request', ( request ) => {
			if ( request.resourceType() === 'document' || request.resourceType() === 'fetch' ) {
				made.push( request.url() );
			}
		} );
		await swatches.getByRole( 'button', { name: 'Frame' } ).click();

		await expect( swatches.getByRole( 'button', { name: 'Frame' } ) ).toHaveAttribute(
			'aria-pressed',
			'true',
		);
		await expect( swatches.getByRole( 'button', { name: 'Hanger' } ) ).toHaveAttribute(
			'aria-pressed',
			'false',
		);
		await expect( card.locator( '.wp-block-post-title a' ) ).toHaveAttribute(
			'href',
			`${ base }/?frame`,
		);
		await expect(
			card.locator( '.wp-block-woocommerce-product-image img' ).first(),
		).toHaveAttribute( 'src', `${ base }/frame.png` );
		expect( made ).toEqual( [] );
	} );
}
