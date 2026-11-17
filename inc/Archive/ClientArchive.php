<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Archive;

use Pixonaut\Shop\Assets\BuiltScript;
use Pixonaut\Shop\Product\LowestPrice;
use Pixonaut\Shop\Product\VariablePrice;
use WP_Block;
use WP_Query;

/**
 * An archive that filters, sorts and counts in the browser, and loads more
 * cards as the shopper scrolls, where Pixonaut for WooCommerce publishes the
 * archive's cards as an index (`src/archive/client/`). The server renders
 * the archive for its address first, as it always has; the script takes over
 * once the index is in, and without it (no index yet, a slow network, no
 * JavaScript) the archive goes on as the server renders it.
 *
 * Beside the script goes what it draws with that only the server knows: the
 * page's size, the default sorting, the store's price markup with
 * placeholders for the amounts (`{{a}}`, `{{b}}`), its sale badge, the
 * no-products panel, and the words, translated.
 */
final class ClientArchive
{
    public const CONFIG_ID = 'pixonaut-shop-archive-config';

    public const SCRIPT_HANDLE = 'pixonaut-shop-client-archive';

    /** The theme feature Pixonaut for WooCommerce builds archive indexes for. */
    public const INDEX_SUPPORT = 'pixonaut-archive-index';

    private const SCRIPT = 'client-archive';

    /** How many times the grid loads more on its own before it asks with "Show more", so the footer can be reached. */
    public const AUTO_LOADS = 3;

    /** The amounts the price markup is rendered with, and the placeholders that replace them. */
    private const AMOUNTS = ['{{a}}' => 1.0, '{{b}}' => 2.0];

    /** After every other filter on a formatted amount, so the placeholder is what is printed. */
    private const LAST = PHP_INT_MAX;

    private const DEFAULT_SORT_OPTION = 'woocommerce_default_catalog_orderby';

    private const DEFAULT_SORT = 'menu_order';

    private const NO_PRODUCTS = '<!-- wp:pattern {"slug":"pixonaut-shop/no-products"} /-->';

    private const SALE_BADGE = '<!-- wp:woocommerce/product-sale-badge {"isDescendentOfQueryLoop":true,"align":"left"} /-->';

    private bool $printed = false;

    public function register(): void
    {
        add_action('after_setup_theme', [$this, 'add_support']);
        add_filter('render_block_woocommerce/product-filters', [$this, 'enqueue'], 10, 2);
    }

    /** Asks Pixonaut for WooCommerce for its archives' indexes: without this it builds and names none. */
    public function add_support(): void
    {
        add_theme_support(self::INDEX_SUPPORT);
    }

    /**
     * @param array<array-key, mixed> $block
     */
    public function enqueue(string $block_content, array $block): string
    {
        if (ProductFilters::is_the_panel($block) && BuiltScript::enqueue(self::SCRIPT_HANDLE, self::SCRIPT) && ! $this->printed) {
            $this->printed = true;
            add_action('wp_footer', [$this, 'print_config']);
        }

        return $block_content;
    }

    public function print_config(): void
    {
        $json = wp_json_encode(self::config(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);

        if (false !== $json) {
            printf('<script type="application/json" id="%s">%s</script>', esc_attr(self::CONFIG_ID), $json); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON with its tags and ampersands escaped.
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        $query = $GLOBALS['wp_query'] ?? null;
        $per   = $query instanceof WP_Query ? $query->get('posts_per_page') : 0;
        $size  = is_numeric($per) ? (int) $per : 0;
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- WooCommerce's own filter, read as its sorting reads it.
        $sort = apply_filters(self::DEFAULT_SORT_OPTION, get_option(self::DEFAULT_SORT_OPTION, self::DEFAULT_SORT));

        return [
            'pageSize' => max(1, $size),
            'autoLoads' => self::AUTO_LOADS,
            'defaultSort' => \is_string($sort) && '' !== $sort ? $sort : self::DEFAULT_SORT,
            'prices' => self::prices(),
            'saleBadge' => self::sale_badge(),
            'noProducts' => do_blocks(self::NO_PRODUCTS),
            'text' => self::text(),
        ];
    }

    /**
     * The store's price markup, as WooCommerce and the theme print it on a
     * card, with placeholders for the amounts.
     *
     * @return array<string, string>
     */
    private static function prices(): array
    {
        $placeholder = static function (mixed $formatted, mixed $price): mixed {
            $token = is_numeric($price) ? array_search((float) $price, self::AMOUNTS, true) : false;

            return false === $token ? $formatted : $token;
        };

        add_filter('formatted_woocommerce_price', $placeholder, self::LAST, 2);

        try {
            [$a, $b] = array_values(self::AMOUNTS);

            return [
                'amount' => wc_price($a),
                'sale' => wc_format_sale_price((string) $a, (string) $b),
                'range' => wc_format_price_range((string) $a, (string) $b),
                'from' => (new VariablePrice())->from_text() . wc_price($a),
                'varied' => VariablePrice::RANGE === VariablePrice::current() ? 'range' : 'from',
                'lowest' => LowestPrice::note($a),
            ];
        } finally {
            remove_filter('formatted_woocommerce_price', $placeholder, self::LAST);
        }
    }

    /**
     * WooCommerce's sale badge, as a card of a product on sale prints it;
     * empty while no product is on sale.
     */
    private static function sale_badge(): string
    {
        $on_sale = wc_get_product_ids_on_sale();
        $first   = [] === $on_sale ? 0 : reset($on_sale);
        $product = is_numeric($first) ? (int) $first : 0;

        if (0 === $product) {
            return '';
        }

        $parsed = parse_blocks(self::SALE_BADGE);

        return [] === $parsed ? '' : (string) (new WP_Block($parsed[0], ['postId' => $product, 'postType' => 'product']))->render();
    }

    /**
     * The words the script shows, with `%s` (or `%1$s`, `%2$s`) for numbers;
     * those that change with a number in its singular and plural.
     *
     * @return array<string, mixed>
     */
    private static function text(): array
    {
        $plural = static fn (callable $words): array => ['one' => $words(1), 'other' => $words(2)];

        return [
            /* translators: %s: how many products the archive shows. */
            'count' => $plural(static fn (int $n): string => _n('%s product', '%s products', $n, 'pixonaut-shop')),
            'none' => __('No products', 'pixonaut-shop'),
            /* translators: %s: how many products the chosen filters list. */
            'showProducts' => $plural(static fn (int $n): string => _n('Show %s product', 'Show %s products', $n, 'pixonaut-shop')),
            /* translators: %s: how many filters the customer has chosen. */
            'chosen' => $plural(static fn (int $n): string => _n('(%s chosen)', '(%s chosen)', $n, 'pixonaut-shop')),
            /* translators: %s: a chosen filter, as "Size: Large". */
            'removeFilter' => __('Remove the filter %s', 'pixonaut-shop'),
            'loading' => __('Loading more products…', 'pixonaut-shop'),
            'fetching' => __('Fetching products…', 'pixonaut-shop'),
            /* translators: 1: how many products the customer has scrolled past, 2: how many the archive shows. */
            'seen' => __('You have seen %1$s of %2$s products', 'pixonaut-shop'),
            /* translators: %s: how many products the archive shows, all of which the customer has scrolled past. */
            'seenAll' => $plural(static fn (int $n): string => _n('You have seen %s product', 'You have seen all %s products', $n, 'pixonaut-shop')),
            'showMore' => __('Show more', 'pixonaut-shop'),
            'toTop' => __('Back to top', 'pixonaut-shop'),
        ];
    }
}
