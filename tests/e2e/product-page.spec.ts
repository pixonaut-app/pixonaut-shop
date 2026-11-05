import { expect, type Locator, type Page, test } from '@playwright/test';
import {
	drawSdkPreview,
	PLUGIN_FRAME_DRAWN,
	PLUGIN_FRAME_MARK,
	PLUGIN_FRAME_STYLE,
	PLUGIN_MARK_HEIGHT_PX,
	PLUGIN_MARK_TARGET,
	PLUGIN_PHONE_MAX_WIDTH,
	PLUGIN_PHONE_PREVIEW_VH,
	PLUGIN_PINNED,
	PLUGIN_SIDE_TABS,
	PLUGIN_STOREFRONT_CSS,
	PLUGIN_THEME_LOOK,
	PLUGIN_VIEWS_STRIP,
	SDK_PHOTO_RATIOS,
} from './support/plugin-storefront';

/**
 * The single-product template leaves room for Pixonaut for WooCommerce's
 * designer. The tests site runs a fake of the plugin
 * (`tests/e2e/fake-pixonaut.php`) that prints a marker where the plugin
 * prints each part: its preview in place of the product gallery, and its form
 * inside the add-to-cart form. No Pixonaut account and no plugin needed.
 */

/** The products the global setup creates (`tests/Support/ShopFixtures.php`). */
const PRODUCT_PATH = '/product/fake-pixonaut-product/';
const PLAIN_PRODUCT_PATH = '/product/plain-product/';

const PREVIEW = '[data-fake-pixonaut="preview"]';
const FORM = '[data-fake-pixonaut="form"]';
const APPROVAL = '[data-fake-pixonaut="approval"]';

/** The plugin's note under its preview, and its approval box's parts. */
const PREVIEW_NOTE = '.pixonaut-preview__note';
/** One side's frame, and its preview and views, by the class and ids the plugin documents. */
const PREVIEW_FRAME = '.pixonaut-preview__frame';
const PREVIEW_SIDE = '#pixonaut-preview-front';
const PREVIEW_VIEWS = '#pixonaut-views-front';
const APPROVAL_BOX = '.pixonaut-approval__box';
const APPROVAL_ERROR = '.pixonaut-approval__error';

/** The plugin's English `preview_note` and `approval_required` labels. */
const PREVIEW_NOTE_LABEL = 'The preview updates as you type';
const APPROVAL_REQUIRED_LABEL =
	'Please tick "I approve the preview" before adding this product to your cart.';

/** WooCommerce's classic add-to-cart form, which the add-to-cart form block renders. */
const ADD_TO_CART_FORM = 'form.cart';

/** The price row's price, the target the plugin updates (`inc/Product/PriceRow.php`). */
const PRICE = '[data-pixonaut-price]';

const DESCRIPTION_PANEL = '.pixonaut-shop-product-description';
const RELATED_PRODUCTS = '.pixonaut-shop-related-products';

/** The colour a `theme.json` palette preset resolves to on the page, as computed styles report colours. */
async function presetColour( page: Page, slug: string ): Promise< string > {
	return page.evaluate( ( name ) => {
		const probe = document.createElement( 'span' );
		probe.style.color = `var( --wp--preset--color--${ name } )`;
		document.body.append( probe );
		const colour = getComputedStyle( probe ).color;
		probe.remove();
		return colour;
	}, slug );
}

/** Whether `first` comes before `second` in the document. */
async function precedes( first: Locator, second: Locator ): Promise< boolean > {
	const handle = await second.elementHandle();

	return first.evaluate(
		( node, other ) =>
			Boolean( node.compareDocumentPosition( other as Node ) & Node.DOCUMENT_POSITION_FOLLOWING ),
		handle,
	);
}

test( 'the product page prints the designer in place of the gallery and inside the add-to-cart form', async ( {
	page,
} ) => {
	await page.goto( PRODUCT_PATH );

	const main = page.locator( 'main' );

	await expect( main.locator( PREVIEW ) ).toHaveCount( 1 );
	await expect( main.locator( `${ ADD_TO_CART_FORM } ${ FORM }` ) ).toHaveCount( 1 );
	await expect( main.locator( `${ ADD_TO_CART_FORM } button[type="submit"]` ) ).toBeVisible();
} );

