<?php
/**
 * Title: Front page
 * Slug: pixonaut-shop/front-page
 * Description: A start for the store's front page: the hero, the categories and the popular products.
 * Categories: featured
 * Post Types: page
 * Block Types: core/post-content
 * Viewport Width: 1440
 * Inserter: yes
 *
 * @package Pixonaut\Shop
 *
 * Offered when a store creates a page, which it then sets as its homepage in
 * Settings > Reading. Each section is locked to its content, so the store
 * edits the text, pictures and links without breaking the layout. The
 * popular products' cards are the product grid's, so the two always look
 * the same.
 */

declare(strict_types=1);

use Pixonaut\Shop\Blocks\ThemeBlocks;

$pixonaut_shop_shop = (string) get_post_type_archive_link( 'product' );

?>
<!-- wp:pattern {"slug":"pixonaut-shop/hero"} /-->

<!-- wp:group {"align":"wide","className":"pixonaut-shop-section","templateLock":"contentOnly","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide pixonaut-shop-section">
	<!-- wp:group {"className":"pixonaut-shop-section-head","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"bottom"}} -->
	<div class="wp-block-group pixonaut-shop-section-head">
		<!-- wp:heading -->
		<h2 class="wp-block-heading"><?php echo esc_html__( 'Shop by category', 'pixonaut-shop' ); ?></h2>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"className":"pixonaut-shop-view-all"} -->
		<p class="pixonaut-shop-view-all"><a href="<?php echo esc_url( $pixonaut_shop_shop ); ?>"><?php echo esc_html__( 'All categories', 'pixonaut-shop' ); ?></a></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->

	<?php if ( ThemeBlocks::is_registered( 'product-categories' ) ) : ?>
	<!-- wp:pixonaut-shop/product-categories {"number":4} /-->
<?php else : ?>
	<!-- wp:woocommerce/product-categories {"hasCount":false,"hasImage":true,"hasEmpty":false,"isHierarchical":false,"className":"pixonaut-shop-category-tiles"} /-->
<?php endif; ?>
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","className":"pixonaut-shop-section","templateLock":"contentOnly","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide pixonaut-shop-section">
	<!-- wp:group {"className":"pixonaut-shop-section-head","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"bottom"}} -->
	<div class="wp-block-group pixonaut-shop-section-head">
		<!-- wp:heading -->
		<h2 class="wp-block-heading"><?php echo esc_html__( 'Popular right now', 'pixonaut-shop' ); ?></h2>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"className":"pixonaut-shop-view-all"} -->
		<p class="pixonaut-shop-view-all"><a href="<?php echo esc_url( $pixonaut_shop_shop ); ?>"><?php echo esc_html__( 'View all', 'pixonaut-shop' ); ?></a></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->

	<!-- wp:woocommerce/product-collection {"queryId":2,"query":{"woocommerceAttributes":[],"woocommerceStockStatus":["instock","onbackorder"],"taxQuery":{},"isProductCollectionBlock":true,"perPage":8,"pages":1,"offset":0,"postType":"product","order":"desc","orderBy":"sales","author":"","search":"","exclude":[],"sticky":"","inherit":false},"tagName":"div","displayLayout":{"type":"flex","columns":4,"shrinkColumns":false},"dimensions":{"widthType":"fill"},"collection":"woocommerce/product-collection/best-sellers","hideControls":["inherit","order","filterable"],"queryContextIncludes":["collection"],"align":"wide"} -->
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
