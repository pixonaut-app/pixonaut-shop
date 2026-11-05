<?php
/**
 * Title: Hero
 * Slug: pixonaut-shop/hero
 * Description: The opening of a page on a tinted band: a pill, a heading, a sentence, two buttons and a picture beside them.
 * Categories: banner, featured
 * Viewport Width: 1440
 * Inserter: yes
 *
 * @package Pixonaut\Shop
 *
 * Placeholders the store writes over, and no picture: the store chooses its
 * own. Locked to its content, so the store edits the text, the picture and
 * the links without breaking the layout. Stacked on phones, text first.
 */

declare(strict_types=1);

?>
<!-- wp:group {"align":"full","className":"is-style-band-tint-1 pixonaut-shop-hero","templateLock":"contentOnly","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull is-style-band-tint-1 pixonaut-shop-hero">
	<!-- wp:media-text {"align":"wide","mediaPosition":"right","verticalAlignment":"center"} -->
	<div class="wp-block-media-text alignwide has-media-on-the-right is-stacked-on-mobile is-vertically-aligned-center"><div class="wp-block-media-text__content">
		<!-- wp:paragraph {"className":"is-style-pill"} -->
		<p class="is-style-pill"><?php echo esc_html__( 'News', 'pixonaut-shop' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:heading {"level":1} -->
		<h1 class="wp-block-heading"><?php echo esc_html__( 'A heading about what you sell', 'pixonaut-shop' ); ?></h1>
		<!-- /wp:heading -->

		<!-- wp:paragraph -->
		<p><?php echo esc_html__( 'One or two sentences about the store.', 'pixonaut-shop' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:buttons -->
		<div class="wp-block-buttons">
			<!-- wp:button -->
			<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( (string) get_post_type_archive_link( 'product' ) ); ?>"><?php echo esc_html__( 'Shop now', 'pixonaut-shop' ); ?></a></div>
			<!-- /wp:button -->

			<!-- wp:button {"className":"is-style-outline"} -->
			<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button"><?php echo esc_html__( 'See more', 'pixonaut-shop' ); ?></a></div>
			<!-- /wp:button -->
		</div>
		<!-- /wp:buttons -->
	</div><figure class="wp-block-media-text__media"></figure></div>
	<!-- /wp:media-text -->
</div>
<!-- /wp:group -->
