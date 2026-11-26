<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Product;

/**
 * The product a "More in the theme" section is shown for, and the tag it
 * shows the product's other products with.
 */
final readonly class ThemeProductsShown
{
    public function __construct(
        public int $product_id,
        public int $tag_id,
        public string $tag_name,
    ) {
    }
}