test( 'the product page shows the product it is for', async ( { page } ) => {
	await page.goto( PRODUCT_PATH );

	await expect( page.locator( 'main h1' ) ).toHaveText( 'Personalised tee' );
} );

test( 'the product page reads from the preview down to the related products', async ( {
	page,
} ) => {
	await page.goto( PRODUCT_PATH );

	const main = page.locator( 'main' );
	const order = [
		main.locator( PREVIEW ),
		main.locator( 'h1' ),
		main.locator( `${ ADD_TO_CART_FORM } ${ FORM }` ),
		main.locator( `${ ADD_TO_CART_FORM } ${ APPROVAL }` ),
		main.locator( `${ ADD_TO_CART_FORM } ${ PRICE }` ),
		main.locator( `${ ADD_TO_CART_FORM } input[name="quantity"]` ),
		main.locator( DESCRIPTION_PANEL ),
		main.locator( RELATED_PRODUCTS ),
	];

	for ( const part of order ) {
		await expect( part ).toHaveCount( 1 );
	}

	for ( let i = 1; i < order.length; i++ ) {
		expect(
			await precedes( order[ i - 1 ], order[ i ] ),
			`part ${ i } comes after part ${ i - 1 }`,
		).toBe( true );
	}
} );

test( 'the price shares the last row with the quantity and the button', async ( { page } ) => {
	await page.goto( PRODUCT_PATH );

	const row = page.locator( `${ ADD_TO_CART_FORM } .pixonaut-shop-buy-row` );

	await expect( row.locator( PRICE ) ).toContainText( '249' );
	await expect( row.locator( 'input[name="quantity"]' ) ).toBeVisible();
	await expect( row.locator( 'button[type="submit"]' ) ).toBeVisible();
	await expect(
		page.locator( '.pixonaut-shop-product .wp-block-woocommerce-product-price' ),
	).toHaveCount( 0 );
} );

test( 'the price the plugin rewrites is the only target in the add-to-cart form', async ( {
	page,
} ) => {
	await page.goto( PRODUCT_PATH );

	// The plugin writes the chosen size's price into every target in the form
	// and falls back to the summary's price only when there is none.
	await expect( page.locator( `${ ADD_TO_CART_FORM } ${ PRICE }` ) ).toHaveCount( 1 );
	await expect( page.locator( PRICE ) ).toHaveCount( 1 );
} );

test( "the plugin's preview sits on a panel, as the gallery does", async ( { page } ) => {
	await page.goto( PRODUCT_PATH );

	const side = page.locator( `${ PREVIEW } ${ PREVIEW_FRAME } ${ PREVIEW_SIDE }` );

	await expect( side ).toHaveCSS( 'border-top-width', '1px' );
	await expect( side ).toHaveCSS( 'border-top-color', await presetColour( page, 'border' ) );
	await expect( side ).toHaveCSS( 'background-color', await presetColour( page, 'base' ) );
	await expect( side ).not.toHaveCSS( 'border-top-left-radius', '0px' );
} );

test( "the plugin's note sits under its preview, small and muted", async ( { page } ) => {
	await page.goto( PRODUCT_PATH );

	const note = page.locator( `${ PREVIEW } > ${ PREVIEW_NOTE }:last-child` );

	await expect( note ).toHaveText( PREVIEW_NOTE_LABEL );
	await expect( note ).toHaveCSS( 'color', await presetColour( page, 'muted' ) );

	const [ noteSize, bodySize ] = await Promise.all( [
		note.evaluate( ( element ) => parseFloat( getComputedStyle( element ).fontSize ) ),
		page.evaluate( () => parseFloat( getComputedStyle( document.body ).fontSize ) ),
	] );

	expect( noteSize ).toBeLessThan( bodySize );
} );

