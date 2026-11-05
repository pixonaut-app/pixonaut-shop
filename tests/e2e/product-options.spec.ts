import { expect, type Locator, type Page, test } from '@playwright/test';
import { drawSdkSteps, PLUGIN_STOREFRONT_CSS } from './support/plugin-storefront';

/**
 * A variable product's options on the product page: WooCommerce's variation
 * menus drawn as Pixonaut's designer draws its fields, the same menus as
 * chips under the add-to-cart form's Use swatches style
 * (`inc/Product/VariationSwatches.php`), and both still styled once the
 * plugin moves them into the designer's steps (its cart row, and SDK
 * 0.8.0's options and last steps). The products, and the swatches product's own
 * template with the style, are the global setup's
 * (`tests/Support/ShopFixtures.php`); the fake plugin personalises the first.
 */

const VARIABLE_PRODUCT_PATH = '/product/fake-pixonaut-variable-product/';
const SWATCHES_PRODUCT_PATH = '/product/swatches-product/';

/** The products' sizes, every one of them sold. */
const SIZE_LABEL = 'Size';
const SIZES = [ 'S', 'M', 'L' ];

/** The swatches product's second attribute and its values. */
const COLOUR_LABEL = 'Colour';
const COLOURS = [ 'Black', 'White' ];

/** The size and colour the swatches product is not sold in together. */
const UNSOLD_SIZE = 'L';
const UNSOLD_COLOUR = 'Black';

/** A colour it is sold in at that size. */
const SOLD_COLOUR = 'White';

const ADD_TO_CART_FORM = 'form.cart';
const MENU = `${ ADD_TO_CART_FORM } table.variations select`;
/** WooCommerce names each menu `attribute_` and the attribute's slug. */
const SIZE_MENU = `${ MENU }[name="attribute_size"]`;
const COLOUR_MENU = `${ MENU }[name="attribute_colour"]`;
const MENU_LABEL = `${ ADD_TO_CART_FORM } table.variations th label`;
const CLEAR_LINK = `${ ADD_TO_CART_FORM } .reset_variations`;
/** The field WooCommerce writes the chosen variation's id into once it has found it. */
const CHOSEN_VARIATION = `${ ADD_TO_CART_FORM } input[name="variation_id"]`;
/** The box WooCommerce shows the chosen variation's description, price and stock in. */
const CHOSEN_VARIATION_DETAILS = `${ ADD_TO_CART_FORM } .single_variation`;
const DESIGNER_FORM = `${ ADD_TO_CART_FORM } [data-fake-pixonaut="form"]`;
const BUY_ROW = `${ ADD_TO_CART_FORM } .pixonaut-shop-buy-row`;
/** The plugin's approval box, which its script moves into the cart row. */
const APPROVAL_BOX = '[data-fake-pixonaut="approval"]';
const APPROVAL = `${ ADD_TO_CART_FORM } ${ APPROVAL_BOX }`;
/** The row the plugin's script gathers the approval box, the variation's box and the price row into. */
const CART_ROW = '.pixonaut-cart-row';
const PURCHASE_PANEL = '.pixonaut-shop-product-info';
const SWATCHES = `${ ADD_TO_CART_FORM } .pixonaut-shop-swatches`;
const SWATCH = '.pixonaut-shop-swatch';

/** The height Pixonaut's designer draws its menus and text inputs at. */
const DESIGNER_FIELD_HEIGHT = '40px';

/** The height it draws its option chips at. */
const DESIGNER_CHIP_HEIGHT = '36px';

/** Sub-pixel layout rounding a box may be off by. */
const LAYOUT_TOLERANCE_PX = 1;

/** What a preset or other custom property resolves to for `property`, as computed styles report it. */
async function resolved( page: Page, property: string, value: string ): Promise< string > {
	return page.evaluate(
		( [ name, css ] ) => {
			const probe = document.createElement( 'span' );
			probe.style.setProperty( name, css );
			document.body.append( probe );
			const computed = getComputedStyle( probe ).getPropertyValue( name );
			probe.remove();
			return computed;
		},
		[ property, value ],
	);
}

/** A chip or an `<option>`: the value it picks, and whether it can be picked. */
interface Choice {
	value: string;
	disabled: boolean;
}

/** The choices a group's chips offer, in their order. */
async function chipChoices( group: Locator ): Promise< Choice[] > {
	return group.getByRole( 'radio' ).evaluateAll( ( radios ) =>
		( radios as HTMLInputElement[] ).map( ( radio ) => ( {
			value: radio.value,
			disabled: radio.disabled,
		} ) ),
	);
}

/** The choices a menu offers, in its order, but its placeholder. */
async function menuChoices( menu: Locator ): Promise< Choice[] > {
	return menu.evaluate( ( select ) =>
		Array.from( ( select as HTMLSelectElement ).options )
			.filter( ( option ) => option.value !== '' )
			.map( ( option ) => ( { value: option.value, disabled: option.disabled } ) ),
	);
}

