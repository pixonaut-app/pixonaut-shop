<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Blocks;

use WP_Block_Type_Registry;

/**
 * Whether one of the theme's own blocks is registered on this site.
 *
 * The build attached to GitHub releases registers them from `build/blocks/`;
 * the wordpress.org build ships without them, since the theme directory
 * leaves blocks to plugins. A pattern that holds one of them asks here
 * first and prints a fallback without it, so neither build names a block
 * its site does not have. The check is by block name alone, so the blocks
 * may move into a plugin without the patterns changing.
 */
final class ThemeBlocks
{
    /** The namespace of the theme's own blocks. */
    private const NAMESPACE = 'pixonaut-shop/';

    /**
     * @param string $name The block's name without its namespace, such as `icon`.
     */
    public static function is_registered(string $name): bool
    {
        // Without WordPress's block registry, as in a unit test, no block is.
        return class_exists(WP_Block_Type_Registry::class)
            && WP_Block_Type_Registry::get_instance()->is_registered(self::NAMESPACE . $name);
    }
}
