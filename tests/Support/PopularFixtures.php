<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Support;

use Pixonaut\Shop\Search\PopularSearches;

/**
 * What the header search offers before a shopper types, for the e2e suite:
 * a search shoppers made often enough to be suggested, and the searchable
 * fixture product (`ShopFixtures::searchable_products()`) as the store's
 * best seller.
 */
final class PopularFixtures
{
    /** The popular search: a word both searchable fixture products' names start with. */
    public const SEARCH = 'Lantern';

    /** More sales than any other product on the tests site has. */
    private const BEST_SELLER_SALES = 1_000_000;

    private const SALES_META = 'total_sales';

    public static function make(): void
    {
        delete_option(PopularSearches::OPTION);

        for ($made = 0; $made < PopularSearches::MIN_SEARCHES; ++$made) {
            PopularSearches::count(self::SEARCH, time());
        }

        $product = get_page_by_path(ShopFixtures::SEARCHABLE_PRODUCT_SLUG, OBJECT, 'product');

        if (null !== $product) {
            update_post_meta($product->ID, self::SALES_META, self::BEST_SELLER_SALES);
        }
    }

    public static function forget(): void
    {
        delete_option(PopularSearches::OPTION);
    }
}
