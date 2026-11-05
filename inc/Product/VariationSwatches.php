<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Product;

use Pixonaut\Shop\Assets\BuiltScript;
use Pixonaut\Shop\Blocks\BlockStyles;

/**
 * A variable product's attributes as chips, for an add-to-cart form block a
 * store gave the Use swatches style.
 *
 * WooCommerce's menus stay in the form as the one source of the choice: the
 * script (`src/product/swatches.ts`) draws a chip for each of a menu's
 * options, picks the option a chip is clicked for, and follows the menu as
 * WooCommerce narrows or clears it. Without the script, the menus show. The
 * script loads only on a page whose form carries the style.
 */
final class VariationSwatches
{
    public const SCRIPT_HANDLE = 'pixonaut-shop-swatches';

    /** The built script's name in `build/`. */
    private const SCRIPT = 'swatches';

    /** WooCommerce's variation script, which the chips' clicks reach, listens through jQuery. */
    private const SCRIPT_DEPENDENCIES = ['jquery'];

    /** Filters the add-to-cart form block's output, with the block's attributes. */
    public const FORM_BLOCK_FILTER = 'render_block_woocommerce/add-to-cart-form';

    /** The class the block style saves on the block. */
    private const STYLE_CLASS = 'is-style-' . BlockStyles::SWATCHES;

    public function register(): void
    {
        add_filter(self::FORM_BLOCK_FILTER, [$this, 'load_script'], 10, 2);
    }

    /**
     * The block's output, as it is, after loading the script when the block
     * carries the style.
     *
     * @param array{attrs?: array<string, mixed>} $block
     */
    public function load_script(string $content, array $block): string
    {
        $class_name = $block['attrs']['className'] ?? '';

        if (\is_string($class_name) && \in_array(self::STYLE_CLASS, explode(' ', $class_name), true)) {
            BuiltScript::enqueue(self::SCRIPT_HANDLE, self::SCRIPT, self::SCRIPT_DEPENDENCIES);
        }

        return $content;
    }
}