test( "adding the product without approving it shows the plugin's error under the box until it is ticked", async ( {
	page,
} ) => {
	await page.goto( PRODUCT_PATH );

	const approval = page.locator( `${ ADD_TO_CART_FORM } ${ APPROVAL }` );
	const box = approval.locator( APPROVAL_BOX );
	const error = approval.locator( `${ APPROVAL_ERROR }[role="alert"]` );

	await expect( error ).toBeHidden();

	// The plugin draws the box itself when it finds the theme's presets,
	// with this rule from its storefront stylesheet; the error still shows.
	await page
		.locator( `${ ADD_TO_CART_FORM } ${ FORM }` )
		.evaluate( ( form ) => form.setAttribute( 'data-pixonaut-look', 'border accent' ) );
	await page.addStyleTag( {
		content: `.pixonaut-form[data-pixonaut-look~="border"][data-pixonaut-look~="accent"] .pixonaut-approval__box {
			border: 1px solid var( --wp--preset--color--border-strong );
		}`,
	} );

	await page.locator( `${ ADD_TO_CART_FORM } button[type="submit"]` ).click();

	await expect( approval ).toHaveClass( /\bis-invalid\b/ );
	await expect( box ).toHaveAttribute( 'aria-invalid', 'true' );
	await expect( error ).toHaveText( APPROVAL_REQUIRED_LABEL );
	await expect( error ).toBeVisible();
	await expect( error ).toHaveCSS( 'color', await presetColour( page, 'error' ) );
	await expect( box ).toHaveCSS( 'border-top-color', await presetColour( page, 'error' ) );
	expect(
		await precedes( approval.locator( 'label' ), error ),
		'the error comes after the box',
	).toBe( true );
	await expect( page.locator( '.woocommerce-message' ) ).toHaveCount( 0 );

	await box.check();

	await expect( approval ).not.toHaveClass( /\bis-invalid\b/ );
	await expect( box ).not.toHaveAttribute( 'aria-invalid' );
	await expect( error ).toBeHidden();
} );

test( 'the description panel and the related products follow the purchase panel', async ( {
	page,
} ) => {
	await page.goto( PRODUCT_PATH );

	await expect( page.locator( DESCRIPTION_PANEL ) ).toContainText(
		'A soft tee with your own print on the chest.',
	);
	await expect( page.locator( `${ RELATED_PRODUCTS } .wc-block-product` ) ).toContainText( [
		'Plain tee',
	] );
	await expect( page.locator( `${ RELATED_PRODUCTS } .add_to_cart_button` ) ).toHaveCount( 0 );
} );

test( 'a product the plugin does not personalise shows the gallery and the plain form', async ( {
	page,
} ) => {
	await page.goto( PLAIN_PRODUCT_PATH );

	const main = page.locator( 'main' );

	await expect( main.locator( PREVIEW ) ).toHaveCount( 0 );
	await expect( main.locator( FORM ) ).toHaveCount( 0 );
	await expect( main.locator( '.wp-block-woocommerce-product-image-gallery' ) ).toHaveCount( 1 );
	await expect( main.locator( `${ ADD_TO_CART_FORM } ${ PRICE }` ) ).toContainText( '249' );
	await expect( main.locator( `${ ADD_TO_CART_FORM } button[type="submit"]` ) ).toBeVisible();
} );

test( 'the designer stays hidden when the plugin hides it', async ( { page } ) => {
	await page.goto( PRODUCT_PATH );

	const form = page.locator( `${ ADD_TO_CART_FORM } ${ FORM }` );

	await expect( form ).toBeVisible();
	// The plugin sets `hidden` on its form for a variation bought without a design.
	await form.evaluate( ( node ) => {
		( node as HTMLElement ).hidden = true;
	} );
	await expect( form ).toBeHidden();
} );

/** The issue's widths around both breakpoints, and a desktop's. */
const DESIGNER_WIDTHS = [ 700, 750, 767, 768, 770, 781, 782, 1280 ];
const VIEWPORT_HEIGHT = 900;

/** Sub-pixel layout rounding a box may be off by. */
const LAYOUT_TOLERANCE_PX = 1;

const PURCHASE_PANEL = '.pixonaut-shop-product-info';

/**
 * The product page with the plugin's stylesheet, its frame set for a
 * landscape photo, and the SDK's drawing of a view in its preview.
 */
async function openDesigner(
	page: Page,
	width: number,
	height: number = VIEWPORT_HEIGHT,
): Promise< void > {
	await page.setViewportSize( { width, height } );
	await page.goto( PRODUCT_PATH );
	await page.addStyleTag( { content: PLUGIN_STOREFRONT_CSS } );
	await page.locator( PREVIEW_FRAME ).evaluate(
		( frame, { style, drawn } ) => {
			frame.setAttribute( 'style', style );
			frame.classList.add( drawn );
		},
		{ style: PLUGIN_FRAME_STYLE, drawn: PLUGIN_FRAME_DRAWN },
	);
	await showView( page, SDK_PHOTO_RATIOS[ 0 ] );
}

