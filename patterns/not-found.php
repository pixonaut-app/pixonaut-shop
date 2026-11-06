<?php
/**
 * Title: Page not found
 * Slug: pixonaut-shop/not-found
 * Inserter: no
 *
 * @package Pixonaut\Shop
 *
 * The 404 template's content: a centred panel that says what happened, with
 * the status as a pill, the product search and the way back to the front
 * page, and the store's categories below it. Translated strings, since this
 * is part of the theme rather than content a store writes; "404" is the
 * status code, not copy.
 */

declare(strict_types=1);

use Pixonaut\Shop\Blocks\ThemeBlocks;

?>
<!-- wp:group {"className":"is-style-panel pixonaut-shop-not-found","layout":{"type":"flex","orientation":"vertical","justifyContent":"center"}} -->
<div class="wp-block-group is-style-panel pixonaut-shop-not-found">
	<!-- wp:paragraph {"className":"is-style-pill"} -->
	<p class="is-style-pill">404</p>
	<!-- /wp:paragraph -->

	<!-- wp:heading {"textAlign":"center","level":1} -->
	<h1 class="wp-block-heading has-text-align-center"><?php echo esc_html__( 'Page not found', 'pixonaut-shop' ); ?></h1>
	<!-- /wp:heading -->

	<!-- wp:paragraph {"align":"center","className":"pixonaut-shop-lede","textColor":"muted"} -->
	<p class="has-text-align-center pixonaut-shop-lede has-muted-color has-text-color"><?php echo esc_html__( 'The page you are looking for is not here. It may have moved, or the address may be mistyped.', 'pixonaut-shop' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:woocommerce/product-search {"hasLabel":false} /-->

	<!-- wp:buttons -->
	<div class="wp-block-buttons">
		<!-- wp:button {"className":"is-style-outline"} -->
		<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html__( 'Go to the home page', 'pixonaut-shop' ); ?></a></div>
		<!-- /wp:button -->
	</div>
	<!-- /wp:buttons -->
</div>
<!-- /wp:group -->

<!-- wp:group {"className":"pixonaut-shop-not-found-categories","align":"wide","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide pixonaut-shop-not-found-categories">
	<!-- wp:heading -->
	<h2 class="wp-block-heading"><?php echo esc_html__( 'Shop by category', 'pixonaut-shop' ); ?></h2>
	<!-- /wp:heading -->

	<?php if ( ThemeBlocks::is_registered( 'product-categories' ) ) : ?>
	<!-- wp:pixonaut-shop/product-categories /-->
<?php else : ?>
	<!-- wp:woocommerce/product-categories {"hasCount":false,"hasImage":true,"hasEmpty":false,"isHierarchical":false} /-->
<?php endif; ?>
</div>
<!-- /wp:group -->
