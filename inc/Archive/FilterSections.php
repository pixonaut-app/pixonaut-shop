<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Archive;

/**
 * Each group in an archive's filter drawer, an attribute's values or the
 * price, as a section that opens and closes under its name: a native
 * `details`, so it needs no script and a keyboard and a screen reader know
 * it. A section with a chosen value opens, so the customer sees what they
 * chose; while nothing is chosen the first one does. The rest stay closed and
 * the drawer shows every group's name at a glance.
 *
 * It wraps a filter in the archive's panel that starts with its heading: the
 * groups the panel gets (`AttributeFilterBlock::group()`), the price in
 * `patterns/product-filters.php` and those a store places in it itself.
 * Another Product Filters block keeps its groups as they are. An attribute nested under a value of
 * another has no heading of its own and stays as it is
 * (`NestedAttributeFilters`), as does a filter WooCommerce hid for want of a
 * value. It runs before the nesting, which then finds the values where they
 * were, inside the section.
 */
final class FilterSections
{
    /** The class of a section. */
    public const SECTION_CLASS = 'pixonaut-shop-filter-section';

    /** Names the section across a page load, for the script that keeps the drawer's place. */
    public const KEY_ATTRIBUTE = 'data-pixonaut-shop-section';

    /** The key of the price's section; an attribute's is its taxonomy. */
    public const PRICE_KEY = 'price';

    private const PRICE_BLOCK = 'woocommerce/product-filter-price';

    private const FILTERS_BLOCK = 'woocommerce/product-filters';

    /** Before `NestedAttributeFilters` adds a dependent's values after the label of the value it shows under. */
    private const BEFORE_NESTING = 5;

    /** After it, when a chosen value nested in a section opens it too. */
    private const AFTER_NESTING = 20;

    /** WooCommerce's class on a filter without any value to show. */
    private const HIDDEN_CLASS = 'wc-block-product-filter--hidden';

    private const INPUT_CLASS = 'wc-block-product-filter-checkbox-list__input';

    /** A heading as core/heading saves it, at the start of the filter. */
    private const HEADING = '/\A(\s*<[^>]+>)\s*(<h([1-6])\b[^>]*>.*?<\/h\3>)/s';

    /** How many sections the panel has so far, so the first opens while nothing is chosen. */
    private int $sections = 0;

    /** Whether the archive's panel is rendering: another Product Filters block keeps its groups as they are. */
    private bool $in_the_panel = false;

    /** Before `FilterDrawer` and anything else reads the finished panel. */
    private const BEFORE_THE_PANEL_IS_READ = 1;

    public function register(): void
    {
        add_filter('render_block_data', [$this, 'start']);
        add_filter('render_block_' . self::FILTERS_BLOCK, [$this, 'finish'], self::BEFORE_THE_PANEL_IS_READ, 2);
        add_filter('render_block_' . AttributeFilterBlock::NAME, [$this, 'attribute'], self::BEFORE_NESTING, 2);
        add_filter('render_block_' . AttributeFilterBlock::NAME, [$this, 'open_on_a_nested_choice'], self::AFTER_NESTING);
        add_filter('render_block_' . self::PRICE_BLOCK, [$this, 'price'], self::BEFORE_NESTING);
    }

    /**
     * Counts the sections afresh as the panel starts to render.
     *
     * @param array<array-key, mixed> $parsed_block
     *
     * @return array<array-key, mixed>
     */
    public function start(array $parsed_block): array
    {
        if (ProductFilters::is_the_panel($parsed_block)) {
            $this->sections     = 0;
            $this->in_the_panel = true;
        }

        return $parsed_block;
    }

    /**
     * The panel has rendered its groups.
     *
     * @param array<array-key, mixed> $block
     */
    public function finish(string $block_content, array $block): string
    {
        if (ProductFilters::is_the_panel($block)) {
            $this->in_the_panel = false;
        }

        return $block_content;
    }

    /**
     * @param array<array-key, mixed> $parsed_block
     */
    public function attribute(string $block_content, array $parsed_block): string
    {
        $attributes = \is_array($parsed_block['attrs'] ?? null) ? $parsed_block['attrs'] : [];
        $id         = $attributes['attributeId'] ?? null;
        $taxonomy   = is_numeric($id) && \function_exists('wc_attribute_taxonomy_name_by_id') ? wc_attribute_taxonomy_name_by_id((int) $id) : '';

        return $this->section($block_content, '' === $taxonomy ? 'attribute' : $taxonomy, $this->has_a_chosen_value($block_content));
    }

    /**
     * Opens a section once a value nested in it is chosen: a dependent
     * attribute's value chosen from the address shows under its parent's
     * value though the parent is not chosen, and must show to be cleared.
     */
    public function open_on_a_nested_choice(string $block_content): string
    {
        $markup = new FilterListMarkup($block_content);

        if (! $this->in_the_panel || ! $markup->next_tag() || ! $markup->has_class(self::SECTION_CLASS) || null !== $markup->get_attribute('open') || ! $this->has_a_chosen_value($block_content)) {
            return $block_content;
        }

        $markup->set_attribute('open', true);

        return $markup->get_updated_html();
    }

    public function price(string $block_content): string
    {
        return $this->section($block_content, self::PRICE_KEY, ProductFilters::is_filtered_by_price());
    }

    private function section(string $block_content, string $key, bool $chosen): string
    {
        if (! $this->in_the_panel || $this->shows_nothing($block_content) || 1 !== preg_match(self::HEADING, $block_content, $parts, PREG_OFFSET_CAPTURE)) {
            return $block_content;
        }

        [$opener, $heading] = [$parts[1][0], $parts[2][0]];
        $after              = substr($block_content, $parts[0][1] + \strlen($parts[0][0]));
        $open               = $chosen || (0 === $this->sections && ! ProductFilters::is_filtered());

        ++$this->sections;

        return \sprintf(
            '<details class="%1$s" %2$s="%3$s"%4$s><summary class="%1$s__summary">%5$s<span class="%1$s__mark" aria-hidden="true"></span></summary>%6$s%7$s</details>',
            esc_attr(self::SECTION_CLASS),
            esc_attr(self::KEY_ATTRIBUTE),
            esc_attr($key),
            $open ? ' open' : '',
            $heading,
            $opener,
            $after
        );
    }

    private function has_a_chosen_value(string $block_content): bool
    {
        $markup = new FilterListMarkup($block_content);

        while ($markup->next_tag(['tag_name' => 'INPUT', 'class_name' => self::INPUT_CLASS])) {
            if (null !== $markup->get_attribute('checked')) {
                return true;
            }
        }

        return false;
    }

    /** Whether WooCommerce hid the filter, for want of a value any listed product has. */
    private function shows_nothing(string $block_content): bool
    {
        $markup = new FilterListMarkup($block_content);

        return ! $markup->next_tag() || $markup->has_class(self::HIDDEN_CLASS);
    }
}