/** The SDK drawing the view whose photo has this shape, as switching to it does. */
async function showView( page: Page, ratio: number ): Promise< void > {
	await page.locator( PREVIEW_SIDE ).evaluate( drawSdkPreview, ratio );
}

for ( const width of DESIGNER_WIDTHS ) {
	test( `at ${ width }px the whole design sits inside the preview's frame, clear of the purchase panel`, async ( {
		page,
	} ) => {
		await openDesigner( page, width );

		const frame = await page.locator( PREVIEW_SIDE ).boundingBox();
		const panel = await page.locator( PURCHASE_PANEL ).boundingBox();

		expect( frame ).not.toBeNull();
		expect( panel ).not.toBeNull();
		if ( frame === null || panel === null ) {
			return;
		}

		for ( const part of [ 'sdk-stage', 'sdk-plate' ] ) {
			const box = await page.getByTestId( part ).boundingBox();

			expect( box, part ).not.toBeNull();
			if ( box === null ) {
				continue;
			}
			expect( box.x, `${ part } left` ).toBeGreaterThanOrEqual( frame.x - LAYOUT_TOLERANCE_PX );
			expect( box.y, `${ part } top` ).toBeGreaterThanOrEqual( frame.y - LAYOUT_TOLERANCE_PX );
			expect( box.x + box.width, `${ part } right` ).toBeLessThanOrEqual(
				frame.x + frame.width + LAYOUT_TOLERANCE_PX,
			);
			expect( box.y + box.height, `${ part } bottom` ).toBeLessThanOrEqual(
				frame.y + frame.height + LAYOUT_TOLERANCE_PX,
			);
		}

		const apart =
			frame.y + frame.height <= panel.y + LAYOUT_TOLERANCE_PX ||
			frame.x + frame.width <= panel.x + LAYOUT_TOLERANCE_PX;

		expect( apart, 'the frame and the purchase panel do not overlap' ).toBe( true );
	} );

	test( `at ${ width }px the columns stack exactly where the plugin lays its preview out for a phone`, async ( {
		page,
	} ) => {
		await openDesigner( page, width );

		const preview = await page.locator( PREVIEW ).boundingBox();
		const panel = await page.locator( PURCHASE_PANEL ).boundingBox();

		expect( preview ).not.toBeNull();
		expect( panel ).not.toBeNull();
		if ( preview === null || panel === null ) {
			return;
		}

		const stacked = panel.y >= preview.y + preview.height - LAYOUT_TOLERANCE_PX;

		expect( stacked ).toBe( width <= PLUGIN_PHONE_MAX_WIDTH );
	} );
}

/** The lines a tab draws under or around its label, by what draws them. */
async function tabLines( tab: Locator ) {
	return tab.evaluate( ( element ) => {
		const style = getComputedStyle( element );
		const drawn = ( side: 'Top' | 'Right' | 'Bottom' | 'Left' ) =>
			parseFloat( style[ `border${ side }Width` ] ) > 0 &&
			style[ `border${ side }Style` ] !== 'none' &&
			style[ `border${ side }Color` ] !== 'rgba(0, 0, 0, 0)';

		return {
			top: drawn( 'Top' ),
			right: drawn( 'Right' ),
			bottom: drawn( 'Bottom' ),
			left: drawn( 'Left' ),
			bottomColour: style.borderBottomColor,
			underline: style.textDecorationLine.includes( 'underline' ),
			shadow: style.boxShadow !== 'none',
		};
	} );
}

