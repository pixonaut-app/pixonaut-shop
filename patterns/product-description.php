<?php
/**
 * Title: Product description
 * Slug: pixonaut-shop/product-description
 * Inserter: no
 *
 * @package Pixonaut\Shop
 *
 * The product page's description panel, below the purchase panel: the
 * product's own description on the left and its attributes on the right,
 * one column on phones. Both are the store's product data; the heading is
 * the theme's, so it is translated here.
 */

declare(strict_types=1);

?>
<!-- wp:group {"align":"wide","className":"is-style-panel pixonaut-shop-product-description","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide is-style-panel pixonaut-shop-product-description">
	<!-- wp:heading {"level":2,"textColor":"accent","fontSize":"large"} -->
	<h2 class="wp-block-heading has-accent-color has-text-color has-large-font-size"><?php echo esc_html__( 'Product description', 'pixonaut-shop' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:woocommerce/product-details {"align":"wide"} -->
		<!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"var:preset|spacing|60"}}}} -->
		<div class="wp-block-columns">
			<!-- wp:column {"width":"55%"} -->
			<div class="wp-block-column" style="flex-basis:55%">
				<!-- wp:woocommerce/product-description /-->
			</div>
			<!-- /wp:column -->

			<!-- wp:column {"width":"45%"} -->
			<div class="wp-block-column" style="flex-basis:45%">
				<!-- wp:woocommerce/product-specifications {"fontSize":"small"} /-->
			</div>
			<!-- /wp:column -->
		</div>
		<!-- /wp:columns -->
	<!-- /wp:woocommerce/product-details -->
</div>
<!-- /wp:group -->
