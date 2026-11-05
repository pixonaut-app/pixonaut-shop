<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Pixonaut\Shop\Orders\OrderReceivedBand;
use Pixonaut\Shop\Tests\Support\ShopFixtures;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;
use WP_Post;

/**
 * The order received page as WooCommerce renders it from the theme's
 * order-confirmation template, for an order with a personalised line, whose
 * rows the fake plugin (`tests/e2e/fake-pixonaut.php`) prints as the plugin
 * does.
 */
final class OrderReceivedTest extends TestCase
{
    /** WooCommerce's query var for the order received endpoint. */
    private const ORDER_RECEIVED = 'order-received';

    private ?WC_Order $order = null;

    public static function setUpBeforeClass(): void
    {
        ShopFixtures::prepare();
    }

    protected function tearDown(): void
    {
        set_query_var(self::ORDER_RECEIVED, '');
        unset($_GET['key']);
        $this->order?->delete(true);
        $this->order = null;
    }

    public function testShowsTheOrderInTheBandWithItsNumberInThePill(): void
    {
        $order = $this->receive_order();

        $page = $this->render_page();

        self::assertStringContainsString('pixonaut-shop-order-received', $page);
        self::assertStringContainsString('>Order ' . $order->get_order_number() . ' received</p>', $page);
        self::assertStringContainsString('wc-block-order-confirmation-summary-list', $page);
        self::assertStringContainsString('wc-block-order-confirmation-totals__table', $page);
    }

    public function testShowsAPersonalisedLinesFieldsBeforeItsStateAsAPill(): void
    {
        $this->receive_order();

        $page = $this->render_page();
        $field = strpos($page, 'Alex');
        $state = strpos($page, '<span class="pixonaut-shop-line-state">Design approved</span>');

        self::assertNotFalse($field, 'The design\'s field row is not shown.');
        self::assertNotFalse($state, 'The line\'s state is not marked as a pill.');
        self::assertLessThan($state, $field);
    }

    /**
     * A failed payment is not a received order: WooCommerce's status says so
     * and offers to try again, without the band's check or pill.
     */
    public function testShowsNeitherCheckNorPillForAFailedOrder(): void
    {
        $this->receive_order('failed');

        $page = $this->render_page();

        self::assertStringNotContainsString(OrderReceivedBand::BADGE_MARKER, $page);
        self::assertStringNotContainsString(OrderReceivedBand::PILL_MARKER, $page);
        self::assertStringContainsString('Order failed', $page);
        self::assertStringContainsString('wc-block-order-confirmation-status__actions', $page);
    }

    public function testPrintsNoPillWithoutAnOrder(): void
    {
        self::assertStringNotContainsString(OrderReceivedBand::PILL_MARKER, $this->render_page());
    }

    public function testPrintsNoPillForAnOrderWithoutItsKey(): void
    {
        $order = $this->receive_order();
        unset($_GET['key']);

        $page = $this->render_page();

        self::assertStringNotContainsString(OrderReceivedBand::PILL_MARKER, $page);
        self::assertStringNotContainsString('Order ' . $order->get_order_number() . ' received', $page);
    }

    /**
     * A guest's order with a personalised line, just placed, on the order
     * received page with its key, as WooCommerce's checkout redirects to it.
     */
    private function receive_order(string $status = 'processing'): WC_Order
    {
        $product = self::personalised_product();
        $cart_item = apply_filters('woocommerce_add_cart_item_data', [], $product->get_id(), 0, 1);
        self::assertIsArray($cart_item);
        $cart_item += ['data' => $product];

        $this->order = new WC_Order();
        $item = new WC_Order_Item_Product();
        $item->set_product($product);
        $item->set_quantity(1);
        do_action('woocommerce_checkout_create_order_line_item', $item, 'cart-item', $cart_item, $this->order);
        $this->order->add_item($item);
        $this->order->set_billing_email('customer@example.test');
        $this->order->calculate_totals();
        $this->order->set_status($status);
        $this->order->save();

        set_query_var(self::ORDER_RECEIVED, $this->order->get_id());
        $_GET['key'] = $this->order->get_order_key();

        return $this->order;
    }

    private function render_page(): string
    {
        $template = get_block_template(get_stylesheet() . '//order-confirmation');
        self::assertNotNull($template);

        return do_blocks($template->content);
    }

    private static function personalised_product(): WC_Product
    {
        $post = get_page_by_path(ShopFixtures::PRODUCT_SLUG, OBJECT, 'product');
        $product = $post instanceof WP_Post ? wc_get_product($post->ID) : null;

        self::assertInstanceOf(WC_Product::class, $product);

        return $product;
    }
}
