<?php
/**
 * Title: Order help
 * Slug: pixonaut-shop/order-help
 * Categories: woo-commerce
 * Inserter: yes
 *
 * @package Pixonaut\Shop
 *
 * How a customer asks about an order, for a store to put in My account's
 * `order-help` part, under an order's details, and write in its own words:
 * an outline button to the store's contact page and a short line beside it.
 */

declare(strict_types=1);

?>
<!-- wp:group {"layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"center"}} -->
<div class="wp-block-group">
	<!-- wp:buttons -->
	<div class="wp-block-buttons">
		<!-- wp:button {"className":"is-style-outline"} -->
		<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button"><?php echo esc_html__( 'Contact us about this order', 'pixonaut-shop' ); ?></a></div>
		<!-- /wp:button -->
	</div>
	<!-- /wp:buttons -->

	<!-- wp:paragraph {"textColor":"muted","fontSize":"small"} -->
	<p class="has-muted-color has-text-color has-small-font-size"><?php echo esc_html__( 'A sentence about how and when you answer.', 'pixonaut-shop' ); ?></p>
	<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
