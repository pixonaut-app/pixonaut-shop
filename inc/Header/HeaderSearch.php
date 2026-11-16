<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Header;

use Pixonaut\Shop\Assets\BuiltScript;
use Pixonaut\Shop\Blocks\Icons;
use Pixonaut\Shop\I18n\TextDomain;

/**
 * The header's search: the search block that is only its button, as the
 * header holds it, printed as the search icon and the panel it opens under
 * the header.
 *
 * The panel is a popover the icon opens, so the browser opens and closes it
 * (Escape, a click outside) and moves focus into its field with no script;
 * the field's form searches the store's products, so a search works without
 * script too. `src/header/search.ts` adds the rest: product suggestions from
 * WooCommerce's Store API as the shopper types, picked with the arrow keys,
 * the count and a link to every result, and the panel closing when focus
 * leaves it.
 *
 * Only the block in its button-only form: a search that shows its field,
 * such as the one on the results page, renders as core prints it.
 */
final class HeaderSearch
{
    public const CLASS_NAME = 'pixonaut-shop-header-search';

    public const SCRIPT_HANDLE = 'pixonaut-shop-header-search';

    /** The built script's name in `build/`. */
    private const SCRIPT = 'header-search';

    /** Filters the search block's output, with the block's attributes. */
    public const SEARCH_BLOCK_FILTER = 'render_block_core/search';

    /** The `buttonPosition` that shows the button alone, until it opens the field. */
    private const BUTTON_ONLY = 'button-only';

    /** The post type the search looks among: the store's products. */
    private const PRODUCT = 'product';

    /** WooCommerce's Store API route that lists products, which the suggestions come from. */
    private const PRODUCTS_ROUTE = 'wc/store/v1/products';

    /** Where the script reads its translations from, as `pnpm i18n:mo` writes them. */
    private const LANGUAGES_DIR = 'languages';

    /**
     * The start of the panel's, the field's, the list's and the hint's IDs;
     * `wp_unique_id()` numbers them, so they stay unique when the header
     * renders more than once on a page.
     */
    private const ID_PREFIX = 'pixonaut-shop-header-search-';

    public function register(): void
    {
        add_filter(self::SEARCH_BLOCK_FILTER, [$this, 'render'], 10, 2);
    }

    /**
     * The header's search in place of the block's output when the block is
     * only its button; any other search block as it is.
     *
     * @param array{attrs?: array<string, mixed>} $block
     */
    public function render(string $content, array $block): string
    {
        $attributes = $block['attrs'] ?? [];

        if (self::BUTTON_ONLY !== ($attributes['buttonPosition'] ?? null)) {
            return $content;
        }

        if (BuiltScript::enqueue(self::SCRIPT_HANDLE, self::SCRIPT)) {
            wp_set_script_translations(
                self::SCRIPT_HANDLE,
                TextDomain::DOMAIN,
                get_theme_file_path(self::LANGUAGES_DIR)
            );
        }

        return self::markup($attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private static function markup(array $attributes): string
    {
        $id          = wp_unique_id(self::ID_PREFIX);
        $panel       = $id . '-panel';
        $field       = $id . '-field';
        $list        = $id . '-list';
        $hint        = $id . '-hint';
        $placeholder = \is_string($attributes['placeholder'] ?? null) && '' !== trim($attributes['placeholder'])
            ? $attributes['placeholder']
            : __('Search products…', 'pixonaut-shop');
        $label       = __('Search products', 'pixonaut-shop');
        $close       = __('Close search', 'pixonaut-shop');

        return \sprintf(
            '<div class="wp-block-search %1$s">'
            . '<button type="button" class="%1$s__toggle" popovertarget="%2$s" aria-controls="%2$s" aria-label="%3$s">%4$s</button>'
            . '<div id="%2$s" class="%1$s__panel" popover data-products="%5$s">'
            . '<div class="%1$s__inner">'
            . '<form class="%1$s__form" role="search" method="get" action="%6$s">'
            . '<label class="screen-reader-text" for="%7$s">%3$s</label>'
            . '<span class="%1$s__field">%8$s'
            . '<input id="%7$s" class="%1$s__input" type="search" name="s" placeholder="%9$s" autocomplete="off"'
            . ' enterkeyhint="search" autofocus role="combobox" aria-expanded="false" aria-controls="%10$s"'
            . ' aria-autocomplete="list" aria-describedby="%11$s">'
            . '</span>'
            . '<input type="hidden" name="post_type" value="%12$s">'
            . '<button type="submit" class="wp-element-button %1$s__submit">%13$s</button>'
            . '<button type="button" class="%1$s__close" popovertarget="%2$s" popovertargetaction="hide" aria-label="%14$s">%15$s</button>'
            . '</form>'
            . '<div class="%1$s__status">'
            . '<p id="%11$s" class="%1$s__hint">%16$s</p>'
            . '<p class="%1$s__count" hidden></p>'
            . '<a class="%1$s__all" href="%6$s" hidden></a>'
            . '<p class="%1$s__none" hidden></p>'
            . '</div>'
            . '<ul id="%10$s" class="%1$s__list" role="listbox" aria-label="%17$s" hidden></ul>'
            . '<p class="%1$s__help" hidden>%18$s</p>'
            . '<p class="screen-reader-text %1$s__announce" role="status"></p>'
            . '</div>'
            . '</div>'
            . '</div>',
            self::CLASS_NAME,
            esc_attr($panel),
            esc_attr($label),
            Icons::svg('search'),
            esc_url(rest_url(self::PRODUCTS_ROUTE)),
            esc_url(home_url('/')),
            esc_attr($field),
            Icons::svg('search'),
            esc_attr($placeholder),
            esc_attr($list),
            esc_attr($hint),
            esc_attr(self::PRODUCT),
            esc_html__('Search', 'pixonaut-shop'),
            esc_attr($close),
            Icons::svg('close'),
            esc_html__('Type two letters or more to see suggestions.', 'pixonaut-shop'),
            esc_attr__('Suggestions', 'pixonaut-shop'),
            esc_html__('Check the spelling or try a shorter word. Press Enter to search the whole store.', 'pixonaut-shop')
        );
    }
}
