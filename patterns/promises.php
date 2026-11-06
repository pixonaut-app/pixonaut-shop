<?php
/**
 * Title: Promises
 * Slug: pixonaut-shop/promises
 * Categories: featured
 * Block Types: core/template-part/promises
 * Inserter: yes
 *
 * @package Pixonaut\Shop
 *
 * A start for the store's promises, inserted into the Promises part in the
 * Site Editor: four columns, each an icon in a circle beside a short heading
 * and one sentence, which the store replaces with its own. Four to a row at
 * wide size; on phones the columns stack, as the design's are.
 */

declare(strict_types=1);

use Pixonaut\Shop\Blocks\ThemeBlocks;

$pixonaut_shop_title = __( 'A short promise', 'pixonaut-shop' );
$pixonaut_shop_text  = __( 'One sentence about it.', 'pixonaut-shop' );

?>
<!-- wp:columns {"align":"wide","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|50","left":"var:preset|spacing|50"}}}} -->
<div class="wp-block-columns alignwide">
	<!-- wp:column -->
	<div class="wp-block-column">
		<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"top"}} -->
		<div class="wp-block-group">
			<?php if ( ThemeBlocks::is_registered( 'icon' ) ) : ?>
			<!-- wp:pixonaut-shop/icon {"icon":"truck","circle":true} /-->
			<?php endif; ?>

			<!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","orientation":"vertical"}} -->
			<div class="wp-block-group">
				<!-- wp:heading {"level":3,"fontSize":"medium"} -->
				<h3 class="wp-block-heading has-medium-font-size"><?php echo esc_html( $pixonaut_shop_title ); ?></h3>
				<!-- /wp:heading -->

				<!-- wp:paragraph {"fontSize":"small"} -->
				<p class="has-small-font-size"><?php echo esc_html( $pixonaut_shop_text ); ?></p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:column -->

	<!-- wp:column -->
	<div class="wp-block-column">
		<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"top"}} -->
		<div class="wp-block-group">
			<?php if ( ThemeBlocks::is_registered( 'icon' ) ) : ?>
			<!-- wp:pixonaut-shop/icon {"icon":"gift","circle":true} /-->
			<?php endif; ?>

			<!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","orientation":"vertical"}} -->
			<div class="wp-block-group">
				<!-- wp:heading {"level":3,"fontSize":"medium"} -->
				<h3 class="wp-block-heading has-medium-font-size"><?php echo esc_html( $pixonaut_shop_title ); ?></h3>
				<!-- /wp:heading -->

				<!-- wp:paragraph {"fontSize":"small"} -->
				<p class="has-small-font-size"><?php echo esc_html( $pixonaut_shop_text ); ?></p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:column -->

	<!-- wp:column -->
	<div class="wp-block-column">
		<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"top"}} -->
		<div class="wp-block-group">
			<?php if ( ThemeBlocks::is_registered( 'icon' ) ) : ?>
			<!-- wp:pixonaut-shop/icon {"icon":"star","circle":true} /-->
			<?php endif; ?>

			<!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","orientation":"vertical"}} -->
			<div class="wp-block-group">
				<!-- wp:heading {"level":3,"fontSize":"medium"} -->
				<h3 class="wp-block-heading has-medium-font-size"><?php echo esc_html( $pixonaut_shop_title ); ?></h3>
				<!-- /wp:heading -->

				<!-- wp:paragraph {"fontSize":"small"} -->
				<p class="has-small-font-size"><?php echo esc_html( $pixonaut_shop_text ); ?></p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:column -->

	<!-- wp:column -->
	<div class="wp-block-column">
		<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"top"}} -->
		<div class="wp-block-group">
			<?php if ( ThemeBlocks::is_registered( 'icon' ) ) : ?>
			<!-- wp:pixonaut-shop/icon {"icon":"lock","circle":true} /-->
			<?php endif; ?>

			<!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","orientation":"vertical"}} -->
			<div class="wp-block-group">
				<!-- wp:heading {"level":3,"fontSize":"medium"} -->
				<h3 class="wp-block-heading has-medium-font-size"><?php echo esc_html( $pixonaut_shop_title ); ?></h3>
				<!-- /wp:heading -->

				<!-- wp:paragraph {"fontSize":"small"} -->
				<p class="has-small-font-size"><?php echo esc_html( $pixonaut_shop_text ); ?></p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:column -->
</div>
<!-- /wp:columns -->
