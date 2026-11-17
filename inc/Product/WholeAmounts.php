<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Product;

/**
 * Prices in whole amounts where they are whole: "389 kr" rather than
 * "389,00 kr". An amount with a fraction keeps the store's decimals
 * ("389,50 kr"), so no price is rounded. This is WooCommerce's own option
 * for it, wherever it formats a price on the server: product listings, the
 * product page, the header search, emails. The cart and checkout blocks
 * format their amounts in the browser, with the store's decimals.
 */
final class WholeAmounts
{
    /** Asks WooCommerce whether to trim a price's trailing zero decimals. */
    public const FILTER = 'woocommerce_price_trim_zeros';

    public function register(): void
    {
        add_filter(self::FILTER, '__return_true');
    }
}
