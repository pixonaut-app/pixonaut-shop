<?php
/**
 * Title: Product filters
 * Slug: pixonaut-shop/product-filters
 * Categories: woo-commerce
 * Inserter: no
 *
 * @package Pixonaut\Shop
 *
 * The filters of an archive, in its toolbar above the grid: a button that
 * opens them in a drawer at every width, so the grid keeps the page's whole
 * width. The drawer holds a group of checkboxes for each product attribute
 * the store has, which `Archive\ProductFilters` adds above the price as the
 * panel renders, and the price, each a section that opens and closes
 * (`Archive\FilterSections`). `Archive\FilterDrawer` names the drawer and
 * gives it a footer that clears the filters or shows the products.
 */

declare(strict_types=1);

?>
<!-- wp:woocommerce/product-filters {"overlayMode":"always","overlayPosition":"left","className":"pixonaut-shop-archive-filters"} -->
<div class="wp-block-woocommerce-product-filters wc-block-product-filters pixonaut-shop-archive-filters">
	<!-- wp:woocommerce/product-filter-price -->
	<div class="wp-block-woocommerce-product-filter-price">
		<!-- wp:heading {"level":3} -->
		<h3 class="wp-block-heading"><?php echo esc_html__( 'Price', 'pixonaut-shop' ); ?></h3>
		<!-- /wp:heading -->

		<!-- wp:woocommerce/product-filter-price-slider -->
		<div class="wp-block-woocommerce-product-filter-price-slider wc-block-product-filter-price-slider"></div>
		<!-- /wp:woocommerce/product-filter-price-slider -->
	</div>
	<!-- /wp:woocommerce/product-filter-price -->
</div>
<!-- /wp:woocommerce/product-filters -->
