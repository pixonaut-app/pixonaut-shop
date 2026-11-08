<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Product;

use Closure;
use DateTimeImmutable;
use WC_Product;

/**
 * The lowest price a product had in the 30 days before its current
 * reduction, shown beside every reduced price a shopper sees, as Swedish law
 * (prisinformationslagen 7 a §) requires of an announced price reduction.
 *
 * WooCommerce keeps no record of past prices, so the theme keeps one: every
 * time a product's active price (what a customer pays) changes, the new
 * price and the moment it took effect are added to the product's
 * `_pixonaut_shop_price_history` meta. A change is seen whenever WooCommerce
 * saves a product or a variation (the admin, the REST API, WP-CLI and
 * imports all save through it), and when a scheduled sale starts or ends.
 * WooCommerce's own prices are only read, never changed.
 *
 * The reference price is the lowest active price in the 30 days before the
 * current price took effect. A history shorter than that uses the lowest
 * price it has; a product first seen already on sale, with no earlier price,
 * shows its regular price. A variation has its own history; a variable
 * product's price range shows the lowest reference among its reduced
 * variations.
 */
final class LowestPrice
{
    /** The product meta the price history is kept in: a list of `{ price, from }`, oldest first. */
    public const HISTORY_META = '_pixonaut_shop_price_history';

    /** How far back before a reduction its reference price is looked for. */
    public const WINDOW_DAYS = 30;

    /** The class of the line that shows the reference price. */
    public const NOTE_CLASS = 'pixonaut-shop-lowest-price';

    private const PRICE_KEY = 'price';

    private const FROM_KEY = 'from';

    /** How each entry's moment is written: ISO 8601 with its offset. */
    private const DATE_FORMAT = DATE_ATOM;

    /** WooCommerce's actions after it creates or updates a product or a variation. */
    private const SAVE_HOOKS = [
        'woocommerce_new_product',
        'woocommerce_update_product',
        'woocommerce_new_product_variation',
        'woocommerce_update_product_variation',
    ];

    /** The scheduled actions that start and end one product's sale, each with the product's ID. */
    private const SCHEDULED_SALE_HOOKS = ['wc_product_start_scheduled_sale', 'wc_product_end_scheduled_sale'];

    /** After WooCommerce's own callbacks on those actions, which change the price. */
    private const AFTER_WOOCOMMERCE = 20;

    /** The daily run's actions after it started or ended sales, each with the products' IDs. */
    private const SCHEDULED_RUN_HOOKS = ['wc_after_products_starting_sales', 'wc_after_products_ending_sales'];

    /** Product types whose price is their children's, with no price of their own. */
    private const DERIVED_TYPES = ['variable', 'grouped'];

    /** @var Closure(): DateTimeImmutable */
    private Closure $now;

    /**
     * @param (Closure(): DateTimeImmutable)|null $now The current time; defaults to WordPress's clock.
     */
    public function __construct(?Closure $now = null)
    {
        $this->now = $now ?? static fn (): DateTimeImmutable => current_datetime();
    }

    public function register(): void
    {
        foreach (self::SAVE_HOOKS as $hook) {
            add_action($hook, [$this, 'record_saved'], 10, 2);
        }

        foreach (self::SCHEDULED_SALE_HOOKS as $hook) {
            add_action($hook, [$this, 'record_scheduled'], self::AFTER_WOOCOMMERCE);
        }

        foreach (self::SCHEDULED_RUN_HOOKS as $hook) {
            add_action($hook, [$this, 'record_scheduled_run']);
        }

        add_filter('woocommerce_get_price_html', [$this, 'with_price_html'], 10, 2);
        add_filter('woocommerce_get_item_data', [$this, 'with_item_data'], 10, 2);
        add_filter('woocommerce_duplicate_product_exclude_meta', [$this, 'without_history']);
    }

    /** Records the active price of a product or variation WooCommerce has just saved. */
    public function record_saved(mixed $product_id, mixed $product = null): void
    {
        if ($product instanceof WC_Product) {
            $this->record($product);
        }
    }

    /** Records the active price of a product whose scheduled sale has just started or ended. */
    public function record_scheduled(mixed $product_id): void
    {
        $product = is_numeric($product_id) ? wc_get_product((int) $product_id) : null;

        if ($product instanceof WC_Product) {
            $this->record($product);
        }
    }

