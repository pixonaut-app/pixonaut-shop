<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Blocks;

use WC_Cart;
use WC_Coupon;
use WC_Data_Store;
use WC_Shipping_Free_Shipping;

/**
 * The free-shipping note block: how much more the customer's cart needs for
 * free shipping, or that shipping is free, from the free-shipping method of
 * the shipping zone the cart ships to. A zone without a free-shipping method
 * that has a minimum the cart can reach shows nothing. The threshold is the
 * store's own, set under WooCommerce → Settings → Shipping.
 *
 * The mini-cart drawer keeps its cart in the Interactivity API's
 * `woocommerce` store, refreshed from the Store API whenever the cart
 * changes. The note's text is added to the Store API's cart under this
 * theme's namespace, and the block's directives read it from there, so the
 * arithmetic and the price formatting stay here and in WooCommerce, and the
 * note follows the cart without a script of its own.
 *
 * `src/blocks/free-shipping-note/render.php` renders it from here.
 */
final class FreeShippingNote
{
    /** The Store API endpoint whose response carries the note. */
    private const STORE_API_ENDPOINT = 'cart';

    /** The key the theme's data has under the Store API cart's `extensions`. */
    public const STORE_API_NAMESPACE = 'pixonaut-shop';

    /** The note's text, or an empty string for none, under that key. */
    public const NOTE_FIELD = 'free_shipping_note';

    /** The Interactivity API store the mini-cart keeps the Store API cart in, as `state.cart`. */
    private const CART_STORE = 'woocommerce';

    /** The interactive region the block opens where no mini-cart region surrounds it. */
    private const INTERACTIVE_NAMESPACE = 'pixonaut-shop/free-shipping-note';

    /** WooCommerce's ID for its free-shipping method. */
    private const FREE_SHIPPING_METHOD = 'free_shipping';

    /** WooCommerce's data store that keeps a zone's shipping methods. */
    private const ZONE_DATA_STORE = 'shipping-zone';

    /**
     * What a free-shipping method requires (its `requires` setting), as
     * WooCommerce names it: the minimum amount, either it or a free-shipping
     * coupon, or both. The two other settings, none and a coupon alone, have
     * no minimum to reach.
     */
    private const REQUIRES_MINIMUM = 'min_amount';

    private const REQUIRES_EITHER = 'either';

    private const REQUIRES_BOTH = 'both';

    /** The value of the method's "ignore discounts" setting when it is on. */
    private const IGNORES_DISCOUNTS = 'yes';

    public function register(): void
    {
        add_action('init', [$this, 'extend_store_api']);
    }

    /**
     * Adds the note to the Store API's cart, which WooCommerce's own mini-cart
     * reads. WooCommerce is required; without it there is no cart.
     */
    public function extend_store_api(): void
    {
        if (!\function_exists('woocommerce_store_api_register_endpoint_data')) {
            return;
        }

        woocommerce_store_api_register_endpoint_data([
            'endpoint'        => self::STORE_API_ENDPOINT,
            'namespace'       => self::STORE_API_NAMESPACE,
            'data_callback'   => [$this, 'cart_data'],
            'schema_callback' => [$this, 'cart_schema'],
            'schema_type'     => ARRAY_A,
        ]);
    }

    /**
     * @return array{free_shipping_note: string}
     */
    public function cart_data(): array
    {
        return [self::NOTE_FIELD => $this->text()];
    }

    /**
     * @return array<string, array{description: string, type: string, context: list<string>, readonly: bool}>
     */
    public function cart_schema(): array
    {
        return [
            self::NOTE_FIELD => [
                'description' => 'How much more the cart needs for free shipping, or that shipping is free; empty when the cart cannot get free shipping by its amount.',
                'type'        => 'string',
                'context'     => ['view', 'edit'],
                'readonly'    => true,
            ],
        ];
    }

    /**
     * The block's markup: the note, with directives that keep it in step with
     * the cart in the mini-cart drawer, hidden while there is none. Without
     * a cart, as in the editor, nothing: there is nothing to keep in step.
     */
    public function render(): string
    {
        if (null === $this->cart()) {
            return '';
        }

        $text  = $this->text();
        $field = 'state.cart.extensions.' . self::STORE_API_NAMESPACE . '.' . self::NOTE_FIELD;
        $attributes = [
            'data-wp-interactive'  => self::INTERACTIVE_NAMESPACE,
            'data-wp-text'         => self::CART_STORE . '::' . $field,
            // A negated reference to another store negates the path, after the store's name.
            'data-wp-bind--hidden' => self::CART_STORE . '::!' . $field,
        ];

        if ('' === $text) {
            $attributes['hidden'] = '';
        }

        return \sprintf('<p %s>%s</p>', get_block_wrapper_attributes($attributes), esc_html($text));
    }

