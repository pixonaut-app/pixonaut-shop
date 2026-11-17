<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Product;

use WC_Product;
use WP_Customize_Manager;

/**
 * How a variable product's price shows before a variation is chosen, which
 * a store picks in WooCommerce's product settings or in the Customizer:
 * "from" its lowest price, with the word in full ("From 299 kr", the
 * default) or short ("Fr. 299 kr"), or the range WooCommerce prints
 * ("299 kr – 749 kr").
 *
 * The choice is an option among WooCommerce's settings, so its settings
 * REST API (`wc/v3/settings/products`), and every tool built on it, reads
 * and changes it as it does the store's other settings. A choice a store
 * made in the Customizer before, saved as a theme mod, becomes the option
 * once.
 *
 * Only the price WooCommerce prints for the variable product itself changes,
 * on its page and in every listing; a chosen variation shows its own price.
 * The amount is WooCommerce's, formatted with its own decimals, and its
 * price suffix stays after it. Variations all at one price have no range,
 * so that price shows as WooCommerce prints it.
 *
 * The word is WooCommerce's "From:" too, wherever a price is printed from
 * its lowest amount (a grouped product, or a plugin's card of a variable
 * product), so a store shows one word for it: the choice's, or "From" in
 * full where the store shows ranges. The word is in its own `from` span,
 * as WooCommerce prints its own, for the stylesheet to set it apart.
 */
final class VariablePrice
{
    /** The option the store's choice is saved in; the theme mod it was saved in before has the same name. */
    public const SETTING = 'pixonaut_shop_variable_price';

    /** The Customizer section, and the part of WooCommerce's product settings, the choice is made in. */
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

    /** The section of WooCommerce's product settings the choice is added to: General, which has no ID. */
    private const GENERAL_PRODUCT_SETTINGS = '';

    private const VARIABLE_TYPE = 'variable';

    /** Asks WooCommerce for the variation prices as it displays them, with or without tax as the store shows prices. */
    private const FOR_DISPLAY = true;

    public function register(): void
    {
        add_filter('woocommerce_variable_price_html', [$this, 'with_price_html'], 10, 2);
        add_filter('woocommerce_get_price_html_from_text', [$this, 'from_text']);
        add_action('customize_register', [$this, 'customize']);
        add_filter('woocommerce_get_settings_products', [$this, 'with_product_settings'], 10, 2);
        add_filter('sanitize_option_' . self::SETTING, [$this, 'sanitize']);
        add_action('init', [$this, 'move_customizer_choice']);
    }

    /** The store's choice, the default while none it knows is saved. */
    public static function current(): string
    {
        return self::known(get_option(self::SETTING, self::DEFAULT));
    }

    /**
     * Saves the choice a store made in the Customizer before the choice was
     * an option as the option, once, and forgets the theme mod. A saved
     * option stays as it is.
     */
    public function move_customizer_choice(): void
    {
        $chosen = get_theme_mod(self::SETTING);

        if (false === $chosen) {
            return;
        }

        if (false === get_option(self::SETTING)) {
            add_option(self::SETTING, self::known($chosen));
        }

        remove_theme_mod(self::SETTING);
    }

    /**
     * WooCommerce's general product settings with the choice after them,
     * in a part of its own; any other section's settings as they are.
     */
    public function with_product_settings(mixed $settings, mixed $section = self::GENERAL_PRODUCT_SETTINGS): mixed
    {
        if (! \is_array($settings) || self::GENERAL_PRODUCT_SETTINGS !== $section) {
            return $settings;
        }

        $settings[] = [
            'title' => __('Prices', 'pixonaut-shop'),
            'type' => 'title',
            'id' => self::SECTION,
        ];
        $settings[] = [
            'title' => self::label(),
            'id' => self::SETTING,
            'type' => 'radio',
            'default' => self::DEFAULT,
            'options' => self::choices(),
        ];
        $settings[] = [
            'type' => 'sectionend',
            'id' => self::SECTION,
        ];

        return $settings;
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

        return $this->from_text() . wc_price((float) $lowest) . $product->get_price_suffix();
    }

    /** The word before a price from its lowest amount, in its `from` span, with the space after it. */
    public function from_text(): string
    {
        return '<span class="from">' . self::word(self::current()) . ' </span>';
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
            'type' => 'option',
            'default' => self::DEFAULT,
            'sanitize_callback' => [$this, 'sanitize'],
        ]);
        $manager->add_control(self::SETTING, [
            'type' => 'radio',
            'section' => self::SECTION,
            'label' => self::label(),
            'choices' => self::choices(),
        ]);
    }

    private static function label(): string
    {
        return __('A variable product\'s price before a variation is chosen', 'pixonaut-shop');
    }

    /** @return array<string, string> Each choice's label, by its value. */
    private static function choices(): array
    {
        return [
            self::FROM => __('"From" the lowest price', 'pixonaut-shop'),
            self::FROM_SHORT => __('"Fr." the lowest price', 'pixonaut-shop'),
            self::RANGE => __('The range from the lowest price to the highest', 'pixonaut-shop'),
        ];
    }

    private static function known(mixed $value): string
    {
        return \is_string($value) && \in_array($value, [self::FROM, self::FROM_SHORT, self::RANGE], true) ? $value : self::DEFAULT;
    }

    /** The word for a choice, escaped: short when the store picks it, else in full. */
    private static function word(string $choice): string
    {
        return self::FROM_SHORT === $choice
            /* translators: Before a product's lowest price, as in "From 299 kr". The short form of "From". */
            ? esc_html_x('Fr.', 'price from its lowest amount, short', 'pixonaut-shop')
            /* translators: Before a product's lowest price, as in "From 299 kr". */
            : esc_html_x('From', 'price from its lowest amount', 'pixonaut-shop');
    }
}
