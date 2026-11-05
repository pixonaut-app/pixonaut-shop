<?php
/**
 * Title: Cart title
 * Slug: pixonaut-shop/cart-title
 * Inserter: no
 *
 * @package Pixonaut\Shop
 *
 * The cart page's title row: the page's own title, and the way back to the
 * shop on the right.
 */

declare(strict_types=1);

?>
<!-- wp:group {"align":"wide","className":"pixonaut-shop-section-head pixonaut-shop-cart-head","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"bottom"}} -->
<div class="wp-block-group alignwide pixonaut-shop-section-head pixonaut-shop-cart-head">
	<!-- wp:post-title {"level":1} /-->

	<!-- wp:paragraph -->
	<p><a href="<?php echo esc_url( (string) get_post_type_archive_link( 'product' ) ); ?>"><?php echo esc_html__( 'Continue shopping', 'pixonaut-shop' ); ?></a></p>
	<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
