<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Archive;

/**
 * Lets WooCommerce filter, sort and page an archive in place.
 *
 * WooCommerce's product collection swaps its results without loading the
 * page again, through the Interactivity API's router, only while every block
 * inside it says it can stay on a page that changes under it. Anything else
 * makes WooCommerce load the whole page for each filter value, sort and page,
 * which closes the filter drawer and blanks the grid every time.
 *
 * The archive's panel for no results (`patterns/no-products.php`) holds
 * WooCommerce's product search, which says nothing either way: it is a plain
 * form with no script, whose search loads its results page as any form does.
 * This says so for it.
 */
final class ProductSearchNavigation
{
    private const BLOCK = 'woocommerce/product-search';

    public function register(): void
    {
        add_filter('register_block_type_args', [$this, 'declare_support'], 10, 2);
    }

    /**
     * @param array<array-key, mixed> $args
     *
     * @return array<array-key, mixed>
     */
    public function declare_support(array $args, string $block_type): array
    {
        if (self::BLOCK !== $block_type) {
            return $args;
        }

        $supports     = \is_array($args['supports'] ?? null) ? $args['supports'] : [];
        $interactive  = $supports['interactivity'] ?? null;
        // `true` alone is short for an interactive block.
        $interactive  = \is_array($interactive) ? $interactive : (true === $interactive ? ['interactive' => true] : []);

        $interactive['clientNavigation'] = true;
        $supports['interactivity']       = $interactive;
        $args['supports']                = $supports;

        return $args;
    }
}
