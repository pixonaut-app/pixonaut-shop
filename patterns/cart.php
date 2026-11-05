<?php
/**
 * Title: Cart
 * Slug: pixonaut-shop/cart
 * Categories: woo-commerce
 * Post Types: page
 * Block Types: core/post-content
 *
 * @package Pixonaut\Shop
 *
 * The Cart page's content with the designed empty state: WooCommerce's Cart
 * block, whose empty cart shows WooCommerce's own message and link back to
 * the shop in a panel, then the popular products. A store applies it to its
 * Cart page in place of WooCommerce's default (docs/SITE-EDITOR.md).
 */

declare(strict_types=1);

?>
<!-- wp:woocommerce/cart {"align":"wide"} -->
<div class="wp-block-woocommerce-cart alignwide is-loading">
	<!-- wp:woocommerce/filled-cart-block -->
	<div class="wp-block-woocommerce-filled-cart-block">
		<!-- wp:woocommerce/cart-items-block -->
		<div class="wp-block-woocommerce-cart-items-block">
			<!-- wp:woocommerce/cart-line-items-block -->
			<div class="wp-block-woocommerce-cart-line-items-block"></div>
			<!-- /wp:woocommerce/cart-line-items-block -->
		</div>
		<!-- /wp:woocommerce/cart-items-block -->

		<!-- wp:woocommerce/cart-totals-block -->
		<div class="wp-block-woocommerce-cart-totals-block">
			<!-- wp:woocommerce/cart-order-summary-block -->
			<div class="wp-block-woocommerce-cart-order-summary-block">
				<!-- wp:woocommerce/cart-order-summary-heading-block -->
				<div class="wp-block-woocommerce-cart-order-summary-heading-block"></div>
				<!-- /wp:woocommerce/cart-order-summary-heading-block -->

				<!-- wp:woocommerce/cart-order-summary-coupon-form-block -->
				<div class="wp-block-woocommerce-cart-order-summary-coupon-form-block"></div>
				<!-- /wp:woocommerce/cart-order-summary-coupon-form-block -->

				<!-- wp:woocommerce/cart-order-summary-subtotal-block -->
				<div class="wp-block-woocommerce-cart-order-summary-subtotal-block"></div>
				<!-- /wp:woocommerce/cart-order-summary-subtotal-block -->

				<!-- wp:woocommerce/cart-order-summary-fee-block -->
				<div class="wp-block-woocommerce-cart-order-summary-fee-block"></div>
				<!-- /wp:woocommerce/cart-order-summary-fee-block -->

				<!-- wp:woocommerce/cart-order-summary-discount-block -->
				<div class="wp-block-woocommerce-cart-order-summary-discount-block"></div>
				<!-- /wp:woocommerce/cart-order-summary-discount-block -->

				<!-- wp:woocommerce/cart-order-summary-shipping-block -->
				<div class="wp-block-woocommerce-cart-order-summary-shipping-block"></div>
				<!-- /wp:woocommerce/cart-order-summary-shipping-block -->

				<!-- wp:woocommerce/cart-order-summary-taxes-block -->
				<div class="wp-block-woocommerce-cart-order-summary-taxes-block"></div>
				<!-- /wp:woocommerce/cart-order-summary-taxes-block -->
			</div>
			<!-- /wp:woocommerce/cart-order-summary-block -->

			<!-- wp:woocommerce/cart-express-payment-block -->
			<div class="wp-block-woocommerce-cart-express-payment-block"></div>
			<!-- /wp:woocommerce/cart-express-payment-block -->

			<!-- wp:woocommerce/proceed-to-checkout-block -->
			<div class="wp-block-woocommerce-proceed-to-checkout-block"></div>
			<!-- /wp:woocommerce/proceed-to-checkout-block -->

			<!-- wp:woocommerce/cart-accepted-payment-methods-block -->
			<div class="wp-block-woocommerce-cart-accepted-payment-methods-block"></div>
			<!-- /wp:woocommerce/cart-accepted-payment-methods-block -->
		</div>
		<!-- /wp:woocommerce/cart-totals-block -->
	</div>
	<!-- /wp:woocommerce/filled-cart-block -->

	<!-- wp:woocommerce/empty-cart-block -->
	<div class="wp-block-woocommerce-empty-cart-block">
		<!-- wp:group {"className":"is-style-panel pixonaut-shop-empty-cart","layout":{"type":"constrained"}} -->
		<div class="wp-block-group is-style-panel pixonaut-shop-empty-cart">
			<!-- wp:pattern {"slug":"woocommerce/cart-empty-message"} /-->
		</div>
		<!-- /wp:group -->

		<!-- wp:pattern {"slug":"pixonaut-shop/popular-products"} /-->
	</div>
	<!-- /wp:woocommerce/empty-cart-block -->
</div>
<!-- /wp:woocommerce/cart -->
