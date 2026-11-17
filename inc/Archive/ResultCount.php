<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Archive;

/**
 * The count above an archive's products, "60 products": every product the
 * archive lists, not the page shown, in the words the archive filtered in
 * the browser writes once it takes over (`src/archive/client/drawer.ts`),
 * so the text holds from the first paint, and with no JavaScript at all.
 *
 * WooCommerce's Product Results Count block prints "Showing 1–12 of 60
 * results", which no longer fits a list that loads more as the shopper
 * scrolls. Its number is the listing's own: where Pixonaut for WooCommerce
 * lists a product once per card, or a design once, the listing counts the
 * same, as the index the browser counts from does.
 */
final class ResultCount
{
    private const BLOCK = 'woocommerce/product-results-count';

    /** WooCommerce's paragraph and its text, which may hold the sorting for screen readers. */
    private const PARAGRAPH = '/(<p\b[^>]*\bclass="woocommerce-result-count"[^>]*>).*?(<\/p>)/s';

    public function register(): void
    {
        add_filter('render_block_' . self::BLOCK, [$this, 'render']);
    }

    public function render(string $block_content): string
    {
        $total = wc_get_loop_prop('total');

        if (! is_numeric($total) || (int) $total < 1) {
            return $block_content;
        }

        $text = esc_html(self::text((int) $total));

        return (string) preg_replace_callback(
            self::PARAGRAPH,
            static fn (array $paragraph): string => $paragraph[1] . $text . $paragraph[2],
            $block_content,
            1
        );
    }

    /** "1 product", "60 products": a number of products, as the count says it. */
    public static function text(int $products): string
    {
        return \sprintf(self::words($products), number_format_i18n($products));
    }

    /** The count's words for a number, `%s` for the number. */
    public static function words(int $products): string
    {
        /* translators: %s: how many products the archive shows. */
        return _n('%s product', '%s products', $products, 'pixonaut-shop');
    }
}
