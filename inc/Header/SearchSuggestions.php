<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Header;

use WC_Product;
use WP_Post;
use WP_Query;

/**
 * The header search's suggestions (`src/header/search.ts`), answered by the
 * results page's own request: a product search whose address also carries
 * `QUERY_VAR` answers, in place of the page, the page's count and its first
 * `LIMIT` products as JSON.
 *
 * The page and the panel so read one query, with whatever the store's
 * plugins do to it, and the panel shows what the page lists. A plugin that
 * lists a product once per card, as Pixonaut for WooCommerce lists a
 * product split by an attribute, counts each card, and each suggestion is a
 * card, with its title, picture, price and link, as the page prints them
 * in its loop.
 *
 * ```json
 * {"total": 2, "suggestions": [{"id": 12, "name": "…", "url": "…", "image": "…", "price": "<span …>…</span>"}]}
 * ```
 *
 * `image` is null for a product without a picture, and `price` is the
 * store's price HTML, kept to the inline markup a price holds.
 */
final class SearchSuggestions
{
    /** The query variable that asks a product search for its suggestions rather than its page. */
    public const QUERY_VAR = 'pixonaut_shop_suggestions';

    /** How many suggestions the panel lists. */
    public const LIMIT = 5;

    /**
     * Before WooCommerce opens the one product a search found and WordPress
     * redirects to a canonical address, both at the default priority.
     */
    private const BEFORE_REDIRECTS = 0;

    /** The post type the search looks among: the store's products. */
    private const PRODUCT = 'product';

    /** The size of a suggestion's picture: WooCommerce's, for a product in a grid. */
    private const IMAGE_SIZE = 'woocommerce_thumbnail';

    /**
     * The markup a price may keep: WooCommerce's amounts and their currency,
     * a sale's struck and current price with their screen reader text, a
     * price's suffix and the theme's lowest-price line.
     */
    private const PRICE_MARKUP = [
        'span'  => ['class' => true, 'aria-hidden' => true],
        'bdi'   => [],
        'del'   => ['aria-hidden' => true],
        'ins'   => ['aria-hidden' => true],
        'small' => ['class' => true],
    ];

    public function register(): void
    {
        add_filter('query_vars', [$this, 'query_vars']);
        add_action('template_redirect', [$this, 'respond'], self::BEFORE_REDIRECTS);
    }

    /**
     * @param mixed $vars WordPress's public query variables.
     */
    public function query_vars(mixed $vars): mixed
    {
        if (\is_array($vars)) {
            $vars[] = self::QUERY_VAR;
        }

        return $vars;
    }

    /** Answers a product search that asks for its suggestions, and ends the request. */
    public function respond(): void
    {
        if ('' === get_query_var(self::QUERY_VAR)) {
            return;
        }

        $query = $GLOBALS['wp_query'] ?? null;

        if (! $query instanceof WP_Query || ! $query->is_search() || self::PRODUCT !== $query->get('post_type')) {
            wp_send_json(['total' => 0, 'suggestions' => []], 400);
        }

        wp_send_json(self::of($query));
    }

    /**
     * A product search's count and its first suggestions, read through its
     * loop as the results page reads them.
     *
     * @return array{total: int, suggestions: list<array{id: int, name: string, url: string, image: string|null, price: string}>}
     */
    public static function of(WP_Query $query): array
    {
        $suggestions = [];

        while ($query->have_posts()) {
            if (self::LIMIT === \count($suggestions)) {
                break;
            }

            $query->the_post();
            $post = get_post();
            $product = $post instanceof WP_Post ? wc_get_product($post) : null;

            if ($post instanceof WP_Post && $product instanceof WC_Product) {
                $suggestions[] = self::suggestion($post, $product);
            }
        }

        // The loop ends here, as one that ran to its last post ends.
        if ($query->in_the_loop) {
            $query->in_the_loop = false;
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own action, fired as the loop fires it.
            do_action_ref_array('loop_end', [&$query]);
        }

        $query->rewind_posts();
        wp_reset_postdata();

        return ['total' => (int) $query->found_posts, 'suggestions' => $suggestions];
    }

    /**
     * A product as the panel shows it outside a search: one of the popular
     * products it shows before a shopper types. Null for a product with no
     * post.
     *
     * @return array{id: int, name: string, url: string, image: string|null, price: string}|null
     */
    public static function of_product(WC_Product $product): ?array
    {
        $post = get_post($product->get_id());

        return $post instanceof WP_Post ? self::suggestion($post, $product) : null;
    }

    /**
     * @return array{id: int, name: string, url: string, image: string|null, price: string}
     */
    private static function suggestion(WP_Post $post, WC_Product $product): array
    {
        $image = wp_get_attachment_image_url((int) $product->get_image_id(), self::IMAGE_SIZE);

        return [
            'id'    => $post->ID,
            'name'  => html_entity_decode(wp_strip_all_tags(get_the_title($post)), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'url'   => (string) get_permalink($post),
            'image' => \is_string($image) && '' !== $image ? $image : null,
            'price' => wp_kses($product->get_price_html(), self::PRICE_MARKUP),
        ];
    }
}
