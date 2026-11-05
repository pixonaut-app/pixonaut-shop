<?php
/**
 * Title: No products found
 * Slug: pixonaut-shop/no-products
 * Inserter: no
 *
 * @package Pixonaut\Shop
 *
 * What a product grid shows when nothing matches: a panel that says so,
 * with WooCommerce's own sentence for it, the product search and the way
 * back to the shop.
 */

declare(strict_types=1);

?>
<!-- wp:group {"className":"is-style-panel pixonaut-shop-no-products","layout":{"type":"flex","orientation":"vertical","justifyContent":"center"}} -->
<div class="wp-block-group is-style-panel pixonaut-shop-no-products">
	<!-- wp:heading {"textAlign":"center"} -->
	<h2 class="wp-block-heading has-text-align-center"><?php echo esc_html__( 'No products found', 'pixonaut-shop' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:pattern {"slug":"woocommerce/no-products-found"} /-->

	<!-- wp:woocommerce/product-search {"hasLabel":false} /-->

	<!-- wp:buttons -->
	<div class="wp-block-buttons">
		<!-- wp:button {"className":"is-style-outline"} -->
		<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( (string) get_post_type_archive_link( 'product' ) ); ?>"><?php echo esc_html__( 'Back to the shop', 'pixonaut-shop' ); ?></a></div>
		<!-- /wp:button -->
	</div>
	<!-- /wp:buttons -->
</div>
<!-- /wp:group -->
