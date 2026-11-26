import { expect, type Locator, type Page, test } from '@playwright/test';

import { TEST_SUPPORT, wpEval } from './support/wp-cli';

/**
 * The product page's "More in the theme" section
 * (`inc/Product/ThemeProducts.php`): the other products with the product's
 * tag, in any category, above the related products, which relate by
 * category alone. The fixture's tag is shared by a product and two others in
 * another category, and a product in the first one's category has no tag
 * (`ThemeFixtures`).
 */

const THEME_PRODUCTS = '.pixonaut-shop-theme-products';
const RELATED_PRODUCTS = '.pixonaut-shop-related-products';
const CARD = '.wc-block-product';

// One worker makes the fixture's products and removes them once, in order,
// rather than a worker removing them under another one's tests.
test.describe.configure( { mode: 'default' } );

let fixture: {
	tag: { name: string; slug: string };
	product: string;
	others: string[];
	untagged: string;
};

test.beforeAll( () => {
	const names = [ 'TAG_NAME', 'TAG_SLUG', 'PRODUCT_SLUG', 'UNTAGGED_SLUG' ];
	const [ name, slug, product, untagged, ...others ] = wpEval(
		`${ TEST_SUPPORT }ThemeFixtures::make(); ` +
			names
				.map( ( constant ) => `echo ${ TEST_SUPPORT }ThemeFixtures::${ constant }, "\\n";` )
				.join( ' ' ) +
			` echo implode( "\\n", ${ TEST_SUPPORT }ThemeFixtures::OTHER_SLUGS );`,
	).split( '\n' );
	fixture = { tag: { name, slug }, product, others, untagged };
} );

test.afterAll( () => {
	wpEval( `${ TEST_SUPPORT }ThemeFixtures::forget();` );
} );

function productPath( slug: string ): string {
	return `/product/${ slug }/`;
}

/** The cards in a section that link to the product. */
function card( section: Locator, slug: string ): Locator {
	return section
		.locator( CARD )
		.filter( { has: section.page().locator( `a[href*="/${ slug }/"]` ) } );
}

async function sections( page: Page ): Promise< { theme: Locator; related: Locator } > {
	await page.goto( productPath( fixture.product ) );

	return { theme: page.locator( THEME_PRODUCTS ), related: page.locator( RELATED_PRODUCTS ) };
}

test( "names the product's theme and links to its archive", async ( { page } ) => {
	const { theme } = await sections( page );

	await expect( theme.getByRole( 'heading', { level: 2 } ).first() ).toHaveText(
		`More in the theme ${ fixture.tag.name }`,
	);
	await expect( theme.getByRole( 'link', { name: 'See all' } ) ).toHaveAttribute(
		'href',
		new RegExp( `/product-tag/${ fixture.tag.slug }/$` ),
	);
} );

test( 'shows the other products in the theme from any category, not the product itself', async ( {
	page,
} ) => {
	const { theme } = await sections( page );

	for ( const slug of fixture.others ) {
		await expect( card( theme, slug ) ).toHaveCount( 1 );
	}

	await expect( card( theme, fixture.product ) ).toHaveCount( 0 );
	await expect( card( theme, fixture.untagged ) ).toHaveCount( 0 );
	await expect( theme.locator( '.add_to_cart_button' ) ).toHaveCount( 0 );
} );

test( 'sits above the related products, which relate by category alone', async ( { page } ) => {
	const { theme, related } = await sections( page );

	await expect( card( related, fixture.untagged ) ).toHaveCount( 1 );

	for ( const slug of fixture.others ) {
		await expect( card( related, slug ) ).toHaveCount( 0 );
	}

	const above = await theme.evaluate(
		( node, other ) =>
			Boolean( node.compareDocumentPosition( other as Node ) & Node.DOCUMENT_POSITION_FOLLOWING ),
		await related.elementHandle(),
	);
	expect( above ).toBe( true );
} );

test( 'a product without a theme shows no section', async ( { page } ) => {
	await page.goto( productPath( fixture.untagged ) );

	await expect( page.locator( RELATED_PRODUCTS ) ).toHaveCount( 1 );
	await expect( page.locator( THEME_PRODUCTS ) ).toHaveCount( 0 );
} );
