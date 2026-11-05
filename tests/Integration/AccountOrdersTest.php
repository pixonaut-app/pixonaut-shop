<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use Closure;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;
use Pixonaut\Shop\Account\ViewOrder;
use Pixonaut\Shop\Tests\Support\ShopFixtures;
use WC_Order;
use WP_Block_Template;
use WP_Post;

/**
 * My account's orders and a single order's view for the signed-in customer,
 * with the line states the fake plugin (`tests/e2e/fake-pixonaut.php`)
 * shows as the plugin does, and without them.
 */
final class AccountOrdersTest extends TestCase
{
    /** The order help part's text while a test has the store fill it in. */
    private const HELP_TEXT = 'Write to us about your order.';

    /** @var list<int> */
    private array $orders = [];

    private ?Closure $help = null;

    public static function setUpBeforeClass(): void
    {
        ShopFixtures::prepare();
    }

    protected function setUp(): void
    {
        wp_set_current_user(ShopFixtures::customer());
    }

    protected function tearDown(): void
    {
        foreach ($this->orders as $order_id) {
            ShopFixtures::delete_order($order_id);
        }

        $this->orders = [];
        if (null !== $this->help) {
            remove_filter('get_block_template', $this->help);
            $this->help = null;
        }
        wp_set_current_user(0);
    }

    public function testShowsTheStateOfAnOrdersLineFurthestBehindInTheOrdersList(): void
    {
        $order = $this->personalised_order(['ordered', 'approved']);

        $status = $this->orders_list()->query($this->status_in_row_of($order));

        self::assertSame('Design approved', $status->item(0)?->textContent);
    }

    public function testShowsWooCommercesStatusForAnOrderWithoutLineStatesInTheOrdersList(): void
    {
        $order = $this->plain_order();

        $status = $this->orders_list()->query($this->status_in_row_of($order));

        self::assertSame('Processing', $status->item(0)?->textContent);
    }

    public function testShowsAnOrdersBreadcrumbsStatusOverviewDetailsAndHelpInThatOrder(): void
    {
        $order = $this->personalised_order(['ordered']);
        $this->fill_in_the_help();

        $page = $this->view_order($order);
        $positions = array_map(
            static fn (string $marker): int|false => strpos($page, $marker),
            [
                'class="woocommerce-breadcrumb"',
                'was placed on',
                'class="woocommerce-order-overview',
                'class="ps-order-panel"',
                'class="woocommerce-order-details"',
                'class="woocommerce-customer-details"',
                self::HELP_TEXT,
            ]
        );

        self::assertNotContains(false, $positions);
        $sorted = $positions;
        sort($sorted);
        self::assertSame($sorted, $positions);
    }

    public function testLeadsFromMyAccountThroughTheOrdersToTheOrder(): void
    {
        $order = $this->personalised_order(['ordered']);

        $crumbs = $this->document($this->view_order($order));
        $links = $crumbs->query('//nav[@class="woocommerce-breadcrumb"]/a');

        self::assertSame(2, $links->length);
        self::assertSame(get_post_field('post_title', wc_get_page_id('myaccount')), $links->item(0)?->textContent);
        self::assertSame('Orders', $links->item(1)?->textContent);
        self::assertStringEndsWith('Order #' . $order->get_order_number(), (string) $crumbs->query('//nav[@class="woocommerce-breadcrumb"]')->item(0)?->textContent);
    }

    public function testShowsTheLinesStateInTheOverviewAndUnderTheLine(): void
    {
        $order = $this->personalised_order(['ordered']);

        $page = $this->document($this->view_order($order));

        self::assertSame('In production', $page->query('//ul[contains(@class, "woocommerce-order-overview")]/li[contains(@class, "woocommerce-order-overview__status")]/strong')->item(0)?->textContent);
        self::assertSame(1, $page->query('//*[contains(@class, "woocommerce-order-details")]//span[@class="pixonaut-shop-line-state"][.="In production"]')->length);
    }

    public function testShowsWooCommercesStatusInTheOverviewOfAnOrderWithoutLineStates(): void
    {
        $page = $this->document($this->view_order($this->plain_order()));

        self::assertSame('Processing', $page->query('//ul[contains(@class, "woocommerce-order-overview")]/li[contains(@class, "woocommerce-order-overview__status")]/strong')->item(0)?->textContent);
        self::assertSame(0, $page->query('//span[@class="pixonaut-shop-line-state"]')->length);
    }

    public function testLeavesTheHelpOutWhileThePartIsEmpty(): void
    {
        self::assertStringNotContainsString('ps-order-help', $this->view_order($this->plain_order()));
    }

    public function testShowsNothingOfItsOwnForSomeoneElsesOrder(): void
    {
        $order = $this->plain_order();
        $someone_else = wp_insert_user(['user_login' => 'someone-else-' . wp_generate_password(6, false), 'user_pass' => wp_generate_password(), 'role' => 'customer']);
        self::assertIsInt($someone_else);
        wp_set_current_user($someone_else);

        try {
            $page = $this->view_order($order);

            self::assertStringNotContainsString('woocommerce-breadcrumb', $page);
            self::assertStringNotContainsString('woocommerce-order-overview', $page);
        } finally {
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user($someone_else);
        }
    }

    /**
     * @param list<string> $states
     */
    private function personalised_order(array $states): WC_Order
    {
        $order_id = ShopFixtures::personalised_order($states);
        $this->orders[] = $order_id;
        $order = wc_get_order($order_id);
        self::assertInstanceOf(WC_Order::class, $order);

        return $order;
    }

    private function plain_order(): WC_Order
    {
        $product = get_page_by_path(ShopFixtures::PLAIN_PRODUCT_SLUG, OBJECT, 'product');
        self::assertInstanceOf(WP_Post::class, $product);

        $order = wc_create_order(['customer_id' => ShopFixtures::customer()]);
        self::assertInstanceOf(WC_Order::class, $order);
        $order->add_product(wc_get_product($product->ID));
        $order->calculate_totals();
        $order->set_status('processing');
        $this->orders[] = $order->save();

        return $order;
    }

    /** Has the store fill in the order help part, as it does in the Site Editor. */
    private function fill_in_the_help(): void
    {
        $this->help = static function (?WP_Block_Template $template, string $id): ?WP_Block_Template {
                if (null === $template || get_stylesheet() . '//' . ViewOrder::HELP_PART !== $id) {
                    return $template;
                }

                $filled = clone $template;
                $filled->content = '<!-- wp:paragraph --><p>' . self::HELP_TEXT . '</p><!-- /wp:paragraph -->';

                return $filled;
        };
        add_filter('get_block_template', $this->help, 10, 2);
    }

    /** XPath to the status in the orders table's row for this order. */
    private function status_in_row_of(WC_Order $order): string
    {
        return '//tr[.//a[normalize-space(.)="#' . $order->get_order_number() . '"]]//mark[contains(@class, "order-status")]';
    }

    private function orders_list(): DOMXPath
    {
        ob_start();
        woocommerce_account_orders(1);

        return $this->document((string) ob_get_clean());
    }

    /** The view-order endpoint's content, as My account prints it. */
    private function view_order(WC_Order $order): string
    {
        ob_start();
        // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- WooCommerce's hook, fired as My account fires it.
        do_action('woocommerce_account_view-order_endpoint', (string) $order->get_id());

        return (string) ob_get_clean();
    }

    private function document(string $html): DOMXPath
    {
        $document = new DOMDocument();
        $document->loadHTML('<?xml encoding="utf-8"?><body>' . $html . '</body>', LIBXML_NOERROR);

        return new DOMXPath($document);
    }
}
