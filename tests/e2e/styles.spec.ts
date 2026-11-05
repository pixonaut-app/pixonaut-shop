import type { Frame, Page } from '@playwright/test';
import { expect, test } from '@wordpress/e2e-test-utils-playwright';

/**
 * The theme's default look reaches the browser: Inter, served from the
 * theme's own files, on the front end and in the Site Editor's canvas, and
 * the custom properties Pixonaut for WooCommerce styles its designer with.
 */

/** wp-env maps the checkout under the theme's fixed slug. */
const THEME_SLUG = 'pixonaut-shop';

const TYPEFACE = 'Inter';

/** Where the theme serves its typeface from. */
const FONT_PATH = `/wp-content/themes/${ THEME_SLUG }/assets/fonts/inter/`;

/** A page every store has, rendered by the theme's own template. */
const SHOP_PATH = '/shop/';

/** The Site Editor renders the template canvas inside this named iframe. */
const EDITOR_CANVAS_IFRAME = 'iframe[name="editor-canvas"]';

/** The custom properties Pixonaut for WooCommerce reads, on every page. */
const CONTRACT_PROPERTIES = [
	...[
		'accent',
		'accent-contrast',
		'highlight',
		'highlight-contrast',
		'border',
		'border-strong',
		'error',
		'success',
		'contrast',
		'muted',
		'base',
		'surface',
	].map( ( slug ) => `--wp--preset--color--${ slug }` ),
	'--wp--preset--font-family--body',
	'--wp--preset--font-family--heading',
	'--wp--custom--radius--small',
	'--wp--custom--radius--large',
	'--wp--preset--shadow--card',
];

/** The body's font-family stack, and whether the typeface has loaded. */
async function bodyTypeface( target: Page | Frame ) {
	return target.evaluate( async ( typeface ) => {
		await document.fonts.ready;

		return {
			family: getComputedStyle( document.body ).fontFamily,
			// `document.fonts.check()` is true too when no face of that
			// name exists at all, so look for a loaded face by name.
			loaded: [ ...document.fonts ].some(
				( face ) => face.family.replace( /"/g, '' ) === typeface && face.status === 'loaded',
			),
		};
	}, TYPEFACE );
}

test( 'the front end sets Inter from the theme and every custom property the plugin reads', async ( {
	page,
} ) => {
	const fontRequests: string[] = [];
	page.on( 'request', ( request ) => {
		if ( request.url().endsWith( '.woff2' ) ) {
			fontRequests.push( new URL( request.url() ).pathname );
		}
	} );

	await page.goto( SHOP_PATH );

	const { family, loaded } = await bodyTypeface( page );
	expect( family.startsWith( `${ TYPEFACE },` ) || family.startsWith( `"${ TYPEFACE }",` ) ).toBe(
		true,
	);
	expect( loaded ).toBe( true );
	expect( fontRequests.length ).toBeGreaterThan( 0 );
	expect( fontRequests.every( ( path ) => path.startsWith( FONT_PATH ) ) ).toBe( true );

	const missing = await page.evaluate( ( properties ) => {
		const root = getComputedStyle( document.documentElement );

		return properties.filter( ( property ) => root.getPropertyValue( property ).trim() === '' );
	}, CONTRACT_PROPERTIES );
	expect( missing ).toEqual( [] );
} );

test( 'the Site Editor canvas sets Inter too', async ( { admin, page } ) => {
	await admin.visitSiteEditor( {
		postType: 'wp_template',
		postId: `${ THEME_SLUG }//page`,
		canvas: 'edit',
	} );

	const iframe = page.locator( EDITOR_CANVAS_IFRAME );
	await expect( iframe ).toBeVisible();
	// The canvas's own document carries the editor styles, not the admin page.
	await expect( iframe.contentFrame().locator( 'body.editor-styles-wrapper' ) ).toBeVisible();
	const canvas = await ( await iframe.elementHandle() )?.contentFrame();

	if ( ! canvas ) {
		throw new Error( 'The Site Editor has no canvas.' );
	}

	const { family, loaded } = await bodyTypeface( canvas );
	expect( family ).toContain( TYPEFACE );
	expect( loaded ).toBe( true );
} );
