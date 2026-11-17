<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Archive;

use Pixonaut\Shop\Assets\BuiltScript;
use WP_Query;

/**
 * The drawer the filters open in from an archive's toolbar
 * (`patterns/product-filters.php`). WooCommerce's Product Filters block draws
 * the button and the dialog; this names them for what they do. The button
 * says how many filters are chosen, the dialog takes its name from a heading,
 * and its footer clears every filter or closes it on the products the filters
 * list, saying how many. WooCommerce renders the panel anew on the server for
 * every choice, whether it swaps it in place or loads the page again, so that
 * number is always the new results' own.
 *
 * Its scripts move the focus into the drawer and back to the button
 * (`src/archive/filters-drawer.ts`), show the grid as skeletons while new
 * results are on their way (`src/archive/filters-loading.ts`) and keep the
 * customer's place from one filtered page to the next where WooCommerce loads
 * the page again (`src/archive/filters-place.ts`). Unticking a value clears the
 * values chosen of the filters it opened (`src/archive/dependent-values.ts`).
 */
final class FilterDrawer
{
    /** The id of the drawer's heading, which names the dialog. */
    public const TITLE_ID = 'pixonaut-shop-filters-title';

    /** The class of the footer's button that clears every filter. */
    public const CLEAR_CLASS = 'pixonaut-shop-filters-clear';

    /** The class of the badge on the toolbar's button with how many filters are chosen. */
    public const COUNT_CLASS = 'pixonaut-shop-filters-count';

    private const FILTERS_BLOCK = 'woocommerce/product-filters';

    private const OPEN_CLASS = 'wc-block-product-filters__open-overlay';

    private const DIALOG_CLASS = 'wc-block-product-filters__overlay-dialog';

    private const HEADER_CLASS = 'wc-block-product-filters__overlay-header';

    private const FOOTER_CLASS = 'wc-block-product-filters__overlay-footer';

    /** The footer's button, which closes the drawer. */
    private const APPLY_CLASS = 'wc-block-product-filters__apply';

    /** The theme's button look, from `theme.json`'s `styles.elements.button`. */
    private const BUTTON_CLASS = 'wp-element-button';

    /**
     * WooCommerce's Active Filters module, which adds `actions.removeAll` to
     * the filters' store; the drawer's "Clear all" needs it whether or not the
     * page shows the chosen filters' block.
     */
    private const REMOVE_ALL_MODULE = 'woocommerce/product-filter-active';

    /** The Interactivity API context WooCommerce gives the block, with the chosen filters. */
    private const CONTEXT = 'data-wp-context';

    private const SCRIPTS = [
        'pixonaut-shop-filters-drawer' => 'filters-drawer',
        'pixonaut-shop-filters-place' => 'filters-place',
        'pixonaut-shop-filters-loading' => 'filters-loading',
        'pixonaut-shop-dependent-values' => 'dependent-values',
    ];

    public function register(): void
    {
        add_filter('render_block_' . self::FILTERS_BLOCK, [$this, 'finish'], 10, 2);
    }

    /**
     * @param array<array-key, mixed> $block
     */
    public function finish(string $block_content, array $block): string
    {
        if (! ProductFilters::is_the_panel($block)) {
            return $block_content;
        }

        foreach (self::SCRIPTS as $handle => $script) {
            BuiltScript::enqueue($handle, $script);
        }

        wp_enqueue_script_module(self::REMOVE_ALL_MODULE);

        $markup = new FilterListMarkup($block_content);
        $chosen = $markup->next_tag() ? $this->chosen_count($markup->get_attribute(self::CONTEXT)) : 0;

        while ($markup->next_tag()) {
            if ($markup->has_class(self::OPEN_CLASS)) {
                $this->finish_the_button($markup, $chosen);
            } elseif ($markup->has_class(self::DIALOG_CLASS)) {
                $markup->remove_attribute('aria-label');
                $markup->set_attribute('aria-labelledby', self::TITLE_ID);
                $markup->set_attribute('aria-modal', 'true');
            } elseif ($markup->has_class(self::HEADER_CLASS)) {
                $markup->add_after_current_tag($this->title());
            } elseif ($markup->has_class(self::FOOTER_CLASS)) {
                $markup->add_after_current_tag($this->clear_button($chosen));
            } elseif ($markup->has_class(self::APPLY_CLASS)) {
                $this->replace_text($markup, $this->show_products());
            }
        }

        return $markup->with_additions();
    }

