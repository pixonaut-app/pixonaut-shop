<?php
/**
 * Title: Order next steps
 * Slug: pixonaut-shop/order-next-steps
 * Categories: woo-commerce
 * Inserter: yes
 *
 * @package Pixonaut\Shop
 *
 * What happens to an order after it is placed, for a store to put in the
 * order received page's `order-next-steps` part and write in its own words:
 * a heading and three numbered steps, each a title and one sentence. The
 * steps sit side by side down to the design's 14rem column, then stack.
 */

declare(strict_types=1);

?>
<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)">
	<!-- wp:heading -->
	<h2 class="wp-block-heading"><?php echo esc_html__( 'What happens next?', 'pixonaut-shop' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:group {"className":"pixonaut-shop-steps","layout":{"type":"grid","minimumColumnWidth":"14rem"}} -->
	<div class="wp-block-group pixonaut-shop-steps">
		<!-- wp:group {"className":"pixonaut-shop-step","layout":{"type":"default"}} -->
		<div class="wp-block-group pixonaut-shop-step">
			<!-- wp:heading {"level":3,"fontSize":"medium"} -->
			<h3 class="wp-block-heading has-medium-font-size"><?php echo esc_html__( 'Step title', 'pixonaut-shop' ); ?></h3>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"fontSize":"small"} -->
			<p class="has-small-font-size"><?php echo esc_html__( 'One sentence about this step.', 'pixonaut-shop' ); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"className":"pixonaut-shop-step","layout":{"type":"default"}} -->
		<div class="wp-block-group pixonaut-shop-step">
			<!-- wp:heading {"level":3,"fontSize":"medium"} -->
			<h3 class="wp-block-heading has-medium-font-size"><?php echo esc_html__( 'Step title', 'pixonaut-shop' ); ?></h3>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"fontSize":"small"} -->
			<p class="has-small-font-size"><?php echo esc_html__( 'One sentence about this step.', 'pixonaut-shop' ); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"className":"pixonaut-shop-step","layout":{"type":"default"}} -->
		<div class="wp-block-group pixonaut-shop-step">
			<!-- wp:heading {"level":3,"fontSize":"medium"} -->
			<h3 class="wp-block-heading has-medium-font-size"><?php echo esc_html__( 'Step title', 'pixonaut-shop' ); ?></h3>
			<!-- /wp:heading -->

			<!-- wp:paragraph {"fontSize":"small"} -->
			<p class="has-small-font-size"><?php echo esc_html__( 'One sentence about this step.', 'pixonaut-shop' ); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
