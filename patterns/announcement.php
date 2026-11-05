<?php
/**
 * Title: Announcement
 * Slug: pixonaut-shop/announcement
 * Categories: banner
 * Block Types: core/template-part/uncategorized
 *
 * @package Pixonaut\Shop
 *
 * A one-line bar for the header's announcement part, on the `highlight`
 * ground. A store puts it in the part and writes its own text: the theme
 * ships the part empty, so a store without an announcement shows no bar.
 */

declare(strict_types=1);

?>
<!-- wp:group {"align":"full","className":"pixonaut-shop-announcement","backgroundColor":"highlight","textColor":"highlight-contrast","style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull pixonaut-shop-announcement has-highlight-contrast-color has-highlight-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--20);padding-bottom:var(--wp--preset--spacing--20)">
	<!-- wp:paragraph {"align":"center","placeholder":"<?php echo esc_attr__( 'Write a short announcement', 'pixonaut-shop' ); ?>","style":{"typography":{"fontWeight":"700"}},"fontSize":"small"} -->
	<p class="has-text-align-center has-small-font-size" style="font-weight:700"></p>
	<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
