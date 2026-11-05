<?php

/**
 * Integration suite bootstrap.
 *
 * Unlike the unit suite, this runs inside the wp-env tests container with a
 * real WordPress, WooCommerce and a real database, so nothing here is mocked.
 * wp-env mounts the theme at wp-content/themes/pixonaut-shop inside the
 * WordPress checkout, so WordPress itself is always three directories above
 * the theme root: load it from there rather than hardcoding a container path.
 *
 * @package Pixonaut\Shop
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * From tests/Integration, wp-load.php is five levels up: out of
 * Integration, out of tests (the theme root), then out of
 * wp-content/themes/pixonaut-shop to the WordPress root.
 */
const WP_LOAD_RELATIVE_PATH = '/../../../../../wp-load.php';

$wp_load = __DIR__ . WP_LOAD_RELATIVE_PATH;

if (is_readable($wp_load)) {
    define('WP_USE_THEMES', false);
    require_once $wp_load;
}

if (! function_exists('add_action')) {
    fwrite(
        STDERR,
        "WordPress is not loaded. Run this suite with: pnpm test:integration\n"
    );
    exit(1);
}

// WooCommerce inserts its blocks into a theme's templates only once this
// option names a version, as on every real store (ShopFixtures).
\Pixonaut\Shop\Tests\Support\ShopFixtures::enable_block_hooks();

// No test reaches a real service: a request no test double answered fails.
\Pixonaut\Shop\Tests\Doubles\NoNetwork::install();
