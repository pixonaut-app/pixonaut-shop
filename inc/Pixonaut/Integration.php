<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Pixonaut;

use stdClass;

/**
 * The theme's callbacks on Pixonaut for WooCommerce's documented hooks.
 *
 * The plugin fires these filters itself; without it nothing fires them, and
 * nothing here runs. The theme only receives what the plugin hands over: it
 * never calls the plugin's functions or classes, and never rewords the
 * plugin's labels, which the plugin translates.
 */
final class Integration
{
    /** Filters a personalised cart item's rows, as `woocommerce_get_item_data` takes them. */
    public const CART_ITEM_DISPLAY = 'pixonaut_wc_cart_item_display';

    /** Filters a personalised order line's displayed meta, as `woocommerce_order_item_get_formatted_meta_data` returns it. */
    public const FULFILMENT_LINE_DISPLAY = 'pixonaut_wc_fulfilment_line_display';

    /** The key of an order line's state row among its displayed meta. */
    public const LINE_STATE_ROW = 'pixonaut_line_state';

    /** The key of a shipped order line's tracking row among its displayed meta. */
    public const TRACKING_ROW = 'pixonaut_tracking';

    /**
     * The class the theme wraps each of these rows' value in, since
     * WooCommerce prints an order line's meta rows without one: the state
     * shows as a pill, the tracking as a link.
     */
    private const ROW_CLASSES = [
        self::LINE_STATE_ROW => 'pixonaut-shop-line-state',
        self::TRACKING_ROW   => 'pixonaut-shop-line-tracking',
    ];

    public function register(): void
    {
        add_filter(self::CART_ITEM_DISPLAY, [$this, 'cart_item_display']);
        add_filter(self::FULFILMENT_LINE_DISPLAY, [$this, 'fulfilment_line_display']);
    }

    /**
     * A personalised cart item's rows: one per side, with its proof link and
     * edit link. Left as the plugin prints them until the cart reshapes them.
     *
     * @param array<int, array{key: string, value: string, display: string}> $rows `display` is escaped HTML.
     * @return array<int, array{key: string, value: string, display: string}>
     */
    public function cart_item_display(array $rows): array
    {
        return $rows;
    }

    /**
     * A personalised order line's displayed meta: a design's field rows, its
     * state row and, once shipped, its tracking row. The state and tracking
     * values are wrapped in a span with the theme's class, so the order
     * pages can style them; their wording stays the plugin's, and every
     * other row is left as it is.
     *
     * @param array<int|string, stdClass> $rows Each with `key`, `value`, `display_key` and `display_value` (escaped HTML).
     * @return array<int|string, stdClass>
     */
    public function fulfilment_line_display(array $rows): array
    {
        foreach (self::ROW_CLASSES as $key => $class) {
            $display = isset($rows[$key]) ? $rows[$key]->display_value ?? null : null;

            if (\is_string($display)) {
                $row = clone $rows[$key];
                $row->display_value = '<span class="' . $class . '">' . $display . '</span>';
                $rows[$key] = $row;
            }
        }

        return $rows;
    }
}
