<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Blocks;

use Pixonaut\Shop\Archive\CategoryImage;
use WP_Term;

/**
 * The product categories the theme's category blocks show: the children of
 * one category, or the top-level ones, in the store's own order, without
 * WooCommerce's default category, and only those with products in the
 * catalogue.
 */
final class CategoryList
{
    public const TAXONOMY = 'product_cat';

    /** The `parent` of a top-level category. */
    public const TOP_LEVEL = 0;

    /**
     * The term meta holding WooCommerce's count of a category's
     * catalogue-visible products, its subcategories' included. Read directly
     * because WooCommerce only swaps it into a term's `count` outside the
     * admin and Ajax, where the editor renders the blocks. A category
     * without products is left out, as WooCommerce's own `hide_empty` does.
     */
    private const PRODUCT_COUNT_META = 'product_count_product_cat';

    /** The option naming the category WooCommerce files a product under when it has none. */
    private const DEFAULT_CATEGORY_OPTION = 'default_product_cat';

    /**
     * The `get_terms()` arguments for every child of `$parent_id`, in the
     * store's own order. Which of them have products is WooCommerce's
     * catalogue count, which the query cannot filter by, so
     * `with_products()` does. WooCommerce's default category holds the
     * products a store has not sorted, so it is no collection.
     *
     * @return array{taxonomy: string, parent: int, hide_empty: bool, exclude: list<int>, orderby: string, order: string}
     */
    public static function query_args(int $parent_id, int $default_category): array
    {
        return [
            'taxonomy'   => self::TAXONOMY,
            'parent'     => $parent_id,
            'hide_empty' => false,
            'exclude'    => $default_category > 0 ? [$default_category] : [],
            // WooCommerce sorts by its own category order for `menu_order`.
            'orderby'    => 'menu_order',
            'order'      => 'ASC',
        ];
    }

    /**
     * The categories that have products in the catalogue, each with that
     * count, in the order given.
     *
     * @param list<WP_Term> $terms
     *
     * @return list<array{term: WP_Term, count: int}>
     */
    public static function with_products(array $terms): array
    {
        $categories = [];

        foreach ($terms as $term) {
            $count = get_term_meta($term->term_id, self::PRODUCT_COUNT_META, true);

            if (is_numeric($count) && (int) $count > 0) {
                $categories[] = ['term' => $term, 'count' => (int) $count];
            }
        }

        return $categories;
    }

    /**
     * Every child of `$parent_id` (`TOP_LEVEL` for the top-level categories)
     * with products, in the store's order, their pictures loaded together.
     * `get_terms()` loads the terms and their meta in one query each.
     *
     * @return list<WP_Term>
     */
    public static function children(int $parent_id): array
    {
        $terms = get_terms(self::query_args($parent_id, self::default_category()));

        if (! \is_array($terms)) {
            return [];
        }

        $categories = array_column(
            self::with_products(array_values(array_filter($terms, static fn ($term): bool => $term instanceof WP_Term))),
            'term'
        );

        CategoryImage::prime(array_map(static fn (WP_Term $term): int => $term->term_id, $categories));

        return $categories;
    }

    /** The category WooCommerce files a product under when it has none, or 0. */
    public static function default_category(): int
    {
        $default = get_option(self::DEFAULT_CATEGORY_OPTION);

        return is_numeric($default) ? (int) $default : 0;
    }

    /**
     * The category with this slug, or null when the store has none.
     */
    public static function by_slug(string $slug): ?WP_Term
    {
        if ('' === $slug) {
            return null;
        }

        $term = get_term_by('slug', $slug, self::TAXONOMY);

        return $term instanceof WP_Term ? $term : null;
    }
}