for ( const look of [ '', PLUGIN_THEME_LOOK ] ) {
	test( `with two sides the selected tab has the one line under it${
		look === '' ? '' : ", with the plugin drawing the tabs in the theme's look"
	}`, async ( { page } ) => {
		await page.goto( PRODUCT_PATH );
		await page.addStyleTag( { content: PLUGIN_STOREFRONT_CSS } );

		const form = page.locator( `${ ADD_TO_CART_FORM } ${ FORM }` );

		await form.evaluate(
			( node, { tabs, theme } ) => {
				node.insertAdjacentHTML( 'afterbegin', tabs );
				if ( theme !== '' ) {
					node.setAttribute( 'data-pixonaut-look', theme );
				}
			},
			{ tabs: PLUGIN_SIDE_TABS, theme: look },
		);

		const accent = await presetColour( page, 'accent' );

		expect( await tabLines( form.locator( '[aria-selected="true"]' ) ) ).toEqual( {
			top: false,
			right: false,
			bottom: true,
			left: false,
			bottomColour: accent,
			underline: false,
			shadow: false,
		} );

		const other = await tabLines( form.locator( '[aria-selected="false"]' ) );

		expect( other ).toMatchObject( {
			top: false,
			right: false,
			bottom: false,
			left: false,
			underline: false,
			shadow: false,
		} );
	} );
}

/** The most of a phone's screen the preview and the mark under it may take. */
const PHONE_PREVIEW_SHARE = PLUGIN_PHONE_PREVIEW_VH / 100;

/** The issue's desktop and phone viewports. */
const FIXED_PREVIEW_VIEWPORTS = [
	{ width: 1280, height: 800 },
	{ width: 390, height: 844 },
];

/** The landscape shape `PLUGIN_FRAME_STYLE` opens the preview at. */
const PLUGIN_OPENING_ASPECT = 1.5;

/** A 5:7 sign's shape, as width per unit of height. */
const FIVE_BY_SEVEN = 5 / 7;

/**
 * What `Product\PreviewFrame` prints around the preview when the store picks
 * a frame style on the gallery block: the shape as `--ps-frame-aspect`.
 */
async function chooseFrame( page: Page, aspect: number ): Promise< void > {
	await page.locator( PREVIEW ).evaluate( ( preview, value ) => {
		const wrapper = document.createElement( 'div' );

		wrapper.className = 'pixonaut-shop-product-frame';
		wrapper.style.setProperty( '--ps-frame-aspect', String( value ) );
		preview.before( wrapper );
		wrapper.append( preview );
	}, aspect );
}

/**
 * The preview element's size as each view is drawn into it, first, second
 * and first again, checking every drawing sits inside it.
 */
async function sizesAcrossViews( page: Page ): Promise< { width: number; height: number }[] > {
	const side = page.locator( PREVIEW_SIDE );
	const sizes = [];

	for ( const ratio of [ ...SDK_PHOTO_RATIOS, SDK_PHOTO_RATIOS[ 0 ] ] ) {
		await showView( page, ratio );

		const box = await side.boundingBox();
		const stage = await page.getByTestId( 'sdk-stage' ).boundingBox();

		expect( box ).not.toBeNull();
		expect( stage ).not.toBeNull();
		if ( box === null || stage === null ) {
			return [];
		}
		expect( stage.x ).toBeGreaterThanOrEqual( box.x - LAYOUT_TOLERANCE_PX );
		expect( stage.y ).toBeGreaterThanOrEqual( box.y - LAYOUT_TOLERANCE_PX );
		expect( stage.x + stage.width ).toBeLessThanOrEqual( box.x + box.width + LAYOUT_TOLERANCE_PX );
		expect( stage.y + stage.height ).toBeLessThanOrEqual(
			box.y + box.height + LAYOUT_TOLERANCE_PX,
		);
		sizes.push( { width: Math.round( box.width ), height: Math.round( box.height ) } );
	}

	return sizes;
}

/** How far a rounded box's height may be from its rounded width over its shape: a pixel of each. */
const SHAPE_TOLERANCE_PX = 2;

/** Whether a box has this shape. */
function expectShape( size: { width: number; height: number } | undefined, aspect: number ): void {
	expect( size?.height ?? 0 ).toBeGreaterThan( 0 );
	expect( Math.abs( ( size?.width ?? 0 ) / aspect - ( size?.height ?? 0 ) ) ).toBeLessThanOrEqual(
		SHAPE_TOLERANCE_PX,
	);
}

