<?php
/**
 * Title: More in the theme
 * Slug: pixonaut-shop/theme-products
 * Inserter: no
 *
 * @package Pixonaut\Shop
 *
 * The product page's other products in the product's theme: a heading
 * naming the product's tag, a link to the tag's archive and four product
 * cards, as the product grid (`pixonaut-shop/product-grid`) draws them, of
 * products with that tag in any category. `Product\ThemeProducts` narrows
 * the collection to the tag as the page renders, and renders nothing for a
 * product without a tag that two other products share.
 */

declare(strict_types=1);

use Pixonaut\Shop\Product\ThemeProducts;

?>
<!-- wp:group {"tagName":"section","align":"wide","className":"<?php echo esc_attr( ThemeProducts::CLASS_NAME ); ?>","layout":{"type":"default"}} -->
<section class="wp-block-group alignwide <?php echo esc_attr( ThemeProducts::CLASS_NAME ); ?>">
	<!-- wp:group {"className":"pixonaut-shop-section-head","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"bottom"}} -->
	<div class="wp-block-group pixonaut-shop-section-head">
		<!-- wp:heading {"className":"<?php echo esc_attr( ThemeProducts::TITLE_CLASS ); ?>"} -->
		<h2 class="wp-block-heading <?php echo esc_attr( ThemeProducts::TITLE_CLASS ); ?>"><?php echo esc_html( ThemeProducts::title( ThemeProducts::PLACEHOLDER ) ); ?></h2>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"className":"<?php echo esc_attr( ThemeProducts::LINK_CLASS ); ?>"} -->
		<p class="<?php echo esc_attr( ThemeProducts::LINK_CLASS ); ?>"><a href="<?php echo esc_url( (string) get_post_type_archive_link( 'product' ) ); ?>"><?php echo esc_html__( 'See all', 'pixonaut-shop' ); ?></a></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->

	<!-- wp:woocommerce/product-collection {"queryId":2,"query":{"woocommerceAttributes":[],"woocommerceStockStatus":<?php echo wp_json_encode( ThemeProducts::STOCK_STATUSES ); ?>,"taxQuery":{},"isProductCollectionBlock":true,"perPage":<?php echo (int) ThemeProducts::COUNT; ?>,"pages":1,"offset":0,"postType":"product","order":"desc","orderBy":"<?php echo esc_attr( ThemeProducts::COLLECTION_ORDER ); ?>","author":"","search":"","exclude":[],"sticky":"","inherit":false},"tagName":"div","displayLayout":{"type":"flex","columns":<?php echo (int) ThemeProducts::COUNT; ?>,"shrinkColumns":false},"dimensions":{"widthType":"fill"},"hideControls":["inherit","order","filterable"],"queryContextIncludes":["collection"],"align":"wide"} -->
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
</section>
<!-- /wp:group -->
