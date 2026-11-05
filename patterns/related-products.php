<?php
/**
 * Title: Related products
 * Slug: pixonaut-shop/related-products
 * Inserter: no
 *
 * @package Pixonaut\Shop
 *
 * The product page's related products: a heading and four product cards, as
 * the product grid (`pixonaut-shop/product-grid`) draws them, from
 * WooCommerce's related collection. No add-to-cart button, since every
 * product is personalised on its own page. Renders nothing when the product
 * has no related products.
 */

declare(strict_types=1);

?>
<!-- wp:woocommerce/product-collection {"queryId":0,"query":{"perPage":4,"pages":1,"offset":0,"postType":"product","order":"asc","orderBy":"title","search":"","exclude":[],"inherit":false,"taxQuery":{},"isProductCollectionBlock":true,"featured":false,"woocommerceOnSale":false,"woocommerceStockStatus":["instock","onbackorder"],"woocommerceAttributes":[],"woocommerceHandPickedProducts":[],"filterable":false,"relatedBy":{"categories":true,"tags":true}},"tagName":"section","displayLayout":{"type":"flex","columns":4,"shrinkColumns":false},"dimensions":{"widthType":"fill"},"collection":"woocommerce/product-collection/related","hideControls":["inherit"],"queryContextIncludes":["collection"],"align":"wide","className":"pixonaut-shop-related-products"} -->
<section class="wp-block-woocommerce-product-collection alignwide pixonaut-shop-related-products">
	<!-- wp:heading {"level":2} -->
	<h2 class="wp-block-heading"><?php echo esc_html__( 'You may also like', 'pixonaut-shop' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:woocommerce/product-template -->
		<!-- wp:woocommerce/product-image {"showSaleBadge":false,"isDescendentOfQueryLoop":true} -->
			<!-- wp:woocommerce/product-sale-badge {"isDescendentOfQueryLoop":true,"align":"left"} /-->
		<!-- /wp:woocommerce/product-image -->

		<!-- wp:post-title {"level":2,"isLink":true,"__woocommerceNamespace":"woocommerce/product-collection/product-title"} /-->

		<!-- wp:woocommerce/product-summary {"isDescendentOfQueryLoop":true} /-->

		<!-- wp:woocommerce/product-price {"isDescendentOfQueryLoop":true} /-->
	<!-- /wp:woocommerce/product-template -->
</section>
<!-- /wp:woocommerce/product-collection -->