for ( const viewport of FIXED_PREVIEW_VIEWPORTS ) {
	test( `at ${ viewport.width }x${ viewport.height } the preview keeps the shape the plugin opens it at, and its size, as the shopper switches views`, async ( {
		page,
	} ) => {
		await openDesigner( page, viewport.width, viewport.height );

		// Held at the height the theme gives the plugin's `--pixonaut-preview-height`.
		expect(
			await page
				.locator( PREVIEW_SIDE )
				.evaluate( ( element ) =>
					getComputedStyle( element ).getPropertyValue( '--pixonaut-preview-height' ).trim(),
				),
		).not.toBe( '' );

		const sizes = await sizesAcrossViews( page );
		const [ first ] = sizes;

		expectShape( first, PLUGIN_OPENING_ASPECT );
		expect( sizes ).toEqual( sizes.map( () => first ) );

		if ( viewport.width <= PLUGIN_PHONE_MAX_WIDTH ) {
			// No taller than the preview may be on a phone.
			expect( first?.height ?? 0 ).toBeLessThanOrEqual(
				viewport.height * PHONE_PREVIEW_SHARE + LAYOUT_TOLERANCE_PX,
			);
		}
	} );

	test( `at ${ viewport.width }x${ viewport.height } the preview holds the frame shape the store picks, whatever the plugin opens it at`, async ( {
		page,
	} ) => {
		await openDesigner( page, viewport.width, viewport.height );
		await chooseFrame( page, FIVE_BY_SEVEN );

		const sizes = await sizesAcrossViews( page );
		const [ first ] = sizes;

		expectShape( first, FIVE_BY_SEVEN );
		expect( sizes ).toEqual( sizes.map( () => first ) );

		if ( viewport.width <= PLUGIN_PHONE_MAX_WIDTH ) {
			// A portrait design fills the width it is given, short of the cap.
			const available = await page.locator( PREVIEW ).boundingBox();

			expect( Math.abs( ( first?.width ?? 0 ) - ( available?.width ?? 0 ) ) ).toBeLessThanOrEqual(
				LAYOUT_TOLERANCE_PX,
			);
		}
	} );
}

/** The phones a 5:7 design fills the width of. */
const PHONE_VIEWPORTS = [
	{ width: 390, height: 844 },
	{ width: 375, height: 667 },
];

/** A design much taller than wide, which the cap narrows. */
const VERY_TALL_SHAPE = 1 / 3;

/** A phone's screen with the keyboard open over its lower half. */
const PHONE_WITH_KEYBOARD = { width: 390, height: 400 };

/** What the plugin prints where the workspace shows the SDK's mark: the frame marked, and its target under the preview. */
async function printMark( page: Page ): Promise< void > {
	await page.locator( PREVIEW_FRAME ).evaluate(
		( frame, { attribute, target } ) => {
			frame.setAttribute( attribute, '' );
			frame.insertAdjacentHTML( 'beforeend', target );
		},
		{ attribute: PLUGIN_FRAME_MARK, target: PLUGIN_MARK_TARGET },
	);
}

/** Whether the form's own control, scrolled to the middle of the screen, is what a tap there reaches. */
async function tapReaches( control: Locator ): Promise< boolean > {
	await control.evaluate( ( node ) => node.scrollIntoView( { block: 'center' } ) );

	return control.evaluate( ( node ) => {
		const { left, top, width, height } = node.getBoundingClientRect();
		const hit = document.elementFromPoint( left + width / 2, top + height / 2 );

		return hit !== null && ( hit === node || node.contains( hit ) );
	} );
}

for ( const viewport of PHONE_VIEWPORTS ) {
	test( `at ${ viewport.width }x${ viewport.height } a 5:7 design with the mark spans the width beside the page's padding`, async ( {
		page,
	} ) => {
		await openDesigner( page, viewport.width, viewport.height );
		await printMark( page );
		await chooseFrame( page, FIVE_BY_SEVEN );

		const side = await page.locator( PREVIEW_SIDE ).boundingBox();
		const panel = await page.locator( PURCHASE_PANEL ).boundingBox();
		const frame = await page.locator( PREVIEW_FRAME ).boundingBox();

		expect( Math.abs( ( side?.width ?? 0 ) - ( panel?.width ?? 0 ) ) ).toBeLessThanOrEqual(
			LAYOUT_TOLERANCE_PX,
		);
		expect( frame?.height ?? 0 ).toBeLessThanOrEqual(
			viewport.height * PHONE_PREVIEW_SHARE + LAYOUT_TOLERANCE_PX,
		);
	} );
}