    /** Records the active prices of the products the daily run has just started or ended sales for. */
    public function record_scheduled_run(mixed $product_ids): void
    {
        foreach (\is_array($product_ids) ? $product_ids : [] as $product_id) {
            $this->record_scheduled($product_id);
        }
    }

    /**
     * Adds the product's active price to its history when it differs from
     * the last one there. A variable or grouped product has no price of its
     * own; its variations and products are recorded each on their own.
     */
    public function record(WC_Product $product): void
    {
        $price = $product->get_price('edit');

        if ($product->is_type(self::DERIVED_TYPES) || ! is_numeric($price)) {
            return;
        }

        $history = $this->history($product->get_id());
        $updated = self::appended($history, (string) $price, ($this->now)());

        if ($updated !== $history) {
            update_post_meta($product->get_id(), self::HISTORY_META, $updated);
        }
    }

    /**
     * The price HTML of a reduced product, with its reference price after
     * it; any other product's as it is.
     */
    public function with_price_html(mixed $price_html, mixed $product): mixed
    {
        if (! \is_string($price_html) || '' === $price_html || ! $product instanceof WC_Product) {
            return $price_html;
        }

        $reference = $this->reference($product);

        return null === $reference ? $price_html : $price_html . self::note($reference);
    }

    /**
     * A cart line's detail rows, with the reference price after them when
     * the line's product is reduced. The cart, the mini cart and the
     * checkout's summary show these rows under the line.
     */
    public function with_item_data(mixed $item_data, mixed $cart_item): mixed
    {
        $product = \is_array($cart_item) ? ($cart_item['data'] ?? null) : null;
        $reference = $product instanceof WC_Product ? $this->reference($product) : null;

        if (! \is_array($item_data) || null === $reference) {
            return $item_data;
        }

        // The blocks print rows as text, so the price is the text wc_price() shows.
        $price = html_entity_decode(wp_strip_all_tags(wc_price($reference)), ENT_QUOTES, 'UTF-8');
        $item_data[] = ['key' => self::label(), 'value' => $price, 'display' => $price];

        return $item_data;
    }

    /** The meta a duplicated product leaves behind: its copy starts a history of its own. */
    public function without_history(mixed $excluded): mixed
    {
        if (\is_array($excluded)) {
            $excluded[] = self::HISTORY_META;
        }

        return $excluded;
    }

    /**
     * The reference price's line, escaped, for the reduced product an
     * address links to, or null when it links to none. Without WooCommerce
     * there is no product to link to.
     */
    public function for_url(string $url): ?string
    {
        if (! \function_exists('wc_get_product')) {
            return null;
        }

        $product_id = url_to_postid($url);
        $product = 0 === $product_id ? null : wc_get_product($product_id);
        $reference = $product instanceof WC_Product ? $this->reference($product) : null;

        return null === $reference ? null : self::note($reference);
    }

    /**
     * The reference price of a reduced product as it is displayed (with or
     * without tax as the store shows its prices), or null when it is not
     * reduced. A variable or grouped product's is the lowest of its reduced
     * children's.
     */
    public function reference(WC_Product $product): ?float
    {
        if (! $product->is_on_sale()) {
            return null;
        }

        if ($product->is_type(self::DERIVED_TYPES)) {
            return $this->lowest_child_reference($product);
        }

        $price = $product->get_price('edit');
        $lowest = self::lowest($this->history($product->get_id()), \is_string($price) ? $price : '', ($this->now)());
        $reference = $lowest ?? $product->get_regular_price('edit');

        return is_numeric($reference) ? (float) wc_get_price_to_display($product, ['price' => (float) $reference]) : null;
    }

