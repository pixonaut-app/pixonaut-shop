<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Product;

/**
 * The add-to-cart form's last row: the price, then the quantity and the
 * button, which stay together when a long price takes a line of its own.
 *
 * WooCommerce's form prints the quantity and the button between these two
 * hooks, for a simple product and for a variable product's chosen variation
 * alike. Pixonaut for WooCommerce prints its designer and approval box at
 * `woocommerce_before_add_to_cart_button`, which fires before both, so they
 * stay above the row. The price's wrapper carries `data-pixonaut-price`, the
 * target the plugin rewrites when the chosen size changes the price; without
 * the plugin it keeps the price printed here. A button the plugin prints in
 * the add-to-cart button's place is inside the row, laid out as that button.
 *
 * A form that prints no row still shows the product's price, above it and
 * without the plugin's target: an out-of-stock simple product (whose form
 * WooCommerce leaves out), an external or a grouped product.
 */
final class PriceRow
{
    /** Fires before the form, while the global `$product` is the page's own product. */
    public const FORM_HOOK = 'woocommerce_before_add_to_cart_form';

    /** Fires just before the quantity input. */
    public const OPEN_HOOK = 'woocommerce_before_add_to_cart_quantity';

    /** Fires just after the add-to-cart button. */
    public const CLOSE_HOOK = 'woocommerce_after_add_to_cart_button';

    /** Filters the add-to-cart form block's output, after its hooks have fired. */
    public const FORM_BLOCK_FILTER = 'render_block_woocommerce/add-to-cart-form';

    /**
     * The product types whose form prints one quantity and one button. A
     * grouped product's form prints a quantity per product in its table,
     * with each of those products as the global `$product` in turn, and an
     * external product's form prints no quantity at all.
     */
    private const TYPES = ['simple', 'variable'];

    /** The price of the form being printed, until its row opens; `null` when it takes no row. */
    private ?string $pending_price_html = null;

    /** Whether a row is open, so the closing hook never closes what it did not open. */
    private bool $open = false;

    /** Whether the form being rendered printed a row, so the block shows no second price. */
    private bool $printed = false;

    public function register(): void
    {
        add_action(self::FORM_HOOK, [$this, 'start_form']);
        add_action(self::OPEN_HOOK, [$this, 'open']);
        add_action(self::CLOSE_HOOK, [$this, 'close']);
        add_filter(self::FORM_BLOCK_FILTER, [$this, 'with_price']);
    }

    public function start_form(): void
    {
        $this->pending_price_html = $this->price_html(self::TYPES);
    }

    public function open(): void
    {
        $price_html = $this->pending_price_html;

        if (null === $price_html) {
            return;
        }

        $this->pending_price_html = null;
        $this->open = true;
        $this->printed = true;

        echo '<div class="pixonaut-shop-buy-row">'
            . '<div class="pixonaut-shop-price" data-pixonaut-price>' . wp_kses_post($price_html) . '</div>'
            . '<div class="pixonaut-shop-buy-row__actions">';
    }

    public function close(): void
    {
        if (! $this->open) {
            return;
        }

        $this->open = false;

        echo '</div></div>';
    }

    /**
     * The add-to-cart form block's output, with the product's price before
     * it when the form printed no row.
     */
    public function with_price(string $content): string
    {
        $printed = $this->printed;
        $this->printed = false;
        $price_html = $printed ? null : $this->price_html();

        if (null === $price_html || '' === $price_html) {
            return $content;
        }

        return '<div class="pixonaut-shop-price">' . wp_kses_post($price_html) . '</div>' . $content;
    }

    /**
     * The price HTML of the product whose form is rendering, or `null` when
     * it is not one of `$types` (any type when none are given). WooCommerce
     * sets the global `$product` while it renders the form; it is read
     * without naming WooCommerce's types, which the theme's static analysis
     * does not load.
     *
     * @param list<string> $types
     */
    private function price_html(array $types = []): ?string
    {
        $product = $GLOBALS['product'] ?? null;

        if (! \is_object($product) || ! method_exists($product, 'is_type') || ! method_exists($product, 'get_price_html')) {
            return null;
        }

        if ([] !== $types && true !== $product->is_type($types)) {
            return null;
        }

        $price_html = $product->get_price_html();

        return \is_string($price_html) ? $price_html : null;
    }
}
