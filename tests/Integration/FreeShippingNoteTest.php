<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Pixonaut\Shop\Blocks\FreeShippingNote;
use WC_Cache_Helper;
use WC_Coupon;
use WC_Product_Simple;
use WC_Shipping_Zone;
use WP_REST_Request;

/**
 * The free-shipping note against a shipping zone made here, with a
 * free-shipping method and a minimum order amount, and a cart below and
 * above that minimum: the note itself, the block, and the Store API's cart,
 * which the mini-cart drawer reads it from.
 */
final class FreeShippingNoteTest extends TestCase
{
    private const BLOCK = 'pixonaut-shop/free-shipping-note';

    /** The country the zone ships to and the customer lives in. */
    private const ZONE_COUNTRY = 'SE';

    /** A country no zone made here covers, which has no shipping methods. */
    private const OTHER_COUNTRY = 'NO';

    private const MINIMUM = '499';

    private const PRICE = '249';

    /** Two products at the price are still under the minimum; three are over it. */
    private const OVER_MINIMUM = 3;

    /** The amount one product at the price leaves to reach the minimum. */
    private const LEFT_AFTER_ONE = 250;

    /** WooCommerce's option for where it ships; `disabled` turns shipping off. */
    private const SHIP_TO_COUNTRIES_OPTION = 'woocommerce_ship_to_countries';

    /** Ships to every country the store sells to. */
    private const SHIP_TO_SELLING_COUNTRIES = '';

    private WC_Shipping_Zone $zone;

    private mixed $ship_to_countries;

    private int $instance_id;

    private int $product_id;

    private ?int $coupon_id = null;

    protected function setUp(): void
    {
        parent::setUp();

        // The end-to-end fixtures turn shipping off on the tests site, for their checkout.
        $this->ship_to_countries = get_option(self::SHIP_TO_COUNTRIES_OPTION);
        update_option(self::SHIP_TO_COUNTRIES_OPTION, self::SHIP_TO_SELLING_COUNTRIES);

        wc_load_cart();
        WC()->cart->empty_cart();
        WC()->customer->set_shipping_country(self::ZONE_COUNTRY);
        WC()->customer->set_billing_country(self::ZONE_COUNTRY);

        $this->zone = new WC_Shipping_Zone();
        $this->zone->set_zone_name('Free shipping note test');
        $this->zone->add_location(self::ZONE_COUNTRY, 'country');
        $this->zone->save();
        $this->instance_id = $this->zone->add_shipping_method('free_shipping');
        $this->free_shipping('min_amount');

        $product = new WC_Product_Simple();
        $product->set_name('Free shipping note product');
        $product->set_regular_price(self::PRICE);
        $this->product_id = $product->save();
    }

    protected function tearDown(): void
    {
        WC()->cart->empty_cart();
        $this->zone->delete();
        wp_delete_post($this->product_id, true);

        if (null !== $this->coupon_id) {
            wp_delete_post($this->coupon_id, true);
        }

        update_option(self::SHIP_TO_COUNTRIES_OPTION, $this->ship_to_countries);

        parent::tearDown();
    }

    public function testSaysHowMuchMoreTheCartNeedsBelowTheMinimum(): void
    {
        WC()->cart->add_to_cart($this->product_id);

        self::assertSame(
            'Add ' . $this->price(self::LEFT_AFTER_ONE) . ' more for free shipping',
            (new FreeShippingNote())->text()
        );
    }

    public function testSaysShippingIsFreeAboveTheMinimum(): void
    {
        WC()->cart->add_to_cart($this->product_id, self::OVER_MINIMUM);

        self::assertSame('You get free shipping', (new FreeShippingNote())->text());
    }

    public function testSaysNothingWhileTheRequiredCouponIsMissing(): void
    {
        $this->free_shipping('both');
        WC()->cart->add_to_cart($this->product_id);

        self::assertSame('', (new FreeShippingNote())->text());
    }

    public function testCountsTheMinimumOnceTheRequiredCouponIsApplied(): void
    {
        $this->free_shipping('both');
        WC()->cart->add_to_cart($this->product_id);
        WC()->cart->apply_coupon($this->free_shipping_coupon());

        self::assertStringContainsString($this->price(self::LEFT_AFTER_ONE), (new FreeShippingNote())->text());
    }

    public function testSaysNothingWhereTheZoneHasNoFreeShipping(): void
    {
        WC()->customer->set_shipping_country(self::OTHER_COUNTRY);
        WC()->cart->add_to_cart($this->product_id);

        self::assertSame('', (new FreeShippingNote())->text());
    }

