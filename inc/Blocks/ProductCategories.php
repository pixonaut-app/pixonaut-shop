<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Blocks;

use WP_Term;

/**
 * The product categories block: the store's own top-level categories that
 * have products, as tiles with their picture and product count, or as
 * buttons with their names. The theme never knows which categories a store
 * has, so every template can place the block.
 *
 * `src/blocks/product-categories/render.php` renders it from here; its
 * attributes are declared in that block's block.json.
 */
final class ProductCategories
{
    public const LAYOUT_TILES = 'tiles';

    public const LAYOUT_BUTTONS = 'buttons';

    /** Most products first. */
    public const ORDER_BY_COUNT = 'count';

    /** The order the store dragged its categories into under Products → Categories. */
    public const ORDER_BY_STORE = 'order';

    /** How many categories each layout shows when the store has not chosen. */
    private const DEFAULT_NUMBER = [
        self::LAYOUT_TILES   => 4,
        self::LAYOUT_BUTTONS => 6,
    ];

    private const TAXONOMY = 'product_cat';

    /**
     * The term meta holding WooCommerce's count of a category's
     * catalogue-visible products, its subcategories' included. Read directly
     * because WooCommerce only swaps it into a term's `count` outside the
     * admin and Ajax, where the editor renders the block.
     */
    private const PRODUCT_COUNT_META = 'product_count_product_cat';

    /** The term meta WooCommerce keeps a category's image in, as an attachment ID. */
    private const THUMBNAIL_META = 'thumbnail_id';

    /** WooCommerce's image size for a product card's picture. */
    private const IMAGE_SIZE = 'woocommerce_thumbnail';

    /** The option naming the category WooCommerce files a product under when it has none. */
    private const DEFAULT_CATEGORY_OPTION = 'default_product_cat';

    private const BLOCK_CLASS = 'wp-block-pixonaut-shop-product-categories';

    /**
     * The block's attributes with every missing or invalid choice replaced by
     * its default.
     *
     * @param array<string, mixed> $attributes
     *
     * @return array{layout: string, number: int, orderBy: string}
     */
    public function attributes(array $attributes): array
    {
        $layout = $this->choice($attributes, 'layout', array_keys(self::DEFAULT_NUMBER), self::LAYOUT_TILES);
        $number = $attributes['number'] ?? null;

        return [
            'layout'  => $layout,
            'number'  => \is_int($number) && $number > 0 ? $number : self::DEFAULT_NUMBER[$layout],
            'orderBy' => $this->choice(
                $attributes,
                'orderBy',
                [self::ORDER_BY_COUNT, self::ORDER_BY_STORE],
                self::ORDER_BY_COUNT
            ),
        ];
    }

    /**
     * The `get_terms()` arguments for every top-level category, in the
     * store's own order. Which of them have products, and how many, is
     * WooCommerce's catalogue count, which the query cannot filter or sort
     * by, so `render()` does both; under count order, categories with as
     * many products keep the store's order. WooCommerce's default category
     * holds the products a store has not sorted, so it is no collection.
     *
     * @param array{layout: string, number: int, orderBy: string} $attributes
     *
     * @return array{taxonomy: string, parent: int, hide_empty: bool, exclude: list<int>, orderby: string, order: string}
     */
    public function query_args(array $attributes, int $default_category): array
    {
        return [
            'taxonomy'   => self::TAXONOMY,
            'parent'     => 0,
            'hide_empty' => false,
            'exclude'    => $default_category > 0 ? [$default_category] : [],
            // WooCommerce sorts by its own category order for `menu_order`.
            'orderby'    => 'menu_order',
            'order'      => 'ASC',
        ];
    }

