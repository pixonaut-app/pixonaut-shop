<?php
/**
 * Title: Footer
 * Slug: pixonaut-shop/footer
 * Categories: footer
 * Block Types: core/template-part/footer
 * Inserter: no
 *
 * @package Pixonaut\Shop
 *
 * The store's name and tagline and two menus, then a copyright line and a
 * legal menu, on the `strong` ground. The name, tagline and menus are the
 * store's own: each of the two menus is headed by the name the
 * store gave it (`Blocks\MenuHeading`). The two, and the legal menu, show
 * the menus saved as `pixonaut-shop-footer`, `pixonaut-shop-service` and
 * `pixonaut-shop-legal` where the store has them (`Header\NamedMenus`). The
 * copyright line reads the site's name and this year when the footer renders.
 */

declare(strict_types=1);

$pixonaut_shop_copyright = sprintf(
	/* translators: 1: the current year, 2: the site's name. */
	__( '© %1$s %2$s', 'pixonaut-shop' ),
	gmdate( 'Y' ),
	get_bloginfo( 'name' )
);

?>
<!-- wp:group {"align":"full","className":"pixonaut-shop-footer","backgroundColor":"strong","textColor":"strong-contrast","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull pixonaut-shop-footer has-strong-contrast-color has-strong-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--50)">
	<!-- wp:group {"align":"wide","layout":{"type":"default"}} -->
	<div class="wp-block-group alignwide">
		<!-- wp:group {"className":"pixonaut-shop-footer__menus","layout":{"type":"default"}} -->
		<div class="wp-block-group pixonaut-shop-footer__menus">
			<!-- wp:group {"className":"pixonaut-shop-footer__about","layout":{"type":"default"}} -->
			<div class="wp-block-group pixonaut-shop-footer__about">
				<!-- wp:site-title {"level":0} /-->

				<!-- wp:site-tagline /-->
			</div>
			<!-- /wp:group -->

			<!-- wp:navigation {"overlayMenu":"never","className":"pixonaut-shop-footer__links pixonaut-shop-headed-menu","layout":{"type":"flex","orientation":"vertical"}} /-->

			<!-- wp:navigation {"overlayMenu":"never","className":"pixonaut-shop-footer__service pixonaut-shop-headed-menu","layout":{"type":"flex","orientation":"vertical"}} /-->
		</div>
		<!-- /wp:group -->

		<!-- wp:group {"className":"pixonaut-shop-footer__base","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between"}} -->
		<div class="wp-block-group pixonaut-shop-footer__base">
			<!-- wp:paragraph {"fontSize":"small"} -->
			<p class="has-small-font-size"><?php echo esc_html( $pixonaut_shop_copyright ); ?></p>
			<!-- /wp:paragraph -->

			<!-- wp:navigation {"overlayMenu":"never","className":"pixonaut-shop-footer__legal","fontSize":"small","layout":{"type":"flex","flexWrap":"wrap"}} /-->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
