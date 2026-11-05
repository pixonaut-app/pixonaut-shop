<?php
/**
 * Title: Product grid
 * Slug: pixonaut-shop/product-grid
 * Categories: woo-commerce
 * Inserter: yes
 *
 * @package Pixonaut\Shop
 *
 * The product cards in a grid: picture with the sale badge on it, name, one
 * line of the short description and the price. No add-to-cart button, since
 * every product is personalised on its own page. The collection inherits the
 * query of the template that shows it; a page that inserts the pattern sets
 * its own in the block's settings. Without a product to show, it shows
 * `pixonaut-shop/no-products` instead.
 *
 * The picture is the product image at full size in its own shape, so no
 * product is cropped or banded; a store picks a shape and a scale for every
 * card on the Product Image block in the Site Editor. The four columns and
 * twelve products a page match what `Archive\ProductGridDefaults` starts
 * WooCommerce's Product Catalog setting at, which sizes the archive's query.
 */

declare(strict_types=1);

?>
<!-- wp:woocommerce/product-collection {"queryId":0,"query":{"woocommerceAttributes":[],"woocommerceStockStatus":["instock","outofstock","onbackorder"],"taxQuery":{},"isProductCollectionBlock":true,"perPage":12,"pages":0,"offset":0,"postType":"product","order":"asc","orderBy":"title","author":"","search":"","exclude":[],"sticky":"","inherit":true},"tagName":"div","displayLayout":{"type":"flex","columns":4,"shrinkColumns":false},"dimensions":{"widthType":"fill"},"queryContextIncludes":["collection"],"align":"wide"} -->
<div class="wp-block-woocommerce-product-collection alignwide">
	<!-- wp:woocommerce/product-template -->
		<!-- wp:woocommerce/product-image {"showSaleBadge":false,"isDescendentOfQueryLoop":true} -->
			<!-- wp:woocommerce/product-sale-badge {"isDescendentOfQueryLoop":true,"align":"left"} /-->
		<!-- /wp:woocommerce/product-image -->

		<!-- wp:post-title {"level":2,"isLink":true,"__woocommerceNamespace":"woocommerce/product-collection/product-title"} /-->

		<!-- wp:woocommerce/product-summary {"isDescendentOfQueryLoop":true} /-->

		<!-- wp:woocommerce/product-price {"isDescendentOfQueryLoop":true} /-->
	<!-- /wp:woocommerce/product-template -->

	<!-- wp:query-pagination {"layout":{"type":"flex","justifyContent":"center"}} -->
		<!-- wp:query-pagination-previous /-->

		<!-- wp:query-pagination-numbers /-->

		<!-- wp:query-pagination-next /-->
	<!-- /wp:query-pagination -->

	<!-- wp:woocommerce/product-collection-no-results -->
		<!-- wp:pattern {"slug":"pixonaut-shop/no-products"} /-->
	<!-- /wp:woocommerce/product-collection-no-results -->
</div>
<!-- /wp:woocommerce/product-collection -->
