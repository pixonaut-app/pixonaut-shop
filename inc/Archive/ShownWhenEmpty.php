<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Archive;

use WP_Query;

/**
 * What an archive or a search shows only when it found nothing of its own:
 * the popular products below the shop's no-products panel and below a
 * search's panel, and the notice and categories a search's panel offers.
 *
 * The popular products sit beside the product grid rather than in its
 * no-results block: WooCommerce keeps one render state for every product
 * collection on a page, so a collection inside another's no-results block
 * resets it, and the outer collection then renders nothing even when it has
 * products. A search's notice sits in its panel, above the grid.
 */
final class ShownWhenEmpty
{
    /**
     * The class of the group in `templates/archive-product.html` and
     * `templates/product-search-results.html` that holds the popular products.
     */
    public const POPULAR_CLASS = 'pixonaut-shop-archive-popular';

    /** The class of the group `patterns/search-no-results.php` prints. */
    public const NO_RESULTS_CLASS = 'pixonaut-shop-search-no-results';

    private const CLASS_NAMES = [self::POPULAR_CLASS, self::NO_RESULTS_CLASS];

    private const GROUP_BLOCK = 'core/group';

    /**
     * The class of a product collection's wrapper, which WooCommerce prints
     * only when the collection has a product to show.
     */
    private const COLLECTION_CLASS = 'wp-block-woocommerce-product-collection';

    public function register(): void
    {
        add_filter('pre_render_block', [$this, 'skip'], 10, 2);
        add_filter('render_block_core/group', [$this, 'render'], 10, 2);
    }

    /**
     * Leaves them out of an archive or a search that found something before
     * they render, so the popular products' query never runs there.
     *
     * @param array<array-key, mixed> $parsed_block
     */
    public function skip(?string $pre_render, array $parsed_block): ?string
    {
        if (self::GROUP_BLOCK !== ($parsed_block['blockName'] ?? null) || [] === $this->classes_shown_when_empty($parsed_block)) {
            return $pre_render;
        }

        return $this->main_query_is_empty() ? $pre_render : '';
    }

    /**
     * Drops the popular products' heading too when there is no product to
     * recommend: a store whose catalogue shows nothing at all.
     *
     * @param array<array-key, mixed> $block
     */
    public function render(string $block_content, array $block): string
    {
        if (! \in_array(self::POPULAR_CLASS, $this->classes_shown_when_empty($block), true)) {
            return $block_content;
        }

        return str_contains($block_content, self::COLLECTION_CLASS) ? $block_content : '';
    }

    /**
     * @param array<array-key, mixed> $block
     *
     * @return list<string>
     */
    private function classes_shown_when_empty(array $block): array
    {
        $attributes = $block['attrs'] ?? [];
        $class_name = \is_array($attributes) ? ($attributes['className'] ?? '') : '';

        return \is_string($class_name) ? array_values(array_intersect(self::CLASS_NAMES, explode(' ', $class_name))) : [];
    }

    private function main_query_is_empty(): bool
    {
        $query = $GLOBALS['wp_query'] ?? null;

        return $query instanceof WP_Query && 0 === $query->found_posts;
    }
}
