<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Archive;

/**
 * How many products the shop, category and tag archives start at on a page:
 * three rows of the grid's four columns.
 *
 * The grid inherits the archive's query, which WooCommerce sizes from its own
 * Product Catalog setting (products per row times rows per page) and which
 * the Site Editor offers no control for. The theme only gives that setting
 * its starting values, which WooCommerce writes when the theme is activated;
 * a store changes them under Customize → WooCommerce → Product Catalog, and
 * a developer through WooCommerce's own `loop_shop_per_page`.
 */
final class ProductGridDefaults
{
    /** The archive grid's columns, as `patterns/product-grid.php` sets them. */
    public const COLUMNS = 4;

    public const ROWS = 3;

    public function register(): void
    {
        add_action('after_setup_theme', [$this, 'add_support']);
    }

    public function add_support(): void
    {
        add_theme_support('woocommerce', [
            'product_grid' => ['default_rows' => self::ROWS, 'default_columns' => self::COLUMNS],
        ]);
    }
}
