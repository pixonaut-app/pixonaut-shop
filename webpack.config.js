/**
 * The theme builds one stylesheet, for the front end and the editor canvas
 * alike, its front-end scripts (the product page's swatches, a product
 * card's second picture, the header's search and mega menu, a customer's place while they filter an archive, and the keyboard in the drawer the filters open in), one editor script, which heads the footer's menus in the Site
 * Editor as `inc/Blocks/MenuHeading.php` does on the front end, and its
 * blocks: wp-scripts finds every block.json under src/blocks/ and builds each
 * block, with its render.php, into build/blocks/<name>/, where
 * inc/Blocks/BlockLoader.php registers it.
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: () => ( {
		...defaultConfig.entry(),
		app: './src/app.css',
		'menu-heading': './src/editor/menu-heading.js',
		swatches: './src/product/swatches.ts',
		'second-picture': './src/product/second-picture.ts',
		'header-search': './src/header/search.ts',
		'header-mega-menu': './src/header/mega-menu.ts',
		'filters-place': './src/archive/filters-place.ts',
		'filters-drawer': './src/archive/filters-drawer.ts',
	} ),
};
