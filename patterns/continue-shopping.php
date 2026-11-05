<?php
/**
 * Title: Continue shopping
 * Slug: pixonaut-shop/continue-shopping
 * Inserter: no
 *
 * @package Pixonaut\Shop
 *
 * The order received page's way back to the shop, as a primary button. The
 * shop is WooCommerce's product archive, its shop page; without one, the
 * front page.
 */

declare(strict_types=1);

$pixonaut_shop_shop_url = get_post_type_archive_link( 'product' );

if ( ! is_string( $pixonaut_shop_shop_url ) ) {
	$pixonaut_shop_shop_url = home_url( '/' );
}

?>
<!-- wp:buttons -->
<div class="wp-block-buttons">
	<!-- wp:button -->
	<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $pixonaut_shop_shop_url ); ?>"><?php echo esc_html__( 'Continue shopping', 'pixonaut-shop' ); ?></a></div>
	<!-- /wp:button -->
</div>
<!-- /wp:buttons -->