/** Every value offered and none narrowed away. */
function allChoices( values: readonly string[] ): Choice[] {
	return values.map( ( value ) => ( { value, disabled: false } ) );
}

async function box( locator: Locator ) {
	const found = await locator.boundingBox();

	if ( found === null ) {
		throw new Error( 'The element has no box.' );
	}

	return found;
}

/** The menu's look, and its label's, as the designer's fields have them. */
async function expectDesignerField( page: Page ): Promise< void > {
	const menu = page.locator( MENU );
	const label = page.locator( MENU_LABEL );
	const small = await resolved( page, 'font-size', 'var( --wp--preset--font-size--small )' );

	await expect( menu ).toHaveCSS( 'height', DESIGNER_FIELD_HEIGHT );
	await expect( menu ).toHaveCSS(
		'border-top-color',
		await resolved( page, 'color', 'var( --wp--preset--color--border )' ),
	);
	await expect( menu ).toHaveCSS( 'font-size', small );
	await expect( label ).toHaveCSS( 'font-size', small );
	await expect( label ).toHaveCSS( 'font-weight', '600' );
}

test( "a variation menu looks like the designer's fields", async ( { page } ) => {
	await page.goto( VARIABLE_PRODUCT_PATH );

	await expect( page.locator( MENU_LABEL ) ).toHaveText( SIZE_LABEL );
	await expectDesignerField( page );
} );

test( 'the variation menu sits at the form’s rhythm above the designer, with Clear on its label’s row', async ( {
	page,
} ) => {
	await page.goto( VARIABLE_PRODUCT_PATH );
	await page.locator( MENU ).selectOption( 'M' );
	await expect( page.locator( CHOSEN_VARIATION ) ).not.toHaveValue( '' );
	await expect( page.locator( CLEAR_LINK ) ).toBeVisible();

	const label = await box( page.locator( MENU_LABEL ) );
	const clear = await box( page.locator( CLEAR_LINK ) );
	const menu = await box( page.locator( MENU ) );
	const gap = await page
		.locator( ADD_TO_CART_FORM )
		.evaluate( ( form ) => parseFloat( getComputedStyle( form ).rowGap ) );

	// Clear on the label's row, at its end.
	expect( clear.y ).toBeLessThan( menu.y );
	expect( clear.y + clear.height ).toBeGreaterThan( label.y );
	expect( clear.x ).toBeGreaterThan( label.x + label.width );

	// A variation with nothing of its own to show takes no room between them,
	// once WooCommerce shows it.
	await expect( page.locator( CHOSEN_VARIATION_DETAILS ) ).toHaveCSS( 'display', 'block' );

	const designer = await box( page.locator( DESIGNER_FORM ) );

	expect( Math.abs( designer.y - ( menu.y + menu.height ) - gap ) ).toBeLessThanOrEqual(
		LAYOUT_TOLERANCE_PX,
	);
} );

test( 'a variable product without the swatches style keeps its menus', async ( { page } ) => {
	await page.goto( VARIABLE_PRODUCT_PATH );

	await expect( page.locator( MENU ) ).toBeVisible();
	await expect( page.locator( SWATCHES ) ).toHaveCount( 0 );
} );

test( 'with Use swatches the attribute shows as chips in place of its menu', async ( { page } ) => {
	await page.goto( SWATCHES_PRODUCT_PATH );

	const group = page.getByRole( 'radiogroup', { name: SIZE_LABEL } );

	await expect( page.locator( SIZE_MENU ) ).toBeHidden();
	await expect( page.locator( COLOUR_MENU ) ).toBeHidden();
	await expect( group.getByRole( 'radio' ) ).toHaveCount( SIZES.length );

	for ( const size of SIZES ) {
		await expect( group.getByRole( 'radio', { name: size } ) ).not.toBeChecked();
	}

	await expect( group.locator( SWATCH ).first() ).toHaveCSS( 'height', DESIGNER_CHIP_HEIGHT );
} );

test( 'picking a chip chooses the variation, and Clear clears the chip', async ( { page } ) => {
	await page.goto( SWATCHES_PRODUCT_PATH );

	const group = page.getByRole( 'radiogroup', { name: SIZE_LABEL } );
	const medium = group.getByRole( 'radio', { name: 'M' } );

	await group.locator( SWATCH, { hasText: 'M' } ).click();
	await page
		.getByRole( 'radiogroup', { name: COLOUR_LABEL } )
		.locator( SWATCH, { hasText: SOLD_COLOUR } )
		.click();

	await expect( medium ).toBeChecked();
	await expect( page.locator( SIZE_MENU ) ).toHaveValue( 'M' );
	await expect( page.locator( `${ ADD_TO_CART_FORM } button[type="submit"]` ) ).not.toHaveClass(
		/disabled/,
	);

	const ring = await group
		.locator( SWATCH, { hasText: 'M' } )
		.evaluate( ( chip ) => getComputedStyle( chip ).boxShadow );
	const accent = await resolved( page, 'color', 'var( --wp--preset--color--accent )' );

	expect( ring ).toContain( accent );

	await page.locator( CLEAR_LINK ).click();

	await expect( page.locator( SIZE_MENU ) ).toHaveValue( '' );
	await expect( medium ).not.toBeChecked();
} );

