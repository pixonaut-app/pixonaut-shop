<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Account;

use Pixonaut\Shop\Pixonaut\Integration;
use stdClass;
use WC_Order;
use WC_Order_Item_Product;

/**
 * An order's status in My account, marked so it reads as a pill, as
 * WooCommerce marks it on a single order's page: tinted while the order is
 * open, quiet once it is done.
 *
 * Personalised lines move through production states WooCommerce's statuses
 * do not express. Where Pixonaut for WooCommerce shows a line's state among
 * its displayed meta, the pill shows the state of the line furthest behind,
 * in the plugin's words, instead of WooCommerce's status. The theme reads
 * the row as WooCommerce hands it out, never the plugin's code.
 */
final class OrderStatus
{
    /** The statuses after which nothing more happens to an order. */
    private const DONE_STATUSES = ['completed', 'cancelled', 'refunded'];

    /**
     * Pixonaut for WooCommerce's line states that are still on their way,
     * furthest behind first: a line the store has to look at, or one that
     * could not be produced, holds the order up whatever the others do; then
     * the forward path, from a saved design to one in production.
     */
    private const UNFINISHED_LINE_STATES = [
        'needs_attention',
        'failed',
        'committed',
        'awaiting_customer',
        'approved',
        'printed',
        'ordered',
    ];

    /**
     * The line states nothing more happens after, which hold nothing up: a
     * shipped line, then a cancelled one. A state the plugin adds later
     * ranks between these and the unfinished ones, so it is never taken for
     * done.
     */
    private const FINISHED_LINE_STATES = ['shipped', 'cancelled'];

    public function register(): void
    {
        add_action('woocommerce_my_account_my_orders_column_order-status', [$this, 'render']);
    }

    public function render(WC_Order $order): void
    {
        echo $this->pill($order); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pill() escapes it.
    }

    /** The order's status, or its production state, as a pill. */
    public function pill(WC_Order $order): string
    {
        $status = $order->get_status();
        $line = $this->line_furthest_behind($order);

        $done = null === $line
            ? \in_array($status, self::DONE_STATUSES, true)
            : \in_array($line->value, self::FINISHED_LINE_STATES, true);

        return \sprintf(
            '<mark class="%s">%s</mark>',
            esc_attr('order-status status-' . $status . ($done ? ' is-done' : '')),
            $this->text($order, $line)
        );
    }

    /** The pill's text alone, as escaped HTML. */
    public function label(WC_Order $order): string
    {
        return $this->text($order, $this->line_furthest_behind($order));
    }

    /**
     * WooCommerce's name for the order's status, or the plugin's label for
     * the line's state, which is escaped HTML the theme may have wrapped in
     * its own span.
     */
    private function text(WC_Order $order, ?stdClass $line): string
    {
        if (null === $line) {
            return esc_html(wc_get_order_status_name($order->get_status()));
        }

        $display = $line->display_value ?? '';

        return wp_kses(\is_string($display) ? $display : '', []);
    }

    /**
     * The state row of the order's line furthest behind, or `null` when no
     * line shows one.
     */
    private function line_furthest_behind(WC_Order $order): ?stdClass
    {
        $furthest_behind = null;
        $rank = PHP_INT_MAX;

        foreach ($order->get_items() as $item) {
            $row = $item instanceof WC_Order_Item_Product ? $item->get_formatted_meta_data()[Integration::LINE_STATE_ROW] ?? null : null;

            if (! $row instanceof stdClass || ! \is_string($row->value ?? null)) {
                continue;
            }

            $row_rank = self::rank($row->value);

            if ($row_rank < $rank) {
                $furthest_behind = $row;
                $rank = $row_rank;
            }
        }

        return $furthest_behind;
    }

    /** Where a line state stands, lowest for the line furthest behind. */
    private static function rank(string $state): int
    {
        $unfinished = array_search($state, self::UNFINISHED_LINE_STATES, true);

        if (false !== $unfinished) {
            return $unfinished;
        }

        $finished = array_search($state, self::FINISHED_LINE_STATES, true);
        $unknown = \count(self::UNFINISHED_LINE_STATES);

        return false === $finished ? $unknown : $unknown + 1 + $finished;
    }
}