    /**
     * The block's markup, or an empty string when the store has no category
     * with products.
     *
     * Called from the block's render.php, where `get_block_wrapper_attributes()`
     * knows the block being rendered.
     *
     * @param array<string, mixed> $attributes
     */
    public function render(array $attributes): string
    {
        $attributes = $this->attributes($attributes);
        $default    = get_option(self::DEFAULT_CATEGORY_OPTION);
        $terms      = get_terms($this->query_args($attributes, is_numeric($default) ? (int) $default : 0));

        if (!\is_array($terms)) {
            return '';
        }

        $categories = $this->with_products(
            array_values(array_filter($terms, static fn ($term): bool => $term instanceof WP_Term)),
            $attributes
        );

        if ([] === $categories) {
            return '';
        }

        $inner = self::LAYOUT_BUTTONS === $attributes['layout']
            ? $this->buttons(array_column($categories, 'term'))
            : $this->tiles($categories);

        return \sprintf(
            '<div %s>%s</div>',
            get_block_wrapper_attributes(['class' => 'is-' . $attributes['layout']]),
            $inner
        );
    }

    /**
     * The categories that have products in the catalogue, each with that
     * count, in the order chosen and no more than the number chosen.
     *
     * @param list<WP_Term> $terms in the store's own order
     * @param array{layout: string, number: int, orderBy: string} $attributes
     *
     * @return list<array{term: WP_Term, count: int}>
     */
    private function with_products(array $terms, array $attributes): array
    {
        $categories = [];

        foreach ($terms as $term) {
            $count = get_term_meta($term->term_id, self::PRODUCT_COUNT_META, true);

            if (is_numeric($count) && (int) $count > 0) {
                $categories[] = ['term' => $term, 'count' => (int) $count];
            }
        }

        if (self::ORDER_BY_COUNT === $attributes['orderBy']) {
            // usort is stable, so categories with as many products keep the store's order.
            usort($categories, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);
        }

        return \array_slice($categories, 0, $attributes['number']);
    }

    /**
     * @param list<array{term: WP_Term, count: int}> $categories
     */
    private function tiles(array $categories): string
    {
        $tiles = '';

        foreach ($categories as ['term' => $category, 'count' => $count]) {
            $link = get_term_link($category);

            if (!\is_string($link)) {
                continue;
            }

            $image_id = get_term_meta($category->term_id, self::THUMBNAIL_META, true);
            $image    = is_numeric($image_id)
                ? wp_get_attachment_image((int) $image_id, self::IMAGE_SIZE, false, ['alt' => ''])
                : '';

            $tiles .= \sprintf(
                '<li class="%1$s__tile"><a class="%1$s__link" href="%2$s">'
                . '<span class="%1$s__picture">%3$s</span>'
                . '<span class="%1$s__name">%4$s</span>'
                . '<span class="%1$s__count">%5$s</span>'
                . '</a></li>',
                self::BLOCK_CLASS,
                esc_url($link),
                $image,
                esc_html($category->name),
                esc_html(
                    \sprintf(
                        /* translators: %s: the number of products in a category. */
                        _n('%s product', '%s products', $count, 'pixonaut-shop'),
                        number_format_i18n($count)
                    )
                )
            );
        }

        return \sprintf('<ul class="%s__tiles">%s</ul>', self::BLOCK_CLASS, $tiles);
    }

    /**
     * Core's own outline buttons, rendered as blocks so they carry the
     * button styles a store's Styles sets.
     *
     * @param list<WP_Term> $categories
     */
    private function buttons(array $categories): string
    {
        $buttons = '';

        foreach ($categories as $category) {
            $link = get_term_link($category);

            if (!\is_string($link)) {
                continue;
            }

            $buttons .= \sprintf(
                '<!-- wp:button {"className":"is-style-outline"} -->'
                . '<div class="wp-block-button is-style-outline">'
                . '<a class="wp-block-button__link wp-element-button" href="%s">%s</a>'
                . '</div><!-- /wp:button -->',
                esc_url($link),
                esc_html($category->name)
            );
        }

        return do_blocks('<!-- wp:buttons --><div class="wp-block-buttons">' . $buttons . '</div><!-- /wp:buttons -->');
    }

    /**
     * @param array<string, mixed> $attributes
     * @param list<string> $allowed
     */
    private function choice(array $attributes, string $name, array $allowed, string $fallback): string
    {
        $value = $attributes[$name] ?? null;

        return \is_string($value) && \in_array($value, $allowed, true) ? $value : $fallback;
    }
}
