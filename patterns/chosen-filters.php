<?php
/**
 * Title: Chosen product filters
 * Slug: pixonaut-shop/chosen-filters
 * Categories: woo-commerce
 * Inserter: no
 *
 * @package Pixonaut\Shop
 *
 * The filters a customer has chosen, under an archive's toolbar and above its
 * grid: each one a chip that takes it away again, and a link that clears them
 * all. WooCommerce prints nothing here while no filter is chosen. The filters
 * themselves open in a drawer from the toolbar (`product-filters.php`).
 */

declare(strict_types=1);

?>
<!-- wp:woocommerce/product-filters {"overlayMode":"off","className":"pixonaut-shop-archive-chosen"} -->
<div class="wp-block-woocommerce-product-filters wc-block-product-filters pixonaut-shop-archive-chosen">
	<!-- wp:woocommerce/product-filter-active -->
	<div class="wp-block-woocommerce-product-filter-active">
		<!-- wp:woocommerce/product-filter-removable-chips -->
		<div class="wp-block-woocommerce-product-filter-removable-chips wc-block-product-filter-removable-chips"></div>
		<!-- /wp:woocommerce/product-filter-removable-chips -->

		<!-- wp:woocommerce/product-filter-clear-button -->
		<!-- wp:buttons -->
		<div class="wp-block-buttons">
			<!-- wp:button {"className":"wc-block-product-filter-clear-button"} -->
			<div class="wp-block-button wc-block-product-filter-clear-button"><a class="wp-block-button__link wp-element-button"><?php echo esc_html__( 'Clear all', 'pixonaut-shop' ); ?></a></div>
			<!-- /wp:button -->
		</div>
		<!-- /wp:buttons -->
		<!-- /wp:woocommerce/product-filter-clear-button -->
	</div>
	<!-- /wp:woocommerce/product-filter-active -->
</div>
<!-- /wp:woocommerce/product-filters -->
