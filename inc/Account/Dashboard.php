<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Account;

use WC_Customer;
use WC_Order;
use WP_Hook;

/**
 * What My account's dashboard shows under WooCommerce's greeting: shortcuts
 * to the latest order, the addresses and the account details, and the
 * latest orders in WooCommerce's own orders table.
 *
 * A customer without orders sees WooCommerce's dashboard alone.
 */
final class Dashboard
{
    /** How many of the customer's latest orders the dashboard lists. */
    public const RECENT_ORDERS = 3;

    /** WooCommerce's filter on the orders query of My account's orders, so the dashboard lists what that page lists. */
    private const ORDERS_QUERY_FILTER = 'woocommerce_my_account_my_orders_query';

    /**
     * The actions WooCommerce's orders table template fires around the table:
     * before it, above its pagination (even with a single page) and after it.
     */
    private const ORDERS_PAGE_HOOKS = [
        'woocommerce_before_account_orders',
        'woocommerce_before_account_orders_pagination',
        'woocommerce_after_account_orders',
    ];

    /** The recent orders heading's id, which labels its section. */
    private const RECENT_ORDERS_HEADING_ID = 'ps-recent-orders-title';

    public function register(): void
    {
        add_action('woocommerce_account_dashboard', [$this, 'render']);
    }

    public function render(): void
    {
        $customer_id = get_current_user_id();

        if (0 === $customer_id) {
            return;
        }

        $orders = $this->recent_orders($customer_id);

        if ([] === $orders) {
            return;
        }

        $this->render_shortcuts($orders[0], new WC_Customer($customer_id));
        $this->render_recent_orders($orders);
    }

    /**
     * @return list<WC_Order>
     */
    private function recent_orders(int $customer_id): array
    {
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- WooCommerce's filter, applied as its orders page applies it.
        $query = apply_filters(self::ORDERS_QUERY_FILTER, ['customer' => $customer_id]);
        $orders = wc_get_orders(array_merge(
            \is_array($query) ? $query : [],
            ['customer' => $customer_id, 'limit' => self::RECENT_ORDERS, 'paginate' => false]
        ));

        if (!\is_array($orders)) {
            return [];
        }

        // The filter may ask for ids, which WooCommerce's orders template accepts too.
        $orders = array_map(static fn (mixed $order): mixed => \is_int($order) ? wc_get_order($order) : $order, $orders);

        return array_values(array_filter($orders, static fn (mixed $order): bool => $order instanceof WC_Order));
    }

    private function render_shortcuts(WC_Order $latest, WC_Customer $customer): void
    {
        echo '<div class="ps-account-shortcuts">';
        $this->render_shortcut(
            $latest->get_view_order_url(),
            __('Latest order', 'pixonaut-shop'),
            [
                /* translators: %s: the order number. */
                \sprintf(__('Order #%s', 'pixonaut-shop'), $latest->get_order_number()),
                wc_get_order_status_name($latest->get_status()),
            ]
        );
        $this->render_shortcut(
            wc_get_account_endpoint_url('edit-address'),
            __('Addresses', 'pixonaut-shop'),
            [$customer->get_billing_address_1()]
        );
        $this->render_shortcut(
            wc_get_account_endpoint_url('edit-account'),
            __('Account details', 'pixonaut-shop'),
            [$customer->get_email()]
        );
        echo '</div>';
    }

    /**
     * @param list<string> $details Each printed apart, in order.
     */
    private function render_shortcut(string $url, string $title, array $details): void
    {
        printf(
            '<a class="ps-account-shortcut" href="%s"><strong class="ps-account-shortcut__title">%s</strong><span class="ps-account-shortcut__detail">',
            esc_url($url),
            esc_html($title)
        );

        foreach (array_filter($details, static fn (string $detail): bool => '' !== $detail) as $detail) {
            printf('<span>%s</span>', esc_html($detail));
        }

        echo '</span></a>';
    }

    /**
     * @param list<WC_Order> $orders
     */
    private function render_recent_orders(array $orders): void
    {
        printf(
            '<section class="ps-recent-orders" aria-labelledby="%1$s"><div class="ps-recent-orders__head"><h2 id="%1$s">%2$s</h2><a href="%3$s">%4$s</a></div>',
            esc_attr(self::RECENT_ORDERS_HEADING_ID),
            esc_html__('Recent orders', 'pixonaut-shop'),
            esc_url(wc_get_account_endpoint_url('orders')),
            esc_html__('View all', 'pixonaut-shop')
        );

        $this->without_orders_page_extras(function () use ($orders): void {
            // WooCommerce's own table, as My account's orders prints it, on one page.
            $button_class = wc_wp_theme_get_element_class_name('button');
            wc_get_template(
                'myaccount/orders.php',
                [
                    'current_page'    => 1,
                    'customer_orders' => (object) ['orders' => $orders, 'total' => \count($orders), 'max_num_pages' => 1],
                    'has_orders'      => true,
                    'wp_button_class' => '' !== $button_class ? ' ' . $button_class : '',
                ]
            );
        });

        echo '</section>';
    }

    /**
     * Runs `$render` with nothing on the hooks around the orders page's
     * table: what WooCommerce and other plugins print there, such as the
     * prompt to link past orders, is about that page, not the dashboard's
     * few recent orders.
     *
     * @param callable(): void $render
     */
    private function without_orders_page_extras(callable $render): void
    {
        global $wp_filter;

        $held = [];

        foreach (self::ORDERS_PAGE_HOOKS as $hook) {
            $registered = \is_array($wp_filter) ? ($wp_filter[$hook] ?? null) : null;

            if ($registered instanceof WP_Hook) {
                $held[$hook] = $registered->callbacks;
                remove_all_actions($hook);
            }
        }

        try {
            $render();
        } finally {
            foreach ($held as $hook => $priorities) {
                foreach ($priorities as $priority => $callbacks) {
                    foreach ($callbacks as $callback) {
                        add_action($hook, $callback['function'], $priority, $callback['accepted_args']);
                    }
                }
            }
        }
    }
}
