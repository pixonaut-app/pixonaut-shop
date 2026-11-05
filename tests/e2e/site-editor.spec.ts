import { expect, test } from '@wordpress/e2e-test-utils-playwright';

/** wp-env maps the checkout under the theme's fixed slug. */
const THEME_SLUG = 'pixonaut-shop';

/** Every template a store renders; kept in step with tests/Unit/Theme/TemplatesTest.php. */
const TEMPLATES = [
	'index',
	'front-page',
	'page',
	'404',
	'single-product',
	'archive-product',
	'page-cart',
	'page-checkout',
	'order-confirmation',
	'product-search-results',
	'search',
];

/** Every part a store edits; kept in step with tests/Unit/Theme/TemplatesTest.php. */
const PARTS = [ 'header', 'footer', 'promises', 'announcement', 'order-next-steps', 'order-help' ];

/** The theme's patterns; kept in step with tests/Unit/Theme/TemplatesTest.php. */
const PATTERNS = [
	'pixonaut-shop/announcement',
	'pixonaut-shop/continue-shopping',
	'pixonaut-shop/footer',
	'pixonaut-shop/front-page',
	'pixonaut-shop/hero',
	'pixonaut-shop/no-products',
	'pixonaut-shop/not-found',
	'pixonaut-shop/order-help',
	'pixonaut-shop/order-next-steps',
	'pixonaut-shop/popular-products',
	'pixonaut-shop/product-grid',
	'pixonaut-shop/product-description',
	'pixonaut-shop/related-products',
	'pixonaut-shop/promises',
	'pixonaut-shop/search-no-results',
];

/** The block styles a store picks in the Site Editor, by block; kept in step with inc/Blocks/BlockStyles.php. */
const BLOCK_STYLES: Record< string, string[] > = {
	'core/group': [ 'Panel', 'Band (tint 1)', 'Band (tint 2)', 'Notice' ],
	'core/paragraph': [ 'Pill' ],
	'core/button': [ 'Text link' ],
};

/** The Site Editor renders the template canvas inside this named iframe. */
const EDITOR_CANVAS_IFRAME = 'iframe[name="editor-canvas"]';

/** What the block parser logs for each block whose markup fails validation. */
const VALIDATION_FAILURE = 'Block validation failed';

test.describe( 'Site Editor', () => {
	const entities = [
		...TEMPLATES.map( ( slug ) => ( { postType: 'wp_template', slug, label: 'template' } ) ),
		...PARTS.map( ( slug ) => ( { postType: 'wp_template_part', slug, label: 'part' } ) ),
	];

	for ( const { postType, slug, label } of entities ) {
		test( `opens the ${ slug } ${ label } without block validation errors`, async ( {
			admin,
			page,
		} ) => {
			// The parser logs every failure to the console; the on-canvas
			// notice asserted on below is not rendered for every failure.
			const failures: string[] = [];
			page.on( 'console', ( message ) => {
				if ( message.text().includes( VALIDATION_FAILURE ) ) {
					failures.push( message.text() );
				}
			} );

			// `canvas: 'edit'` is required: without it the Site Editor opens
			// on the templates list rather than the canvas, so the markup is
			// never parsed and a validation error cannot surface.
			await admin.visitSiteEditor( {
				postType,
				postId: `${ THEME_SLUG }//${ slug }`,
				canvas: 'edit',
			} );

			// The canvas is an iframe: `page.getByText` would never see an
			// error inside it, even when one is there.
			const canvas = page.frameLocator( EDITOR_CANVAS_IFRAME );

			await expect(
				canvas.getByText( 'Block contains unexpected or invalid content' ),
			).toHaveCount( 0 );

			expect( failures ).toEqual( [] );
		} );
	}

	test( 'parses every pattern of the theme without invalid blocks', async ( { admin, page } ) => {
		await admin.visitSiteEditor();

		const invalid = await page.evaluate( async ( slugs ) => {
			type Block = { name: string; isValid: boolean; innerBlocks: Block[] };
			type Pattern = { name: string; content: string };
			const wp = (
				window as unknown as {
					wp: {
						apiFetch: ( options: { path: string } ) => Promise< Pattern[] >;
						blocks: { parse: ( content: string ) => Block[] };
					};
				}
			).wp;
			const patterns = await wp.apiFetch( { path: '/wp/v2/block-patterns/patterns' } );
			const invalidIn = ( blocks: Block[] ): string[] =>
				blocks.flatMap( ( block ) => [
					...( block.isValid ? [] : [ block.name ] ),
					...invalidIn( block.innerBlocks ),
				] );

			return Object.fromEntries(
				slugs.map( ( slug ) => {
					const pattern = patterns.find( ( { name } ) => name === slug );

					return [
						slug,
						pattern ? invalidIn( wp.blocks.parse( pattern.content ) ) : [ 'not registered' ],
					];
				} ),
			);
		}, PATTERNS );

		expect( invalid ).toEqual( Object.fromEntries( PATTERNS.map( ( slug ) => [ slug, [] ] ) ) );
	} );

	test( "offers the theme's block styles", async ( { admin, page } ) => {
		await admin.visitSiteEditor();

		const offered = await page.evaluate( ( blocks ) => {
			const wp = (
				window as unknown as {
					wp: {
						data: {
							select: ( store: string ) => {
								getBlockStyles: ( block: string ) => { label: string }[];
							};
						};
					};
				}
			).wp;
			const registry = wp.data.select( 'core/blocks' );

			return Object.fromEntries(
				blocks.map( ( block ) => [
					block,
					registry.getBlockStyles( block ).map( ( { label } ) => label ),
				] ),
			);
		}, Object.keys( BLOCK_STYLES ) );

		for ( const [ block, labels ] of Object.entries( BLOCK_STYLES ) ) {
			expect( offered[ block ], block ).toEqual( expect.arrayContaining( labels ) );
		}
	} );
} );
