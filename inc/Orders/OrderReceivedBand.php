<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Orders;

use Closure;
use WC_Order;

/**
 * The order received page's band: its check badge and the pill above the
 * heading, "Order 1042 received".
 *
 * The order-confirmation template saves the pill as an empty paragraph with
 * the Pill style and `PILL_MARKER`, since a template cannot know the order.
 * Once the group around it has rendered, this fills the pill in, but only
 * where WooCommerce's summary in the same group printed its list of the
 * order's facts, which WooCommerce does only for whoever may see the order;
 * everywhere else the pill is left out, so it never reveals an order the
 * page does not. For an order that did not go through (failed, cancelled or
 * refunded), the badge's check and the pill are both left out, and
 * WooCommerce's status says what happened.
 */
final class OrderReceivedBand
{
    /** The class that marks the template's paragraph as the pill. */
    public const PILL_MARKER = 'pixonaut-shop-order-number';

    /** The class that marks the template's group as the check badge. */
    public const BADGE_MARKER = 'pixonaut-shop-check-badge';

    /** The class of the list WooCommerce's summary block prints only for whoever may see the order. */
    private const SUMMARY_LIST = 'wc-block-order-confirmation-summary-list';

    /** WooCommerce's statuses for an order that did not go through. */
    private const UNSUCCESSFUL_STATUSES = ['failed', 'cancelled', 'refunded'];

    /** The query var WooCommerce's order received endpoint carries the order's ID in. */
    private const ORDER_RECEIVED_QUERY_VAR = 'order-received';

    /** @var Closure(): (array{number: string, status: string}|null) */
    private Closure $order;

    /**
     * @param (Closure(): (array{number: string, status: string}|null))|null $order The number and status of the order this page is for, or `null`; defaults to asking WooCommerce.
     */
    public function __construct(?Closure $order = null)
    {
        $this->order = $order ?? static fn (): ?array => self::received_order();
    }

    public function register(): void
    {
        add_filter('render_block_core/group', [$this, 'render']);
    }

    public function render(string $content): string
    {
        $empty_pill = self::element('p', self::PILL_MARKER);

        if (1 !== preg_match($empty_pill, $content)) {
            return $content;
        }

        $order = ($this->order)();
        $went_through = null !== $order && ! \in_array($order['status'], self::UNSUCCESSFUL_STATUSES, true);

        if (null !== $order && ! $went_through) {
            $content = (string) preg_replace(self::element('div', self::BADGE_MARKER), '', $content);
        }

        if (! $went_through || ! self::shows_summary($content)) {
            return (string) preg_replace($empty_pill, '', $content);
        }

        /* translators: %s: the order's number. */
        $text = \sprintf(esc_html__('Order %s received', 'pixonaut-shop'), esc_html($order['number']));

        return (string) preg_replace_callback(
            $empty_pill,
            static fn (array $parts): string => $parts['open'] . $text . $parts['close'],
            $content,
            1
        );
    }

    /**
     * An empty element the template saved with a marker class, as its
     * opening tag and its closing tag. A marker matches as a whole class
     * name only.
     */
    private static function element(string $tag, string $marker): string
    {
        return '/(?<open><' . $tag . '\b[^>]*\bclass="[^"]*(?<![\w-])' . preg_quote($marker, '/') . '(?![\w-])[^"]*"[^>]*>)\s*(?<close><\/' . $tag . '>)/';
    }

    private static function shows_summary(string $content): bool
    {
        return 1 === preg_match('/\bclass="[^"]*(?<![\w-])' . self::SUMMARY_LIST . '(?![\w-])/', $content);
    }

    /**
     * The order on WooCommerce's order received page, by its number and
     * status, or `null` when the page is for none.
     *
     * @return array{number: string, status: string}|null
     */
    private static function received_order(): ?array
    {
        $query_var = get_query_var(self::ORDER_RECEIVED_QUERY_VAR);
        $order_id = is_numeric($query_var) ? absint($query_var) : 0;

        if (0 === $order_id || ! \function_exists('wc_get_order')) {
            return null;
        }

        $order = wc_get_order($order_id);

        return $order instanceof WC_Order
            ? ['number' => $order->get_order_number(), 'status' => $order->get_status()]
            : null;
    }
}
