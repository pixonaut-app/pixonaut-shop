<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Pixonaut\Shop\Pixonaut\Integration;
use Pixonaut\Shop\Tests\Support\ShopFixtures;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;
use WP_Post;

/**
 * The theme's callbacks on Pixonaut for WooCommerce's documented hooks, on the tests site, where the fake plugin
 * (`tests/e2e/fake-pixonaut.php`) fires them as the plugin does.
 */
final class PixonautHooksTest extends TestCase
{
    private ?WC_Order $order = null;

    public static function setUpBeforeClass(): void
    {
        ShopFixtures::prepare();
    }

    protected function tearDown(): void
    {
        $this->order?->delete(true);
        $this->order = null;
    }

    public function testTheFakePrintsAPersonalisedCartLinesRowsThroughTheCartItemDisplayHook(): void
    {
        $fired = did_filter(Integration::CART_ITEM_DISPLAY);

        $rows = apply_filters('woocommerce_get_item_data', [], self::personalised_cart_item());

        self::assertTrue(self::theme_hooks(Integration::CART_ITEM_DISPLAY, 'cart_item_display'));
        self::assertSame($fired + 1, did_filter(Integration::CART_ITEM_DISPLAY));
        self::assertIsArray($rows);
        self::assertStringContainsString('class="pixonaut-proof-link"', $rows[0]['display']);
    }

    /**
     * The side's row comes first, then its field rows, and the edit link is
     * on the last of them, as the plugin prints them.
     */
    public function testTheFakePrintsADesignsFieldsAfterItsSideWithTheEditLinkLast(): void
    {
        $rows = apply_filters('woocommerce_get_item_data', [], self::personalised_cart_item());

        self::assertIsArray($rows);
        self::assertSame(
            [['Name', 'Alex'], ['Greeting', 'Happy birthday'], ['Colour', '#1e6b52'], ['Photo', 'Image']],
            array_map(static fn (array $row): array => [$row['key'], $row['value']], \array_slice($rows, 1))
        );

        $last = array_pop($rows);
        self::assertStringContainsString('class="pixonaut-edit-design"', $last['display']);
        self::assertStringNotContainsString('pixonaut-edit-design', $last['value']);

        foreach ($rows as $row) {
            self::assertStringNotContainsString('pixonaut-edit-design', $row['display']);
        }
    }

    /**
     * Each stored field is a row keyed by its side and place, before the
     * line's state.
     */
    public function testTheFakePrintsAnOrderLinesFieldsBeforeItsState(): void
    {
        $rows = $this->order_line(self::personalised_cart_item())->get_formatted_meta_data();

        self::assertSame(
            ['pixonaut_field_front_0', 'pixonaut_field_front_1', 'pixonaut_field_front_2', 'pixonaut_field_front_3', Integration::LINE_STATE_ROW],
            array_keys($rows)
        );
        self::assertSame('Colour', $rows['pixonaut_field_front_2']->key);
        self::assertSame('Image', $rows['pixonaut_field_front_3']->display_value);
    }

    public function testTheFakePrintsAnOrderLinesStateThroughTheFulfilmentLineDisplayHook(): void
    {
        $fired = did_filter(Integration::FULFILMENT_LINE_DISPLAY);

        $rows = $this->order_line(self::personalised_cart_item())->get_formatted_meta_data();

        self::assertTrue(self::theme_hooks(Integration::FULFILMENT_LINE_DISPLAY, 'fulfilment_line_display'));
        self::assertSame($fired + 1, did_filter(Integration::FULFILMENT_LINE_DISPLAY));
        self::assertArrayHasKey(Integration::LINE_STATE_ROW, $rows);
    }

    /**
     * The plugin prints only for a line it personalised, not for every line
     * of a personalisable product.
     */
    public function testALineThePluginDidNotPersonaliseKeepsWooCommercesRows(): void
    {
        $cart_item = ['data' => self::personalised_product()];

        self::assertSame([], apply_filters('woocommerce_get_item_data', [], $cart_item));
        self::assertSame([], $this->order_line($cart_item)->get_formatted_meta_data());
    }

    /**
     * A cart item for the product the fake personalises, with the cart line
     * the fake adds when it is added to the cart.
     *
     * @return array<string, mixed>
     */
    private static function personalised_cart_item(): array
    {
        $product = self::personalised_product();
        $cart_item = apply_filters('woocommerce_add_cart_item_data', [], $product->get_id(), 0, 1);

        self::assertIsArray($cart_item);

        return $cart_item + ['data' => $product];
    }

    private static function personalised_product(): WC_Product
    {
        $post = get_page_by_path(ShopFixtures::PRODUCT_SLUG, OBJECT, 'product');
        $product = $post instanceof WP_Post ? wc_get_product($post->ID) : null;

        self::assertInstanceOf(WC_Product::class, $product);

        return $product;
    }

    /**
     * An order line checked out from a cart item, as WooCommerce's checkout
     * makes it.
     *
     * @param array<string, mixed> $cart_item
     */
    private function order_line(array $cart_item): WC_Order_Item_Product
    {
        $this->order = new WC_Order();
        $item = new WC_Order_Item_Product();
        $item->set_product($cart_item['data']);
        do_action('woocommerce_checkout_create_order_line_item', $item, 'cart-item', $cart_item, $this->order);
        $this->order->add_item($item);
        $this->order->save();

        return $item;
    }

    /**
     * Whether the theme's `Integration` is hooked on a filter. It hooks an
     * array callback, which WordPress keys by the instance, so the
     * registered callbacks are walked (as in ThemeSetupTest).
     */
    private static function theme_hooks(string $hook, string $method): bool
    {
        global $wp_filter;

        foreach ($wp_filter[$hook]->callbacks ?? [] as $callbacks) {
            foreach ($callbacks as $callback) {
                $function = $callback['function'];

                if (\is_array($function) && $function[0] instanceof Integration && $method === $function[1]) {
                    return true;
                }
            }
        }

        return false;
    }
}
