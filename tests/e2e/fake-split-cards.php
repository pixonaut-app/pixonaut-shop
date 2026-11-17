<?php

/**
 * Plugin Name: Fake Pixonaut split cards
 * Description: Lists a product once per card in the shop's listings, as Pixonaut for WooCommerce lists a split product, for the integration and end-to-end suites.
 *
 * Mapped into wp-env's tests site only (.wp-env.json), beside the fake
 * plugin (`fake-pixonaut.php`), and only while the real plugin is not there.
 *
 * The real plugin (its `Storefront\SplitCardListings` and
 * `Storefront\SplitCardFaces`) joins a split product's card rows into the
 * main query of the shop, its category pages and product search, so the
 * listing returns the product once per card and counts, pages and sorts
 * each card as a product. In the loop, a card's title and link are the
 * card's own. This does the same for a product holding a
 * `PIXONAUT_SHOP_FAKE_CARD_META` row per card, the card's value in each:
 * its title is the product's, the value after it, and its link the
 * product's with the value in `PIXONAUT_SHOP_FAKE_CARD_ARG`. Any other
 * query, and any other product, lists a product once, as it is.
 *
 * @package Pixonaut\Shop
 */

declare(strict_types=1);

/** The meta a product listed once per card holds, a row per card, as the plugin keeps its card rows. */
const PIXONAUT_SHOP_FAKE_CARD_META = '_pixonaut_shop_fake_card';

/** The argument a card's link carries its value in. */
const PIXONAUT_SHOP_FAKE_CARD_ARG = 'fake_card';

/** The joined card rows' alias, and the column the listing's rows carry a card's value in. */
const PIXONAUT_SHOP_FAKE_CARD_ALIAS = 'pixonaut_shop_fake_card';

const PIXONAUT_SHOP_FAKE_CARD_FIELD = 'pixonaut_shop_fake_card_value';

/** After WooCommerce's own clauses, such as its sorting, so a card's order comes last, as the plugin's does. */
const PIXONAUT_SHOP_FAKE_CARD_PRIORITY = 100;

/** Between a card's product's title and its value. */
const PIXONAUT_SHOP_FAKE_CARD_TITLE_SEPARATOR = ' – ';

/** The real plugin's main class; mu-plugins load before it, so it is looked for when a query runs. */
const PIXONAUT_SHOP_FAKE_CARD_REAL_PLUGIN = 'Pixonaut\\WooCommerce\\Plugin';

/**
 * The listing's cards, by the post object the listing holds, and the card
 * the loop shows now: `[product ID, value]`.
 */
final class Pixonaut_Shop_Fake_Split_Cards
{
    /** @var WeakMap<WP_Post, string> */
    public static WeakMap $cards;

    /** @var array{0: int, 1: string}|null */
    public static ?array $current = null;

    /** Whether a query is one of the shop's listings, while the fake stands in for the plugin. */
    public static function lists_cards(mixed $query): bool
    {
        return $query instanceof WP_Query
            && ! class_exists(PIXONAUT_SHOP_FAKE_CARD_REAL_PLUGIN)
            && ! is_admin()
            && $query->is_main_query()
            && 'product_query' === $query->get('wc_query');
    }

    /** The value of the card the loop shows of this product now, or null. */
    public static function current_of(mixed $post_id): ?string
    {
        $current = self::$current;

        return null !== $current && is_numeric($post_id) && (int) $post_id === $current[0] ? $current[1] : null;
    }
}

Pixonaut_Shop_Fake_Split_Cards::$cards = new WeakMap();

add_filter(
    'posts_clauses',
    static function (mixed $clauses, mixed $query): mixed {
        $alias = PIXONAUT_SHOP_FAKE_CARD_ALIAS;

        if (! is_array($clauses) || ! Pixonaut_Shop_Fake_Split_Cards::lists_cards($query) || str_contains((string) ($clauses['join'] ?? ''), " AS {$alias} ")) {
            return $clauses;
        }

        global $wpdb;
        $clauses['join']    = ($clauses['join'] ?? '') . $wpdb->prepare(' LEFT JOIN %i AS %i ON ( %i.post_id = %i.ID AND %i.meta_key = %s )', $wpdb->postmeta, $alias, $alias, $wpdb->posts, $alias, PIXONAUT_SHOP_FAKE_CARD_META);
        $clauses['fields']  = ($clauses['fields'] ?? '') . ", {$alias}.meta_value AS " . PIXONAUT_SHOP_FAKE_CARD_FIELD;
        $clauses['groupby'] = '' === trim((string) ($clauses['groupby'] ?? '')) ? '' : $clauses['groupby'] . ", {$alias}.meta_id";
        $clauses['orderby'] = '' === trim((string) ($clauses['orderby'] ?? '')) ? "{$alias}.meta_id ASC" : $clauses['orderby'] . ", {$alias}.meta_id ASC";

        return $clauses;
    },
    PIXONAUT_SHOP_FAKE_CARD_PRIORITY,
    2
);

add_filter(
    'posts_results',
    static function (mixed $posts, mixed $query): mixed {
        if (! is_array($posts) || ! Pixonaut_Shop_Fake_Split_Cards::lists_cards($query)) {
            return $posts;
        }

        foreach ($posts as $post) {
            if (! $post instanceof WP_Post || ! property_exists($post, PIXONAUT_SHOP_FAKE_CARD_FIELD)) {
                continue;
            }

            $value = $post->{PIXONAUT_SHOP_FAKE_CARD_FIELD};
            unset($post->{PIXONAUT_SHOP_FAKE_CARD_FIELD});

            if (is_string($value) && '' !== $value) {
                Pixonaut_Shop_Fake_Split_Cards::$cards[$post] = $value;
            }
        }

        return $posts;
    },
    10,
    2
);

add_action(
    'the_post',
    static function (mixed $post, mixed $query = null): void {
        $in_loop = ! $query instanceof WP_Query || $query->in_the_loop;
        $value   = $in_loop && $post instanceof WP_Post ? (Pixonaut_Shop_Fake_Split_Cards::$cards[$post] ?? null) : null;

        Pixonaut_Shop_Fake_Split_Cards::$current = null === $value ? null : [$post->ID, $value];
    },
    10,
    2
);

add_action(
    'loop_end',
    static function (): void {
        Pixonaut_Shop_Fake_Split_Cards::$current = null;
    }
);

add_filter(
    'the_title',
    static function (mixed $title, mixed $post_id = 0): mixed {
        $value = Pixonaut_Shop_Fake_Split_Cards::current_of($post_id);

        return null === $value || ! is_string($title) ? $title : $title . PIXONAUT_SHOP_FAKE_CARD_TITLE_SEPARATOR . esc_html($value);
    },
    10,
    2
);

add_filter(
    'post_type_link',
    static function (mixed $url, mixed $post): mixed {
        $value = $post instanceof WP_Post ? Pixonaut_Shop_Fake_Split_Cards::current_of($post->ID) : null;

        return null === $value || ! is_string($url) ? $url : add_query_arg(PIXONAUT_SHOP_FAKE_CARD_ARG, rawurlencode($value), $url);
    },
    10,
    2
);