    /**
     * The note for the current cart, or an empty string for none: an empty
     * cart, one that needs no shipping, one split into several packages
     * (which may not all ship free), one whose package may not ship free, or
     * a zone with no free-shipping method whose minimum the cart can reach.
     * Of several methods, the one closest to free shipping counts. Shipping
     * is only called free when WooCommerce's own method says it is available,
     * so a store's filter on it is honoured.
     */
    public function text(): string
    {
        $cart = $this->cart();

        if (null === $cart || $cart->is_empty() || !$cart->needs_shipping()) {
            return '';
        }

        $packages = $cart->get_shipping_packages();
        $package  = 1 === \count($packages) ? reset($packages) : false;

        if (!\is_array($package) || !$this->may_ship_free($package)) {
            return '';
        }

        $has_coupon = $this->has_free_shipping_coupon($cart);
        $remaining  = null;

        foreach ($this->free_shipping_methods($package) as $method) {
            $left = $this->remaining(
                $this->cart_total(
                    (float) $cart->get_displayed_subtotal(),
                    (float) $cart->get_discount_total(),
                    (float) $cart->get_discount_tax(),
                    $cart->display_prices_including_tax(),
                    self::IGNORES_DISCOUNTS === $method->ignore_discounts,
                    wc_get_price_decimals()
                ),
                (float) $method->min_amount,
                (string) $method->requires,
                $has_coupon
            );

            if (0.0 === $left && !$method->is_available($package)) {
                continue;
            }

            if (null !== $left && (null === $remaining || $left < $remaining)) {
                $remaining = $left;
            }
        }

        return null === $remaining ? '' : $this->message($remaining, $this->formatted($remaining));
    }

    /**
     * How much the cart's total lacks for free shipping, zero when shipping
     * is free, or `null` when the method gives no minimum for the cart to
     * reach: it needs no minimum, has none set, or also needs a coupon the
     * cart does not have.
     */
    public function remaining(float $total, float $minimum, string $requires, bool $has_coupon): ?float
    {
        $counts_minimum = match ($requires) {
            self::REQUIRES_MINIMUM, self::REQUIRES_EITHER => true,
            self::REQUIRES_BOTH                           => $has_coupon,
            default                                       => false,
        };

        if (!$counts_minimum || $minimum <= 0) {
            return null;
        }

        if (self::REQUIRES_EITHER === $requires && $has_coupon) {
            return 0.0;
        }

        return max(0.0, $minimum - $total);
    }

    /**
     * The cart total a free-shipping method compares with its minimum, as
     * WooCommerce's own method works it out: the subtotal as the cart shows
     * it, less its discounts (and their tax, when prices show with tax)
     * unless the method ignores them, rounded to the price's decimals.
     */
    public function cart_total(
        float $displayed_subtotal,
        float $discount_total,
        float $discount_tax,
        bool $prices_include_tax,
        bool $ignores_discounts,
        int $decimals
    ): float {
        $total = $displayed_subtotal;

        if (!$ignores_discounts) {
            $total -= $discount_total;

            if ($prices_include_tax) {
                $total -= $discount_tax;
            }
        }

        return round($total, $decimals);
    }

    /**
     * The note itself, with the amount left already formatted as a price.
     */
    public function message(float $remaining, string $formatted_amount): string
    {
        if ($remaining <= 0) {
            return __('You get free shipping', 'pixonaut-shop');
        }

        return \sprintf(
            /* translators: %s: the amount, formatted as a price, the cart needs for free shipping. */
            __('Add %s more for free shipping', 'pixonaut-shop'),
            $formatted_amount
        );
    }

    /**
     * An amount as WooCommerce formats a price, as plain text: its currency,
     * separators and decimals are the store's settings.
     */
    private function formatted(float $amount): string
    {
        return html_entity_decode(wp_strip_all_tags(wc_price($amount)), ENT_QUOTES, 'UTF-8');
    }

    /**
     * WooCommerce's cart for this request, or `null` where there is none:
     * in the editor, or without WooCommerce.
     */
    private function cart(): ?WC_Cart
    {
        $cart = \function_exists('WC') ? WC()->cart : null;

        return $cart instanceof WC_Cart ? $cart : null;
    }

    /**
     * Whether a package may ship by free shipping at all: a package can name
     * the only methods it ships by, in `ship_via`.
     *
     * @param array<mixed> $package
     */
    private function may_ship_free(array $package): bool
    {
        $ship_via = $package['ship_via'] ?? [];

        return !\is_array($ship_via) || [] === $ship_via || \in_array(self::FREE_SHIPPING_METHOD, $ship_via, true);
    }

    /**
     * The enabled free-shipping methods of the zone a package ships to, built
     * from the zone's method records. The zone's own accessor would build
     * every one of its methods and render each one's admin settings, on every
     * cart request.
     *
     * @param array<mixed> $package
     *
     * @return list<WC_Shipping_Free_Shipping>
     */
    private function free_shipping_methods(array $package): array
    {
        $zone_id = wc_get_shipping_zone($package)->get_id();
        // The data store passes its store's methods on through `__call()`.
        // @phpstan-ignore method.notFound
        $records = WC_Data_Store::load(self::ZONE_DATA_STORE)->get_methods($zone_id, true);
        $methods = [];

        foreach (\is_array($records) ? $records : [] as $record) {
            if (\is_object($record) && self::FREE_SHIPPING_METHOD === ($record->method_id ?? null) && is_numeric($record->instance_id ?? null)) {
                $methods[] = new WC_Shipping_Free_Shipping((int) $record->instance_id);
            }
        }

        return $methods;
    }

    /** Whether a coupon applied to the cart gives free shipping. */
    private function has_free_shipping_coupon(WC_Cart $cart): bool
    {
        foreach ($cart->get_coupons() as $coupon) {
            if ($coupon instanceof WC_Coupon && $coupon->get_free_shipping()) {
                return true;
            }
        }

        return false;
    }
}
