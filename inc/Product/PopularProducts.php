<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Product;

use WC_Product;

/**
 * The store's popular products: the best-selling products a customer can
 * buy now, which the popular-products pattern lists and the header search
 * shows before a shopper types. The pattern's collection takes its count,
 * stock statuses and order from here, so the two pick the same products.
 */
final class PopularProducts
{
    /** How many products are shown. */
    public const COUNT = 4;

    /** The stock statuses a customer can buy in: in stock, or on backorder. */
    public const STOCK_STATUSES = ['instock', 'onbackorder'];

    /** The product collection's order for the most sold first. */
    public const COLLECTION_ORDER = 'sales';

    /** The meta WooCommerce counts a product's sales in, which `COLLECTION_ORDER` sorts by. */
    private const SALES_META = 'total_sales';

    /** Products shown in the shop and its search, as the collection lists them. */
    private const CATALOGUE = 'catalog';

    private const PUBLISHED = 'publish';

    /**
     * The popular products, the most sold first.
     *
     * @return list<WC_Product>
     */
    public static function products(): array
    {
        // Without WooCommerce there are no products, and pages still render.
        if (! \function_exists('wc_get_products')) {
            return [];
        }

        $products = wc_get_products([
            'status'       => self::PUBLISHED,
            'visibility'   => self::CATALOGUE,
            'stock_status' => self::STOCK_STATUSES,
            'limit'        => self::COUNT,
            'meta_key'     => self::SALES_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- the order the collection's "sales" sorts by.
            // Equal sales, the newest product first, as the collection's "sales" order breaks a tie.
            'orderby'      => ['meta_value_num' => 'DESC', 'ID' => 'DESC'],
        ]);

        return array_values(array_filter(\is_array($products) ? $products : [], static fn (mixed $product): bool => $product instanceof WC_Product));
    }
}