    /**
     * The history with `$price` added as taking effect at `$at`, unless it is
     * already the last price there. Entries that ended more than the window
     * before `$at` are dropped: no reduction from `$at` on can look back to
     * them. The entry in effect when that window opens is kept.
     *
     * @param list<array{price: string, from: string}> $history
     *
     * @return list<array{price: string, from: string}>
     */
    public static function appended(array $history, string $price, DateTimeImmutable $at): array
    {
        $last = end($history);

        if (false !== $last && (float) $last[self::PRICE_KEY] === (float) $price) {
            return $history;
        }

        $history[] = [self::PRICE_KEY => $price, self::FROM_KEY => $at->format(self::DATE_FORMAT)];
        $opens = self::window_start($at);
        $last_index = \count($history) - 1;
        $kept_from = 0;

        while ($kept_from < $last_index && self::moment($history[$kept_from + 1][self::FROM_KEY]) <= $opens) {
            ++$kept_from;
        }

        return \array_slice($history, $kept_from);
    }

    /**
     * The lowest price in the window before the current price took effect,
     * or null when the history knows no price from before it. The current
     * price took effect when the history's last entry did, if that entry is
     * the current price; otherwise it changed without the theme seeing it,
     * no later than `$now`.
     *
     * @param list<array{price: string, from: string}> $history Oldest first.
     */
    public static function lowest(array $history, string $current, DateTimeImmutable $now): ?string
    {
        $last = end($history);
        $started = $now;

        if (false !== $last && is_numeric($current) && (float) $last[self::PRICE_KEY] === (float) $current) {
            $started = self::moment($last[self::FROM_KEY]);
            array_pop($history);
        }

        $opens = self::window_start($started);
        $lowest = null;

        foreach ($history as $index => $entry) {
            $ended = isset($history[$index + 1]) ? self::moment($history[$index + 1][self::FROM_KEY]) : $started;

            if ($ended > $opens && (null === $lowest || (float) $entry[self::PRICE_KEY] < (float) $lowest)) {
                $lowest = $entry[self::PRICE_KEY];
            }
        }

        return $lowest;
    }

    /** The lowest reference price among a product's reduced children, or null when none is. */
    private function lowest_child_reference(WC_Product $product): ?float
    {
        $lowest = null;

        foreach ($product->get_children() as $child_id) {
            $child = wc_get_product($child_id);
            $reference = $child instanceof WC_Product ? $this->reference($child) : null;

            if (null !== $reference && (null === $lowest || $reference < $lowest)) {
                $lowest = $reference;
            }
        }

        return $lowest;
    }

    /**
     * A product's price history, oldest first, without any entry that is not
     * a price and an ISO 8601 moment.
     *
     * @return list<array{price: string, from: string}>
     */
    private function history(int $product_id): array
    {
        $stored = get_post_meta($product_id, self::HISTORY_META, true);
        $history = [];

        foreach (\is_array($stored) ? $stored : [] as $entry) {
            $price = \is_array($entry) ? ($entry[self::PRICE_KEY] ?? null) : null;
            $from = \is_array($entry) ? ($entry[self::FROM_KEY] ?? null) : null;

            if (\is_string($price) && is_numeric($price) && \is_string($from) && false !== DateTimeImmutable::createFromFormat(self::DATE_FORMAT, $from)) {
                $history[] = [self::PRICE_KEY => $price, self::FROM_KEY => $from];
            }
        }

        usort($history, static fn (array $a, array $b): int => self::moment($a[self::FROM_KEY]) <=> self::moment($b[self::FROM_KEY]));

        return $history;
    }

    /** The moment an entry's price took effect; entries are checked when they are read. */
    private static function moment(string $from): DateTimeImmutable
    {
        $moment = DateTimeImmutable::createFromFormat(self::DATE_FORMAT, $from);

        return false === $moment ? new DateTimeImmutable($from) : $moment;
    }

    /** When the window before a moment opens. */
    private static function window_start(DateTimeImmutable $moment): DateTimeImmutable
    {
        return $moment->modify(\sprintf('-%d days', self::WINDOW_DAYS));
    }

    /** What the reference price is called, beside it or as the name of a cart line's row. */
    private static function label(): string
    {
        return \sprintf(
            /* translators: %d: the number of days before a price reduction the lowest price is looked for. */
            __('Lowest price in the last %d days', 'pixonaut-shop'),
            self::WINDOW_DAYS
        );
    }

    /** The reference price's line, escaped, with the price as WooCommerce formats it. */
    private static function note(float $reference): string
    {
        return '<span class="' . esc_attr(self::NOTE_CLASS) . '">' . esc_html(self::label()) . ': ' . wc_price($reference) . '</span>';
    }
}
