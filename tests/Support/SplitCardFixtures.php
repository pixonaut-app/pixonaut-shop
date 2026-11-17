<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Support;

use RuntimeException;
use WC_Product;
use WC_Product_Simple;

/**
 * Products a search finds, one of them listed once per card
 * (`tests/e2e/fake-split-cards.php`), for the header search's suggestions
 * and the results page they agree with. Idempotent, as the suites' other
 * fixtures: a run makes or updates them, and `forget()` removes them.
 */
final class SplitCardFixtures
{
    /** The word every product here has in its name, and no other product on the tests site. */
    public const QUERY = 'Compass';

    /** The product listed once per card, and its cards' values, in the order the listing shows them. */
    public const SPLIT_SLUG = 'searchable-compass-print';

    public const SPLIT_NAME = 'Compass print';

    public const CARDS = ['Sea', 'Forest'];

    /** A product listed once, as it is. */
    public const PLAIN_SLUG = 'searchable-compass-mug';

    public const PLAIN_NAME = 'Compass mug';

    /** The meta the fake reads a product's cards from (`PIXONAUT_SHOP_FAKE_CARD_META`). */
    private const CARD_META = '_pixonaut_shop_fake_card';

    private const PRICE = '149';

    /** Makes the products, the split one with a card per value, and returns the split one's ID. */
    public static function make(): int
    {
        self::product(self::PLAIN_SLUG, self::PLAIN_NAME);
        $split = self::product(self::SPLIT_SLUG, self::SPLIT_NAME)->get_id();

        delete_post_meta($split, self::CARD_META);

        foreach (self::CARDS as $value) {
            add_post_meta($split, self::CARD_META, $value);
        }

        return $split;
    }

    /** The products gone again. */
    public static function forget(): void
    {
        foreach ([self::SPLIT_SLUG, self::PLAIN_SLUG] as $slug) {
            $existing = get_page_by_path($slug, OBJECT, 'product');

            if (null !== $existing) {
                wp_delete_post($existing->ID, true);
            }
        }
    }

    private static function product(string $slug, string $name): WC_Product
    {
        $existing = get_page_by_path($slug, OBJECT, 'product');
        $product  = null === $existing ? new WC_Product_Simple() : wc_get_product($existing->ID);

        if (! $product instanceof WC_Product) {
            throw new RuntimeException('The fixture product is not a WooCommerce product.');
        }

        $product->set_name($name);
        $product->set_slug($slug);
        $product->set_regular_price(self::PRICE);
        $product->set_status('publish');
        $product->save();

        return $product;
    }
}
