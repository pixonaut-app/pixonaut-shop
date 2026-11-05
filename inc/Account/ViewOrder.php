<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Account;

use WC_Order;

/**
 * What My account's view of a single order adds around WooCommerce's own:
 * breadcrumbs back through the orders, the order's facts as an overview
 * under WooCommerce's status sentence, a panel around WooCommerce's order
 * and customer details, and after them the store's `order-help` part,
 * where it says how to ask about an order.
 *
 * WooCommerce checks the customer may see the order before it prints it;
 * the breadcrumbs, printed before that, check the same.
 */
final class ViewOrder
{
    /** The template part the store fills in with its own way to ask about an order. */
    public const HELP_PART = 'order-help';

    /** The orders endpoint's key among My account's menu items. */
    private const ORDERS_ENDPOINT = 'orders';

    /** Before WooCommerce prints the order's page, and its details, at the default 10. */
    private const BEFORE_WOOCOMMERCE = 5;

    /** Around WooCommerce's order and customer details, which it prints at 10. */
    private const OPEN_DETAILS = 9;

    private const CLOSE_DETAILS = 11;

    /** After WooCommerce printed the order's details. */
    private const AFTER_WOOCOMMERCE = 20;

    /** WooCommerce's own breadcrumb delimiter, so these read as the shop's do. */
    private const BREADCRUMB_DELIMITER = '&nbsp;&#47;&nbsp;';

    public function __construct(private readonly OrderStatus $status = new OrderStatus())
    {
    }

    public function register(): void
    {
        add_action('woocommerce_account_view-order_endpoint', [$this, 'render_breadcrumbs'], self::BEFORE_WOOCOMMERCE);
        add_action('woocommerce_view_order', [$this, 'render_overview'], self::BEFORE_WOOCOMMERCE);
        add_action('woocommerce_view_order', [$this, 'open_details'], self::OPEN_DETAILS);
        add_action('woocommerce_view_order', [$this, 'close_details'], self::CLOSE_DETAILS);
        add_action('woocommerce_view_order', [$this, 'render_help'], self::AFTER_WOOCOMMERCE);
    }

    /**
     * My account, its orders, and this order, each a link but the last.
     */
    public function render_breadcrumbs(int|string $order_id): void
    {
        $order = $this->viewable_order($order_id);

        if (null === $order) {
            return;
        }

        $account_page = wc_get_page_id('myaccount');
        $crumbs = [[(string) get_post_field('post_title', $account_page), wc_get_page_permalink('myaccount')]];
        $menu = wc_get_account_menu_items();

        $orders = $menu[self::ORDERS_ENDPOINT] ?? null;

        if (\is_string($orders)) {
            $crumbs[] = [$orders, wc_get_account_endpoint_url(self::ORDERS_ENDPOINT)];
        }

        $links = array_map(
            static fn (array $crumb): string => \sprintf('<a href="%s">%s</a>', esc_url($crumb[1]), esc_html($crumb[0])),
            $crumbs
        );
        /* translators: %s: the order number. */
        $links[] = '<span aria-current="page">' . esc_html(\sprintf(__('Order #%s', 'pixonaut-shop'), $order->get_order_number())) . '</span>';

        printf(
            '<nav class="woocommerce-breadcrumb" aria-label="%s">%s</nav>',
            esc_attr__('Breadcrumb', 'pixonaut-shop'),
            implode(self::BREADCRUMB_DELIMITER, $links) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each part is escaped above.
        );
    }

    /**
     * The order's number, date, total and status, label over value.
     */
    public function render_overview(int|string $order_id): void
    {
        $order = wc_get_order(absint($order_id));

        if (! $order instanceof WC_Order) {
            return;
        }

        $date = $order->get_date_created();
        $facts = [
            'order'  => [__('Order number', 'pixonaut-shop'), esc_html($order->get_order_number())],
            'date'   => [__('Date', 'pixonaut-shop'), null === $date ? '' : esc_html(wc_format_datetime($date))],
            'total'  => [__('Total', 'pixonaut-shop'), wp_kses_post($order->get_formatted_order_total())],
            'status' => [__('Status', 'pixonaut-shop'), $this->status->label($order)],
        ];

        echo '<ul class="woocommerce-order-overview ps-order-overview">';

        foreach ($facts as $fact => [$label, $value]) {
            printf(
                '<li class="woocommerce-order-overview__%s"><span class="ps-order-overview__label">%s</span><strong>%s</strong></li>',
                esc_attr($fact),
                esc_html($label),
                $value // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each value is escaped above.
            );
        }

        echo '</ul>';
    }

    public function open_details(): void
    {
        echo '<div class="ps-order-panel">';
    }

    public function close_details(): void
    {
        echo '</div>';
    }

    /**
     * The store's order help part, once the store has written it; an empty
     * part prints nothing.
     */
    public function render_help(): void
    {
        ob_start();
        block_template_part(self::HELP_PART);
        $help = trim((string) ob_get_clean());

        if ('' !== $help) {
            echo '<div class="ps-order-help">' . $help . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the store's own blocks, rendered.
        }
    }

    /**
     * The order, if the signed-in customer may see it, as WooCommerce
     * decides before it prints the order.
     */
    private function viewable_order(int|string $order_id): ?WC_Order
    {
        $order = wc_get_order(absint($order_id));

        return $order instanceof WC_Order && current_user_can('view_order', $order->get_id()) ? $order : null;
    }
}
