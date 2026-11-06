<?php
/**
 * Title: Empty mini-cart icon
 * Slug: pixonaut-shop/mini-cart-empty-icon
 * Inserter: no
 *
 * @package Pixonaut\Shop
 *
 * The bag in a circle above the empty mini-cart's message, where the theme's
 * icon block is registered; nothing where it is not, as in the wordpress.org
 * build.
 */

declare(strict_types=1);

use Pixonaut\Shop\Blocks\ThemeBlocks;

if ( ThemeBlocks::is_registered( 'icon' ) ) :
	?>
<!-- wp:pixonaut-shop/icon {"icon":"bag","circle":true,"className":"pixonaut-shop-mini-cart__empty-icon"} /-->
	<?php
endif;
