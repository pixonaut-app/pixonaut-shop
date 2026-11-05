<?php
/**
 * Title: No search results
 * Slug: pixonaut-shop/search-no-results
 * Inserter: no
 *
 * @package Pixonaut\Shop
 *
 * What a search that found nothing shows in its panel, below the search
 * field: a notice that names the query and suggests what to try, and the
 * store's categories as buttons to try instead. `Archive\ShownWhenEmpty`
 * renders it only when the search found nothing, and
 * `Archive\NoResultsTitle` prints the title again as it renders, in case a
 * saved template holds the query of the request that saved it.
 */

declare(strict_types=1);

?>
<!-- wp:group {"className":"pixonaut-shop-search-no-results","layout":{"type":"default"}} -->
<div class="wp-block-group pixonaut-shop-search-no-results">
	<!-- wp:group {"className":"is-style-notice","layout":{"type":"default"}} -->
	<div class="wp-block-group is-style-notice">
		<!-- wp:heading {"className":"pixonaut-shop-search-no-results-title"} -->
		<h2 class="wp-block-heading pixonaut-shop-search-no-results-title"><?php echo esc_html( \Pixonaut\Shop\Archive\NoResultsTitle::text() ); ?></h2>
		<!-- /wp:heading -->

		<!-- wp:paragraph -->
		<p><?php echo esc_html__( 'Check the spelling, try a shorter word or look in one of the categories below.', 'pixonaut-shop' ); ?></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->

	<!-- wp:paragraph {"className":"pixonaut-shop-search-categories-label","fontSize":"small"} -->
	<p class="pixonaut-shop-search-categories-label has-small-font-size"><?php echo esc_html__( 'Popular categories', 'pixonaut-shop' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:pixonaut-shop/product-categories {"layout":"buttons"} /-->
</div>
<!-- /wp:group -->
