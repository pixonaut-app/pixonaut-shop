<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Archive;

use Pixonaut\Shop\Assets\BuiltScript;
use WC_Query;
use WP_HTML_Tag_Processor;
use WP_Query;

/**
 * The filters beside an archive's product grid (`patterns/product-filters.php`):
 * one group for every product attribute the store has, which the theme cannot
 * name, since each store has its own, and the price last.
 *
 * WooCommerce's attribute filter takes one attribute, picked in the Site
 * Editor. The panel therefore gets one of them for each attribute as it
 * renders; WooCommerce then leaves out a value no listed product has, and a
 * group without any value. A store that places attribute filters in the panel
 * itself keeps exactly those. An attribute that applies only under some
 * values of another shows nested under them (`NestedAttributeFilters`).
 */
final class ProductFilters
{
    /** The class of the Product Filters block in `patterns/product-filters.php`. */
    public const PANEL_CLASS = 'pixonaut-shop-archive-filters';

    private const FILTERS_BLOCK = 'woocommerce/product-filters';

    private const PRICE_BLOCK = 'woocommerce/product-filter-price';

    /** The button that closes WooCommerce's filter dialog on a phone. */
    private const APPLY_CLASS = 'wc-block-product-filters__apply';

    /** The query arguments WooCommerce's price filter narrows a listing with. */
    private const PRICE_ARGUMENTS = ['min_price', 'max_price'];

    private const SCRIPT_HANDLE = 'pixonaut-shop-filters-place';

    /** `src/archive/filters-place.ts`, which keeps the customer's place from one filtered page to the next. */
    private const SCRIPT = 'filters-place';

    public function register(): void
    {
        add_filter('pre_render_block', [$this, 'skip'], 10, 2);
        add_filter('render_block_data', [$this, 'add_attributes']);
        add_filter('render_block_' . self::FILTERS_BLOCK, [$this, 'finish_the_panel'], 10, 2);
    }

    /**
     * Leaves the panel out of an archive that has no product at all, where
     * there is nothing to narrow. A filtered archive that found nothing keeps
     * it, so the customer can take a filter away again.
     *
     * @param array<array-key, mixed> $parsed_block
     */
    public function skip(?string $pre_render, array $parsed_block): ?string
    {
        if (! $this->is_the_panel($parsed_block) || ! $this->main_query_is_empty() || $this->is_filtered()) {
            return $pre_render;
        }

        return '';
    }

    /**
     * @param array<array-key, mixed> $parsed_block
     *
     * @return array<array-key, mixed>
     */
    public function add_attributes(array $parsed_block): array
    {
        if (! $this->is_the_panel($parsed_block) || ! \function_exists('wc_get_attribute_taxonomies')) {
            return $parsed_block;
        }

        $inner_blocks = \is_array($parsed_block['innerBlocks'] ?? null) ? array_values($parsed_block['innerBlocks']) : [];
        $names        = array_column($inner_blocks, 'blockName');

        if ($this->has_an_attribute_filter($inner_blocks)) {
            return $parsed_block;
        }

        $groups = $this->attribute_groups();

        if ([] === $groups) {
            return $parsed_block;
        }

        $price    = array_search(self::PRICE_BLOCK, $names, true);
        $position = false === $price ? \count($inner_blocks) : (int) $price;

        array_splice($inner_blocks, $position, 0, $groups);
        $parsed_block['innerBlocks']  = $inner_blocks;
        $parsed_block['innerContent'] = $this->with_places(
            \is_array($parsed_block['innerContent'] ?? null) ? array_values($parsed_block['innerContent']) : [],
            $position,
            \count($groups)
        );

        return $parsed_block;
    }

