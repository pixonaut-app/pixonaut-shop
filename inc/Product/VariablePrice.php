<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Product;

use WC_Product;
use WP_Customize_Manager;

/**
 * How a variable product's price shows before a variation is chosen, which
 * a store picks in the Customizer: "from" its lowest price, with the word in
 * full ("From 299 kr", the default) or short ("Fr. 299 kr"), or the range
 * WooCommerce prints ("299 kr – 749 kr").
 *
 * Only the price WooCommerce prints for the variable product itself changes,
 * on its page and in every listing; a chosen variation shows its own price.
 * The amount is WooCommerce's, formatted with its own decimals, and its
 * price suffix stays after it. Variations all at one price have no range,
 * so that price shows as WooCommerce prints it.
 */
final class VariablePrice
{
    /** The theme mod the store's choice is saved in. */
    public const SETTING = 'pixonaut_shop_variable_price';

    /** The Customizer section the choice is made in. */
    public const SECTION = 'pixonaut_shop_prices';

    /** "From" the lowest price, the word in full. */
    public const FROM = 'from';

    /** "From" the lowest price, the word short. */
    public const FROM_SHORT = 'from_short';

    /** The range from the lowest price to the highest, as WooCommerce prints it. */
    public const RANGE = 'range';

    public const DEFAULT = self::FROM;

    /** WooCommerce's Customizer panel, which holds its store settings when it is active. */
    private const WOOCOMMERCE_PANEL = 'woocommerce';

    private const VARIABLE_TYPE = 'variable';

    /** Asks WooCommerce for the variation prices as it displays them, with or without tax as the store shows prices. */
    private const FOR_DISPLAY = true;

    public function register(): void
    {
        add_filter('woocommerce_variable_price_html', [$this, 'with_price_html'], 10, 2);
        add_action('customize_register', [$this, 'customize']);
    }

    /** The store's choice, the default while none it knows is saved. */
    public static function current(): string
    {
        return self::known(get_theme_mod(self::SETTING, self::DEFAULT));
    }

    /** A saved choice, or the default when it is none the theme knows. */
    public function sanitize(mixed $value): string
    {
        return self::known($value);
    }

    /**
     * A variable product's price HTML, "from" its lowest price unless the
     * store shows the range; anything else as it is.
     */
    public function with_price_html(mixed $price_html, mixed $product): mixed
    {
        $choice = self::current();

        if (! \is_string($price_html) || '' === $price_html || self::RANGE === $choice || ! $product instanceof WC_Product || ! $product->is_type(self::VARIABLE_TYPE)) {
            return $price_html;
        }

        $prices = \is_callable([$product, 'get_variation_prices']) ? $product->get_variation_prices(self::FOR_DISPLAY) : null;
        $shown = \is_array($prices) && \is_array($prices['price'] ?? null) ? array_values($prices['price']) : [];

        // WooCommerce sorts the prices, cheapest first.
        $lowest = $shown[0] ?? null;
        $highest = end($shown);

        if (! is_numeric($lowest) || ! is_numeric($highest) || (float) $lowest === (float) $highest) {
            return $price_html;
        }

        return \sprintf(self::template($choice), wc_price((float) $lowest)) . $product->get_price_suffix();
    }

    /** Adds the choice to the Customizer, in WooCommerce's panel when it has one. */
    public function customize(WP_Customize_Manager $manager): void
    {
        $section = [
            'title' => __('Prices', 'pixonaut-shop'),
        ];

        if (null !== $manager->get_panel(self::WOOCOMMERCE_PANEL)) {
            $section['panel'] = self::WOOCOMMERCE_PANEL;
        }

        $manager->add_section(self::SECTION, $section);
        $manager->add_setting(self::SETTING, [
            'type' => 'theme_mod',
            'default' => self::DEFAULT,
            'sanitize_callback' => [$this, 'sanitize'],
        ]);
        $manager->add_control(self::SETTING, [
            'type' => 'radio',
            'section' => self::SECTION,
            'label' => __('A variable product\'s price before a variation is chosen', 'pixonaut-shop'),
            'choices' => [
                self::FROM => __('"From" the lowest price', 'pixonaut-shop'),
                self::FROM_SHORT => __('"Fr." the lowest price', 'pixonaut-shop'),
                self::RANGE => __('The range from the lowest price to the highest', 'pixonaut-shop'),
            ],
        ]);
    }

    private static function known(mixed $value): string
    {
        return \is_string($value) && \in_array($value, [self::FROM, self::FROM_SHORT, self::RANGE], true) ? $value : self::DEFAULT;
    }

    /** The "from" line for a choice, escaped, with `%s` where the price goes. */
    private static function template(string $choice): string
    {
        return self::FROM_SHORT === $choice
            /* translators: %s: a variable product's lowest price. The short form of "From %s". */
            ? esc_html_x('Fr. %s', 'variable product price, short', 'pixonaut-shop')
            /* translators: %s: a variable product's lowest price. */
            : esc_html_x('From %s', 'variable product price', 'pixonaut-shop');
    }
}