    /** The toolbar's button: what it opens, and how many filters are chosen. */
    private function finish_the_button(FilterListMarkup $markup, int $chosen): void
    {
        $markup->add_class(self::BUTTON_CLASS);
        $markup->set_attribute('aria-haspopup', 'dialog');
        $markup->set_attribute('aria-expanded', 'false');
        $markup->set_attribute('data-wp-bind--aria-expanded', 'context.isOverlayOpened');

        $this->replace_text($markup, __('Show filters', 'pixonaut-shop'));

        if (0 === $chosen) {
            return;
        }

        // The badge follows the button's text, inside the button.
        while ($markup->next_tag(['tag_closers' => 'visit'])) {
            if ($markup->is_tag_closer() && 'SPAN' === $markup->get_tag()) {
                $markup->add_after_current_tag($this->count_badge($chosen));

                return;
            }
        }
    }

    /** Replaces the first text inside the current tag, which is a button. */
    private function replace_text(FilterListMarkup $markup, string $text): void
    {
        while ($markup->next_token()) {
            if ('#text' === $markup->get_token_name() && '' !== trim($markup->get_modifiable_text())) {
                $markup->set_modifiable_text($text);

                return;
            }

            if ($markup->is_tag_closer() && 'BUTTON' === $markup->get_tag()) {
                return;
            }
        }
    }

    private function count_badge(int $chosen): string
    {
        return \sprintf(
            '<span class="%1$s"><span aria-hidden="true">%2$s</span><span class="screen-reader-text">%3$s</span></span>',
            esc_attr(self::COUNT_CLASS),
            esc_html(number_format_i18n($chosen)),
            /* translators: %s: how many filters the customer has chosen. */
            esc_html(\sprintf(_n('(%s chosen)', '(%s chosen)', $chosen, 'pixonaut-shop'), number_format_i18n($chosen)))
        );
    }

    private function title(): string
    {
        return \sprintf(
            '<h2 id="%1$s" class="pixonaut-shop-filters-title">%2$s</h2>',
            esc_attr(self::TITLE_ID),
            esc_html__('Filters', 'pixonaut-shop')
        );
    }

    /**
     * Clears every filter with WooCommerce's own action, as its Clear button
     * block does; greyed out while none is chosen. The server renders the
     * panel anew for every choice, so its own count holds: a binding to
     * WooCommerce's state would be worked out on the server too, where that
     * state does not exist.
     */
    private function clear_button(int $chosen): string
    {
        return \sprintf(
            '<button type="button" class="%1$s %2$s" data-wp-on--click="actions.removeAll"%3$s>%4$s</button>',
            esc_attr(self::BUTTON_CLASS),
            esc_attr(self::CLEAR_CLASS),
            0 === $chosen ? ' disabled' : '',
            esc_html__('Clear all', 'pixonaut-shop')
        );
    }

    /** "Show 5 products": how many products the chosen filters list. */
    private function show_products(): string
    {
        $query = $GLOBALS['wp_query'] ?? null;

        if (! $query instanceof WP_Query) {
            return __('Show products', 'pixonaut-shop');
        }

        $found = (int) $query->found_posts;

        /* translators: %s: how many products the chosen filters list. */
        return \sprintf(_n('Show %s product', 'Show %s products', $found, 'pixonaut-shop'), number_format_i18n($found));
    }

    /** How many filters WooCommerce counts as chosen, from the context it gives the block. */
    private function chosen_count(null|bool|string $context): int
    {
        $data = \is_string($context) ? json_decode($context, true) : null;

        return \is_array($data) && \is_array($data['activeFilters'] ?? null) ? \count($data['activeFilters']) : 0;
    }
}
