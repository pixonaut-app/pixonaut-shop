<?php
/**
 * Title: Free shipping note
 * Slug: pixonaut-shop/free-shipping-note
 * Inserter: no
 *
 * @package Pixonaut\Shop
 *
 * The mini-cart footer's free-shipping note, where the theme's note block is
 * registered; nothing where it is not, as in the wordpress.org build.
 */

declare(strict_types=1);

use Pixonaut\Shop\Blocks\ThemeBlocks;

if ( ThemeBlocks::is_registered( 'free-shipping-note' ) ) :
	?>
<!-- wp:pixonaut-shop/free-shipping-note /-->
	<?php
endif;