for ( const { mark, room } of [
	{ mark: false, room: 0 },
	{ mark: true, room: PLUGIN_MARK_HEIGHT_PX },
] ) {
	test( `on a phone a very tall design ${ mark ? 'with' : 'without' } the mark narrows to the cap, centred, and leaves the form reachable`, async ( {
		page,
	} ) => {
		const { width, height } = PHONE_VIEWPORTS[ 0 ];

		await openDesigner( page, width, height );
		if ( mark ) {
			await printMark( page );
		}
		await chooseFrame( page, VERY_TALL_SHAPE );

		const side = await page.locator( PREVIEW_SIDE ).boundingBox();
		const preview = await page.locator( PREVIEW ).boundingBox();

		const cap = height * PHONE_PREVIEW_SHARE - room;

		expect( Math.abs( ( side?.width ?? 0 ) - cap * VERY_TALL_SHAPE ) ).toBeLessThanOrEqual(
			LAYOUT_TOLERANCE_PX,
		);
		expect( Math.abs( ( side?.height ?? 0 ) - cap ) ).toBeLessThanOrEqual( LAYOUT_TOLERANCE_PX );
		expect(
			Math.abs(
				( side?.x ?? 0 ) +
					( side?.width ?? 0 ) / 2 -
					( ( preview?.x ?? 0 ) + ( preview?.width ?? 0 ) / 2 ),
			),
		).toBeLessThanOrEqual( LAYOUT_TOLERANCE_PX );

		const submit = page.locator( `${ ADD_TO_CART_FORM } button[type="submit"]` );

		expect( await tapReaches( submit ), 'with the whole screen' ).toBe( true );

		await page.setViewportSize( PHONE_WITH_KEYBOARD );
		expect( await tapReaches( submit ), 'with the keyboard open' ).toBe( true );
	} );
}

test( 'on a phone a preview the plugin pins never covers the form under it', async ( { page } ) => {
	const { width, height } = PHONE_VIEWPORTS[ 0 ];

	await openDesigner( page, width, height );
	await page
		.locator( PREVIEW )
		.evaluate( ( preview, pinned ) => preview.setAttribute( pinned, '' ), PLUGIN_PINNED );
	await expect( page.locator( PREVIEW ) ).toHaveCSS( 'position', 'sticky' );

	const submit = page.locator( `${ ADD_TO_CART_FORM } button[type="submit"]` );

	expect( await tapReaches( submit ) ).toBe( true );
} );

test( "the view strip keeps the page's small gap under the preview", async ( { page } ) => {
	await page.goto( PRODUCT_PATH );
	await page.addStyleTag( { content: PLUGIN_STOREFRONT_CSS } );
	await page
		.locator( PREVIEW_VIEWS )
		.evaluate(
			( views, strip ) => views.insertAdjacentHTML( 'afterbegin', strip ),
			PLUGIN_VIEWS_STRIP,
		);

	// The gap the SDK keeps above its strip, and the plugin's printed strip
	// with it (`--pixonaut-views-gap`), is the page's small spacing step.
	const spacing = await page.locator( PREVIEW_VIEWS ).evaluate( ( views ) => {
		const probe = document.createElement( 'span' );
		probe.style.paddingTop = 'var( --wp--preset--spacing--30 )';
		views.append( probe );
		const measured = getComputedStyle( probe ).paddingTop;
		probe.remove();
		return measured;
	} );

	await expect( page.locator( `${ PREVIEW_VIEWS } .pixonaut-views__strip` ) ).toHaveCSS(
		'padding-top',
		spacing,
	);
} );

test( 'the purchase panel has the same padding under the add-to-cart row as at its sides', async ( {
	page,
} ) => {
	await page.goto( PRODUCT_PATH );

	const panel = await page.locator( PURCHASE_PANEL ).boundingBox();
	const row = await page.locator( `${ ADD_TO_CART_FORM } .pixonaut-shop-buy-row` ).boundingBox();

	expect( panel ).not.toBeNull();
	expect( row ).not.toBeNull();
	if ( panel === null || row === null ) {
		return;
	}

	const under = panel.y + panel.height - ( row.y + row.height );
	const side = panel.x + panel.width - ( row.x + row.width );

	expect( Math.abs( under - side ) ).toBeLessThanOrEqual( LAYOUT_TOLERANCE_PX );
} );
