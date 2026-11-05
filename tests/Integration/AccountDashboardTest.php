<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;
use Pixonaut\Shop\Tests\Support\ShopFixtures;

/**
 * My account's dashboard for a signed-in customer with an order: the
 * theme's shortcuts and recent orders under WooCommerce's greeting.
 */
final class AccountDashboardTest extends TestCase
{
    /** What WooCommerce's orders table template fires around the table. */
    private const ORDERS_PAGE_HOOKS = [
        'woocommerce_before_account_orders',
        'woocommerce_before_account_orders_pagination',
        'woocommerce_after_account_orders',
    ];

    public static function setUpBeforeClass(): void
    {
        ShopFixtures::prepare();
    }

    protected function tearDown(): void
    {
        wp_set_current_user(0);
    }

    public function testShowsTheShortcutsAndTheRecentOrdersToACustomerWithOrders(): void
    {
        $customer_id = ShopFixtures::customer();
        wp_set_current_user($customer_id);
        $latest = wc_get_orders(['customer' => $customer_id, 'limit' => 1])[0];

        $page = $this->dashboard();
        $shortcuts = $page->query('//*[@class="ps-account-shortcuts"]/a[@class="ps-account-shortcut"]');

        self::assertSame(3, $shortcuts->length);
        self::assertStringContainsString('Latest order', (string) $shortcuts->item(0)?->textContent);
        self::assertStringContainsString('Order #' . $latest->get_order_number(), (string) $shortcuts->item(0)?->textContent);
        self::assertStringContainsString(ShopFixtures::CUSTOMER_STREET, (string) $shortcuts->item(1)?->textContent);
        self::assertStringContainsString(ShopFixtures::CUSTOMER_EMAIL, (string) $shortcuts->item(2)?->textContent);

        self::assertSame(1, $page->query('//section[@class="ps-recent-orders"]//h2[.="Recent orders"]')->length);
        self::assertSame(1, $page->query('//section[@class="ps-recent-orders"]//a[.="View all"]')->length);
        self::assertGreaterThan(0, $page->query('//section[@class="ps-recent-orders"]//table[contains(@class, "woocommerce-orders-table")]//tr[contains(@class, "woocommerce-orders-table__row")]')->length);
        self::assertSame(1, $page->query('//mark[contains(@class, "order-status")][contains(@class, "status-processing")]')->length);
    }

    /**
     * A plugin may ask the orders query shared with the orders page for ids,
     * as WooCommerce's orders template accepts.
     */
    public function testShowsTheRecentOrdersWhenTheOrdersQueryAsksForIds(): void
    {
        wp_set_current_user(ShopFixtures::customer());
        $ids = static fn (array $query): array => ['return' => 'ids'] + $query;
        add_filter('woocommerce_my_account_my_orders_query', $ids);

        try {
            $page = $this->dashboard();

            self::assertSame(3, $page->query('//*[@class="ps-account-shortcuts"]/a[@class="ps-account-shortcut"]')->length);
            self::assertGreaterThan(0, $page->query('//section[@class="ps-recent-orders"]//tr[contains(@class, "woocommerce-orders-table__row")]')->length);
        } finally {
            remove_filter('woocommerce_my_account_my_orders_query', $ids);
        }
    }

    /**
     * What WooCommerce and plugins print around the orders page's table, such
     * as the prompt to link past orders or what goes above its pagination,
     * stays on that page.
     */
    public function testLeavesOutWhatTheOrdersPagePrintsAroundItsTable(): void
    {
        wp_set_current_user(ShopFixtures::customer());
        $marker = static function (): void {
            echo '<p class="orders-page-extra"></p>';
        };

        foreach (self::ORDERS_PAGE_HOOKS as $hook) {
            add_action($hook, $marker);
        }

        try {
            $page = $this->dashboard();

            self::assertSame(0, $page->query('//*[@class="orders-page-extra"]')->length);

            foreach (self::ORDERS_PAGE_HOOKS as $hook) {
                self::assertNotFalse(has_action($hook, $marker), $hook);
            }
        } finally {
            foreach (self::ORDERS_PAGE_HOOKS as $hook) {
                remove_action($hook, $marker);
            }
        }
    }

    public function testShowsNothingOfItsOwnToACustomerWithoutOrders(): void
    {
        $customer_id = wp_insert_user(['user_login' => 'no-orders-' . wp_generate_password(6, false), 'user_pass' => wp_generate_password(), 'role' => 'customer']);
        self::assertIsInt($customer_id);
        wp_set_current_user($customer_id);

        try {
            $page = $this->dashboard();

            self::assertSame(0, $page->query('//*[@class="ps-account-shortcuts"] | //*[@class="ps-recent-orders"]')->length);
        } finally {
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user($customer_id);
        }
    }

    private function dashboard(): DOMXPath
    {
        ob_start();
        do_action('woocommerce_account_dashboard');
        $html = (string) ob_get_clean();

        $document = new DOMDocument();
        $document->loadHTML('<?xml encoding="utf-8"?><body>' . $html . '</body>', LIBXML_NOERROR);

        return new DOMXPath($document);
    }
}
