<?php
/**
 * Title: Popular products
 * Slug: pixonaut-shop/popular-products
 * Categories: woo-commerce
 * Inserter: yes
 *
 * @package Pixonaut\Shop
 *
 * The four best-selling products a customer can buy now, under a heading,
 * with a link to the shop that a page already linking there removes. The
 * cards are the product grid's, so the two always look the same.
 */

declare(strict_types=1);

?>
<!-- wp:group {"align":"wide","className":"pixonaut-shop-popular-products","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide pixonaut-shop-popular-products">
	<!-- wp:group {"className":"pixonaut-shop-section-head","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"bottom"}} -->
	<div class="wp-block-group pixonaut-shop-section-head">
		<!-- wp:heading -->
		<h2 class="wp-block-heading"><?php echo esc_html__( 'Popular right now', 'pixonaut-shop' ); ?></h2>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"className":"pixonaut-shop-view-all"} -->
		<p class="pixonaut-shop-view-all"><a href="<?php echo esc_url( (string) get_post_type_archive_link( 'product' ) ); ?>"><?php echo esc_html__( 'View all', 'pixonaut-shop' ); ?></a></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->

	<!-- wp:woocommerce/product-collection {"queryId":1,"query":{"woocommerceAttributes":[],"woocommerceStockStatus":["instock","onbackorder"],"taxQuery":{},"isProductCollectionBlock":true,"perPage":4,"pages":1,"offset":0,"postType":"product","order":"desc","orderBy":"sales","author":"","search":"","exclude":[],"sticky":"","inherit":false},"tagName":"div","displayLayout":{"type":"flex","columns":4,"shrinkColumns":false},"dimensions":{"widthType":"fill"},"collection":"woocommerce/product-collection/best-sellers","hideControls":["inherit","order","filterable"],"queryContextIncludes":["collection"],"align":"wide"} -->
	<div class="wp-block-woocommerce-product-collection alignwide">
		<!-- wp:woocommerce/product-template -->
			<!-- wp:woocommerce/product-image {"showSaleBadge":false,"isDescendentOfQueryLoop":true} -->
				<!-- wp:woocommerce/product-sale-badge {"isDescendentOfQueryLoop":true,"align":"left"} /-->
			<!-- /wp:woocommerce/product-image -->

			<!-- wp:post-title {"level":2,"isLink":true,"__woocommerceNamespace":"woocommerce/product-collection/product-title"} /-->

			<!-- wp:woocommerce/product-summary {"isDescendentOfQueryLoop":true} /-->

			<!-- wp:woocommerce/product-price {"isDescendentOfQueryLoop":true} /-->
		<!-- /wp:woocommerce/product-template -->
	</div>
	<!-- /wp:woocommerce/product-collection -->
</div>
<!-- /wp:group -->