test( 'Clear from the keyboard leaves focus on the first attribute’s chips', async ( { page } ) => {
	await page.goto( SWATCHES_PRODUCT_PATH );

	const group = page.getByRole( 'radiogroup', { name: SIZE_LABEL } );

	await group.locator( SWATCH, { hasText: 'M' } ).click();
	await page.locator( CLEAR_LINK ).focus();
	await page.keyboard.press( 'Enter' );

	await expect( page.locator( SIZE_MENU ) ).toHaveValue( '' );

	const focused = group.locator( 'input[type="radio"]:focus' );

	await expect( focused ).toHaveCount( 1 );
	await expect( focused ).toBeVisible();
	await expect( focused ).toBeEnabled();
} );

test( 'when WooCommerce narrows another attribute’s options, its chips follow, and Clear restores them', async ( {
	page,
} ) => {
	await page.goto( SWATCHES_PRODUCT_PATH );

	const sizes = page.getByRole( 'radiogroup', { name: SIZE_LABEL } );
	const colours = page.getByRole( 'radiogroup', { name: COLOUR_LABEL } );
	const colourMenu = page.locator( COLOUR_MENU );

	await sizes.locator( SWATCH, { hasText: UNSOLD_SIZE } ).click();

	// WooCommerce takes the colour it cannot sell at that size off the menu,
	// or disables it, and the chips are drawn as it leaves the menu.
	await expect
		.poll( () => menuChoices( colourMenu ) )
		.not.toContainEqual( { value: UNSOLD_COLOUR, disabled: false } );
	await expect.poll( () => chipChoices( colours ) ).toEqual( await menuChoices( colourMenu ) );

	await colours.locator( SWATCH, { hasText: SOLD_COLOUR } ).click();

	await expect( page.locator( CHOSEN_VARIATION ) ).not.toHaveValue( '' );

	await page.locator( CLEAR_LINK ).click();

	await expect.poll( () => chipChoices( sizes ) ).toEqual( allChoices( SIZES ) );
	await expect.poll( () => chipChoices( colours ) ).toEqual( allChoices( COLOURS ) );
	await expect( page.getByRole( 'radio', { checked: true } ) ).toHaveCount( 0 );
} );

/** The space between the approval box and the price row under it, in pixels. */
async function approvalToBuyRow( page: Page ): Promise< number > {
	const approval = await box( page.locator( APPROVAL ) );
	const row = await box( page.locator( BUY_ROW ) );

	return row.y - ( approval.y + approval.height );
}

test( 'in the designer’s steps the options and the cart row keep the theme’s look', async ( {
	page,
} ) => {
	await page.goto( VARIABLE_PRODUCT_PATH );
	await page.addStyleTag( { content: PLUGIN_STOREFRONT_CSS } );

	// The form's rhythm, whether or not the plugin has moved the two into its cart row.
	const rhythm = parseFloat(
		await resolved( page, 'padding-top', 'var( --wp--preset--spacing--40 )' ),
	);

	expect( Math.abs( ( await approvalToBuyRow( page ) ) - rhythm ) ).toBeLessThanOrEqual(
		LAYOUT_TOLERANCE_PX,
	);

	await page.locator( DESIGNER_FORM ).evaluate( drawSdkSteps );

	await expect( page.getByTestId( 'sdk-options-step' ).locator( 'select' ) ).toHaveCount( 1 );
	await expectDesignerField( page );

	const cartRow = page.getByTestId( 'sdk-last-step' ).locator( CART_ROW );

	await expect( cartRow ).toHaveCSS( 'display', 'flow-root' );
	await expect( cartRow.locator( APPROVAL_BOX ) ).toHaveCount( 1 );

	const row = cartRow.locator( '.pixonaut-shop-buy-row' );

	await expect( row ).toHaveCSS( 'display', 'flex' );
	await expect( row ).toHaveCSS( 'border-top-width', '1px' );
	await expect( row.locator( '.pixonaut-shop-price' ) ).toHaveCSS(
		'color',
		await resolved( page, 'color', 'var( --wp--preset--color--accent )' ),
	);
	await expect( page.locator( BUY_ROW ) ).toHaveCount( 1 );
	expect( Math.abs( ( await approvalToBuyRow( page ) ) - rhythm ) ).toBeLessThanOrEqual(
		LAYOUT_TOLERANCE_PX,
	);

	// Nothing under the designer's form once the cart row is in it, so the
	// purchase panel's padding is the same at its foot as at its sides.
	const panel = await box( page.locator( PURCHASE_PANEL ) );
	const buyRow = await box( row );
	const under = panel.y + panel.height - ( buyRow.y + buyRow.height );
	const side = panel.x + panel.width - ( buyRow.x + buyRow.width );

	expect( Math.abs( under - side ) ).toBeLessThanOrEqual( LAYOUT_TOLERANCE_PX );
} );
