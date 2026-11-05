<?php
/**
 * Title: Checkout title
 * Slug: pixonaut-shop/checkout-title
 * Inserter: no
 *
 * @package Pixonaut\Shop
 *
 * The checkout page's title row: the page's own title, and the way back to
 * the cart on the right. The way back needs WooCommerce, so it is left out
 * while WooCommerce is inactive.
 */

declare(strict_types=1);

?>
<!-- wp:group {"align":"wide","className":"pixonaut-shop-section-head pixonaut-shop-checkout-head","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"bottom"}} -->
<div class="wp-block-group alignwide pixonaut-shop-section-head pixonaut-shop-checkout-head">
	<!-- wp:post-title {"level":1} /-->
	<?php if ( function_exists( 'wc_get_cart_url' ) ) : ?>

	<!-- wp:paragraph -->
	<p><a href="<?php echo esc_url( wc_get_cart_url() ); ?>"><?php echo esc_html__( 'Return to Cart', 'pixonaut-shop' ); ?></a></p>
	<!-- /wp:paragraph -->
	<?php endif; ?>
</div>
<!-- /wp:group -->