    /**
     * The dialog's button closes it on choices that already apply, so it says
     * what the customer gets rather than WooCommerce's "Apply". The panel
     * also brings the script that keeps the customer's place.
     *
     * @param array<array-key, mixed> $block
     */
    public function finish_the_panel(string $block_content, array $block): string
    {
        if (! $this->is_the_panel($block)) {
            return $block_content;
        }

        BuiltScript::enqueue(self::SCRIPT_HANDLE, self::SCRIPT);

        $tags = new WP_HTML_Tag_Processor($block_content);

        if (! $tags->next_tag(['class_name' => self::APPLY_CLASS])) {
            return $block_content;
        }

        while ($tags->next_token()) {
            if ('#text' === $tags->get_token_name() && '' !== trim($tags->get_modifiable_text())) {
                $tags->set_modifiable_text(__('Show products', 'pixonaut-shop'));

                return $tags->get_updated_html();
            }

            if ($tags->is_tag_closer() && 'BUTTON' === $tags->get_tag()) {
                break;
            }
        }

        return $block_content;
    }

    /**
     * Whether the blocks hold an attribute filter, at any depth: a store may
     * have placed its own inside a group or a column.
     *
     * @param array<array-key, mixed> $blocks
     */
    private function has_an_attribute_filter(array $blocks): bool
    {
        foreach ($blocks as $block) {
            if (! \is_array($block)) {
                continue;
            }

            if (AttributeFilterBlock::NAME === ($block['blockName'] ?? null)) {
                return true;
            }

            if (\is_array($block['innerBlocks'] ?? null) && $this->has_an_attribute_filter($block['innerBlocks'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * One attribute filter for each of the store's product attributes, but
     * for those that show nested under a value of another
     * (`NestedAttributeFilters`).
     *
     * @return list<array<array-key, mixed>>
     */
    private function attribute_groups(): array
    {
        $attributes = array_values(array_filter(
            wc_get_attribute_taxonomies(),
            static fn (mixed $attribute): bool => \is_object($attribute) && isset($attribute->attribute_id, $attribute->attribute_label)
        ));
        $nested     = (new AttributeDependencies())->nested(array_map(AttributeFilterBlock::taxonomy_of(...), $attributes));
        $groups     = [];

        foreach ($attributes as $attribute) {
            $group = \in_array(AttributeFilterBlock::taxonomy_of($attribute), $nested, true) ? null : AttributeFilterBlock::group($attribute);

            if (null !== $group) {
                $groups[] = $group;
            }
        }

        return $groups;
    }

    /**
     * A block's content with a place for each added inner block: WordPress
     * marks an inner block's place with a null between the block's own markup.
     *
     * @param list<mixed> $inner_content
     *
     * @return list<mixed>
     */
    private function with_places(array $inner_content, int $position, int $count): array
    {
        $places = array_keys($inner_content, null, true);
        $offset = $places[$position] ?? $this->before_the_closing_tag($inner_content);

        array_splice($inner_content, $offset, 0, array_fill(0, $count, null));

        return $inner_content;
    }

    /**
     * @param list<mixed> $inner_content
     */
    private function before_the_closing_tag(array $inner_content): int
    {
        return max(0, \count($inner_content) - 1);
    }

    /**
     * @param array<array-key, mixed> $block
     */
    private function is_the_panel(array $block): bool
    {
        if (self::FILTERS_BLOCK !== ($block['blockName'] ?? null)) {
            return false;
        }

        $attributes = $block['attrs'] ?? [];
        $class_name = \is_array($attributes) ? ($attributes['className'] ?? '') : '';

        return \is_string($class_name) && \in_array(self::PANEL_CLASS, explode(' ', $class_name), true);
    }

    private function main_query_is_empty(): bool
    {
        $query = $GLOBALS['wp_query'] ?? null;

        return $query instanceof WP_Query && 0 === $query->found_posts;
    }

    /** Whether the request narrows the listing by an attribute or a price. */
    private function is_filtered(): bool
    {
        if (class_exists(WC_Query::class) && [] !== WC_Query::get_layered_nav_chosen_attributes()) {
            return true;
        }

        foreach (self::PRICE_ARGUMENTS as $argument) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a filter in a link, which changes nothing.
            if (isset($_GET[$argument])) {
                return true;
            }
        }

        return false;
    }
}
