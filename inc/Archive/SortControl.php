<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Archive;

use WP_HTML_Tag_Processor;

/**
 * The sorting in an archive's toolbar, WooCommerce's Catalog Sorting block,
 * looks like the filters' button beside it: the form around its menu takes
 * the theme's button class, so it follows the store's Styles for buttons as
 * that button does, and the menu takes the button's font from it, as
 * WooCommerce has its menu inherit its font. `styles/_archive.css` draws it
 * as an outline button with its own chevron and styles its list of orders
 * where the browser lets it.
 *
 * It stays the block's own menu, so a change still sorts in place through
 * WooCommerce's script, and a keyboard and a screen reader know it.
 */
final class SortControl
{
    private const BLOCK = 'woocommerce/catalog-sorting';

    /** WooCommerce's form around the menu. */
    private const FORM_CLASS = 'woocommerce-ordering';

    /** The theme's button look, from `theme.json`'s `styles.elements.button`. */
    private const BUTTON_CLASS = 'wp-element-button';

    public function register(): void
    {
        add_filter('render_block_' . self::BLOCK, [$this, 'as_a_button']);
    }

    public function as_a_button(string $block_content): string
    {
        $markup = new WP_HTML_Tag_Processor($block_content);

        if (! $markup->next_tag(['tag_name' => 'form', 'class_name' => self::FORM_CLASS])) {
            return $block_content;
        }

        $markup->add_class(self::BUTTON_CLASS);

        return $markup->get_updated_html();
    }
}
