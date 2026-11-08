<?php

/**
 * Plugin Name: Mega menu for one visitor
 * Description: Shows the mega header, with the menu named in a cookie as the header's menu, to a visitor carrying that cookie, on wp-env's tests site, so the mega menu's spec runs beside the specs of the simple header.
 *
 * Mapped into wp-env's tests site only (.wp-env.json). The header variant and
 * the header's menu are each one for the whole site, so the spec cannot
 * set them without changing every other spec's header while it runs.
 */

declare(strict_types=1);

/** The cookie holding the ID of the menu to show as the header's. */
const PIXONAUT_SHOP_TESTS_MEGA_MENU_COOKIE = 'pixonaut_shop_tests_mega_menu';

/** The menu named in the cookie, or 0 without one. */
function pixonaut_shop_tests_mega_menu(): int
{
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only ever read as an int.
    return isset($_COOKIE[PIXONAUT_SHOP_TESTS_MEGA_MENU_COOKIE]) ? absint(wp_unslash($_COOKIE[PIXONAUT_SHOP_TESTS_MEGA_MENU_COOKIE])) : 0;
}

add_filter(
    'pre_option_pixonaut_shop_header',
    static fn (mixed $value): mixed => 0 === pixonaut_shop_tests_mega_menu() ? $value : 'mega'
);

// The query that looks the header's menu up by its name answers the cookie's menu.
add_filter(
    'posts_pre_query',
    static function (?array $posts, WP_Query $query): ?array {
        $menu = pixonaut_shop_tests_mega_menu();

        if (0 === $menu || 'wp_navigation' !== $query->get('post_type') || 'pixonaut-shop-header' !== $query->get('name')) {
            return $posts;
        }

        return [$menu];
    },
    10,
    2
);