    public function testSaysNothingWhenTheCartShipsAsSeveralPackages(): void
    {
        WC()->cart->add_to_cart($this->product_id, self::OVER_MINIMUM);
        add_filter('woocommerce_cart_shipping_packages', [$this, 'split_into_two_packages']);

        try {
            self::assertSame('', (new FreeShippingNote())->text());
        } finally {
            remove_filter('woocommerce_cart_shipping_packages', [$this, 'split_into_two_packages']);
        }
    }

    public function testDoesNotPromiseFreeShippingTheStoreHasTurnedOff(): void
    {
        WC()->cart->add_to_cart($this->product_id, self::OVER_MINIMUM);
        add_filter('woocommerce_shipping_free_shipping_is_available', '__return_false');

        try {
            self::assertSame('', (new FreeShippingNote())->text());
        } finally {
            remove_filter('woocommerce_shipping_free_shipping_is_available', '__return_false');
        }
    }

    public function testSaysNothingWhenThePackageMayNotShipFree(): void
    {
        WC()->cart->add_to_cart($this->product_id);
        add_filter('woocommerce_cart_shipping_packages', [$this, 'ship_via_flat_rate']);

        try {
            self::assertSame('', (new FreeShippingNote())->text());
        } finally {
            remove_filter('woocommerce_cart_shipping_packages', [$this, 'ship_via_flat_rate']);
        }
    }

    /**
     * The cart's package twice, as a store that ships some products
     * separately splits it.
     *
     * @param list<array<string, mixed>> $packages
     *
     * @return list<array<string, mixed>>
     */
    public function split_into_two_packages(array $packages): array
    {
        return [$packages[0], $packages[0]];
    }

    /**
     * The cart's package, restricted to flat rate shipping.
     *
     * @param list<array<string, mixed>> $packages
     *
     * @return list<array<string, mixed>>
     */
    public function ship_via_flat_rate(array $packages): array
    {
        $packages[0]['ship_via'] = ['flat_rate'];

        return $packages;
    }

    public function testTheBlockShowsTheNoteAndFollowsTheMiniCartsCart(): void
    {
        WC()->cart->add_to_cart($this->product_id);

        $block = do_blocks('<!-- wp:' . self::BLOCK . ' /-->');

        self::assertStringContainsString($this->price(self::LEFT_AFTER_ONE), $block);
        self::assertStringContainsString(
            'data-wp-text="woocommerce::state.cart.extensions.pixonaut-shop.free_shipping_note"',
            $block
        );
        self::assertStringContainsString(
            'data-wp-bind--hidden="woocommerce::!state.cart.extensions.pixonaut-shop.free_shipping_note"',
            $block
        );
        self::assertStringNotContainsString(' hidden', $block);
    }

    public function testTheBlockIsHiddenWithoutANote(): void
    {
        WC()->customer->set_shipping_country(self::OTHER_COUNTRY);
        WC()->cart->add_to_cart($this->product_id);

        self::assertMatchesRegularExpression(
            '/\A<p [^>]*\bhidden=""[^>]*><\/p>\z/',
            do_blocks('<!-- wp:' . self::BLOCK . ' /-->')
        );
    }

    public function testTheStoreApiCartCarriesTheNote(): void
    {
        WC()->cart->add_to_cart($this->product_id, self::OVER_MINIMUM);

        // The response holds its extensions as an object, as the client receives them.
        $data = json_decode((string) wp_json_encode(rest_do_request(new WP_REST_Request('GET', '/wc/store/v1/cart'))->get_data()), true);

        self::assertIsArray($data);
        self::assertSame(
            ['free_shipping_note' => 'You get free shipping'],
            $data['extensions']['pixonaut-shop'] ?? null
        );
    }

    /** Sets what the zone's free-shipping method requires, with the minimum. */
    private function free_shipping(string $requires): void
    {
        update_option(
            "woocommerce_free_shipping_{$this->instance_id}_settings",
            ['title' => 'Free shipping', 'requires' => $requires, 'min_amount' => self::MINIMUM, 'ignore_discounts' => 'no']
        );
        WC_Cache_Helper::get_transient_version('shipping', true);
    }

    /** A coupon that gives free shipping and takes nothing off. */
    private function free_shipping_coupon(): string
    {
        $coupon = new WC_Coupon();
        $coupon->set_code('free-shipping-note-test');
        $coupon->set_free_shipping(true);
        $this->coupon_id = $coupon->save();

        return $coupon->get_code();
    }

    /** An amount as WooCommerce formats a price, as text. */
    private function price(int $amount): string
    {
        return html_entity_decode(wp_strip_all_tags(wc_price($amount)), ENT_QUOTES, 'UTF-8');
    }
}
