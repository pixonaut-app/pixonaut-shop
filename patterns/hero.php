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
 *
 * Where the store's hero is saved in its `pixonaut_shop_hero` option
 * (`FrontPage\Hero`), the hero shows that instead: its heading, its
 * sentence, one button and its picture, with no pill. A page holding a
 * reference to this pattern shows the option as it is now; a page the store
 * has edited holds the blocks it saved.
 */

declare(strict_types=1);

use Pixonaut\Shop\FrontPage\Hero;

$pixonaut_shop_hero = Hero::from_option();

if ( null !== $pixonaut_shop_hero ) :
	?>
<!-- wp:group {"align":"full","className":"is-style-band-tint-1 pixonaut-shop-hero","templateLock":"contentOnly","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull is-style-band-tint-1 pixonaut-shop-hero">
	<!-- wp:media-text {"align":"wide","mediaPosition":"right","mediaId":<?php echo (int) $pixonaut_shop_hero->image_id; ?>,"mediaType":"image","verticalAlignment":"center"} -->
	<div class="wp-block-media-text alignwide has-media-on-the-right is-stacked-on-mobile is-vertically-aligned-center"><div class="wp-block-media-text__content">
		<!-- wp:heading {"level":1} -->
		<h1 class="wp-block-heading"><?php echo esc_html( $pixonaut_shop_hero->heading ); ?></h1>
		<!-- /wp:heading -->

		<!-- wp:paragraph -->
		<p><?php echo esc_html( $pixonaut_shop_hero->text ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:buttons -->
		<div class="wp-block-buttons">
			<!-- wp:button -->
			<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $pixonaut_shop_hero->button_url ); ?>"><?php echo esc_html( $pixonaut_shop_hero->button_label ); ?></a></div>
			<!-- /wp:button -->
		</div>
		<!-- /wp:buttons -->
	</div><figure class="wp-block-media-text__media"><img src="<?php echo esc_url( $pixonaut_shop_hero->image_url ); ?>" alt="<?php echo esc_attr( $pixonaut_shop_hero->image_alt ); ?>" class="wp-image-<?php echo (int) $pixonaut_shop_hero->image_id; ?> size-<?php echo esc_attr( Hero::IMAGE_SIZE ); ?>"/></figure></div>
	<!-- /wp:media-text -->
</div>
<!-- /wp:group -->
	<?php
	return;
endif;

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
