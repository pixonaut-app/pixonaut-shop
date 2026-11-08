<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Header;

use Pixonaut\Shop\Archive\CategoryImage;
use Pixonaut\Shop\Assets\BuiltScript;

/**
 * The mega header's menu: in place of the header's navigation block, the
 * menu button and the panel it opens, built from the same menu.
 *
 * The menu's top items are the panel's groups, down its start; a group's
 * own items show beside them, as picture tiles when any of them is a
 * product category (its picture and name), or as a list of links when none
 * is. A top item with nothing under it is a plain link among the groups. On
 * phones the panel is a drawer, each group's items under it. Everything is
 * printed here, on the server, in a `<nav>`. The panel is a popover the
 * button opens, so the browser opens and closes it (Escape, a click
 * outside) with no script, and lists every group's items then.
 * `src/header/mega-menu.ts` adds the rest: one group's items at a time,
 * switched by hover, focus or the arrow keys, the drawer's groups opening
 * under their buttons, and focus kept in the open panel.
 *
 * Only when `HeaderVariant` is `mega`, and only with a menu to show: the
 * one saved under the header's name, or picked for the block in the Site
 * Editor. Without one, or with a menu holding no links, core's navigation
 * block renders as it does in the simple header.
 */
final class MegaMenu
{
    public const CLASS_NAME = 'pixonaut-shop-mega';

    /**
     * The start of the panel's, each group's button's and each group's
     * items' IDs; `wp_unique_id()` numbers them, so they stay unique when the
     * header renders more than once on a page.
     */
    private const PANEL_ID = 'pixonaut-shop-mega-';

    private const GROUP_ID = 'pixonaut-shop-mega-group-';

    private const ITEMS_ID = 'pixonaut-shop-mega-items-';

    public const SCRIPT_HANDLE = 'pixonaut-shop-mega-menu';

    /** The built script's name in `build/`. */
    private const SCRIPT = 'header-mega-menu';

    private const NAVIGATION_BLOCK = 'core/navigation';

    private const LINK_BLOCK = 'core/navigation-link';

    private const SUBMENU_BLOCK = 'core/navigation-submenu';

    private const PUBLISHED = 'publish';

    /** How a navigation link names a product category it was picked as. */
    private const TAXONOMY_KIND = 'taxonomy';

    private const CATEGORY_TAXONOMY = 'product_cat';

    /** The address core gives a submenu that leads nowhere of its own. */
    private const NO_ADDRESS = '#';

    /**
     * How wide a tile's picture shows, for the browser to pick its file: a
     * small square beside the name in a phone's drawer, below WordPress's
     * 782px breakpoint, and a tile of about 10rem in the panel
     * (`src/styles/_mega-menu.css`).
     */
    private const TILE_IMAGE_SIZES = '(max-width: 781px) 3rem, 10rem';

    /**
     * The panel's custom property holding how many rows its groups take, so
     * the group's items beside them span them all.
     */
    private const ROWS_PROPERTY = '--pixonaut-shop-mega-rows';

    /** The line icons the panel draws, as `Blocks\Icons` draws them: that set is not in every build. */
    private const ICON_OPEN = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"'
        . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">';

    private const MENU_ICON = self::ICON_OPEN . '<path d="M4 8h16M4 16h16"/></svg>';

    private const CLOSE_ICON = self::ICON_OPEN . '<path d="M6 6l12 12M18 6L6 18"/></svg>';

    private const CHEVRON_ICON = self::ICON_OPEN . '<path d="M9 6l6 6-6 6"/></svg>';

    public function __construct(private readonly NamedMenus $menus)
    {
    }

    public function register(): void
    {
        add_filter('pre_render_block', [$this, 'render'], 10, 2);
    }

    /**
     * The panel in place of the header's navigation block, before core
     * renders it; any other block, or the header's in the simple header,
     * renders as it would.
     *
     * @param array<string, mixed> $block The parsed block.
     */
    public function render(?string $pre_render, array $block): ?string
    {
        if (null !== $pre_render || ! self::is_header_menu($block) || HeaderVariant::MEGA !== HeaderVariant::current()) {
            return $pre_render;
        }

        $named  = $this->menus->name_menu($block)['attrs'] ?? null;
        $menu   = \is_array($named) ? ($named['ref'] ?? null) : null;
        $groups = \is_int($menu) && self::PUBLISHED === get_post_status($menu)
            ? self::groups(parse_blocks((string) get_post_field('post_content', $menu)))
            : [];

        if ([] === $groups) {
            return $pre_render;
        }

        BuiltScript::enqueue(self::SCRIPT_HANDLE, self::SCRIPT);

        return self::panel($groups);
    }

    /**
     * @param array<string, mixed> $block
     */
    private static function is_header_menu(array $block): bool
    {
        $attributes = $block['attrs'] ?? null;

        return self::NAVIGATION_BLOCK === ($block['blockName'] ?? null)
            && \is_array($attributes)
            && NamedMenus::HEADER === NamedMenus::menu_name($attributes['className'] ?? null);
    }

    /**
     * The menu's top links and submenus, each with its own links and
     * submenus. A submenu among a group's items is one item; what it holds
     * is left out.
     *
     * @param array<mixed> $blocks
     *
     * @return list<array{label: string, url: string, children: list<array{label: string, url: string, category: bool, image: string}>}>
     */
    private static function groups(array $blocks): array
    {
        $groups = [];

        foreach (self::items($blocks) as $item) {
            $groups[] = ['item' => $item, 'children' => self::items($item['block']['innerBlocks'] ?? [])];
        }

        self::prime(array_merge(...array_map(
            static fn (array $group): array => array_filter(array_map(self::category_id(...), $group['children'])),
            $groups
        )));

        return array_map(static fn (array $group): array => [
            'label' => $group['item']['label'],
            'url' => $group['item']['url'],
            'children' => array_map(self::child(...), $group['children']),
        ], $groups);
    }

    /**
     * Loads the categories, their meta and their pictures' attachments,
     * each in one query, before the items look them up one by one.
     *
     * @param array<int> $categories
     */
    private static function prime(array $categories): void
    {
        $categories = array_values(array_unique($categories));

        if ([] === $categories) {
            return;
        }

        _prime_term_caches($categories);
        update_termmeta_cache($categories);
        CategoryImage::prime($categories);
    }

    /**
     * The term ID of a product category the item was picked as, or 0.
     *
     * @param array{label: string, url: string, block: array<mixed>} $item
     */
    private static function category_id(array $item): int
    {
        $attributes = \is_array($item['block']['attrs'] ?? null) ? $item['block']['attrs'] : [];
        $term       = $attributes['id'] ?? null;

        return self::TAXONOMY_KIND === ($attributes['kind'] ?? null)
            && self::CATEGORY_TAXONOMY === ($attributes['type'] ?? null)
            && \is_int($term) && $term > 0
            ? $term
            : 0;
    }

    /**
     * The links and submenus among the blocks that have a label.
     *
     * @param mixed $blocks
     *
     * @return list<array{label: string, url: string, block: array<mixed>}>
     */
    private static function items(mixed $blocks): array
    {
        $items = [];

        foreach (\is_array($blocks) ? $blocks : [] as $block) {
            if (! \is_array($block) || ! \in_array($block['blockName'] ?? null, [self::LINK_BLOCK, self::SUBMENU_BLOCK], true)) {
                continue;
            }

            $attributes = \is_array($block['attrs'] ?? null) ? $block['attrs'] : [];
            $label      = self::text($attributes['label'] ?? '');

            if ('' === $label) {
                continue;
            }

            $url     = $attributes['url'] ?? '';
            $items[] = ['label' => $label, 'url' => \is_string($url) ? $url : '', 'block' => $block];
        }

        return $items;
    }

    /**
     * A group's item, with the category's own address and picture when it
     * was picked as a product category.
     *
     * @param array{label: string, url: string, block: array<mixed>} $item
     *
     * @return array{label: string, url: string, category: bool, image: string}
     */
    private static function child(array $item): array
    {
        $term = self::category_id($item);
        $link = 0 === $term ? null : get_term_link($term, self::CATEGORY_TAXONOMY);

        if (! \is_string($link)) {
            return ['label' => $item['label'], 'url' => $item['url'], 'category' => false, 'image' => ''];
        }

        return [
            'label' => $item['label'],
            'url' => $link,
            'category' => true,
            'image' => CategoryImage::html($term, [
                'loading' => 'lazy',
                'decoding' => 'async',
                'sizes' => self::TILE_IMAGE_SIZES,
            ]),
        ];
    }

    /** A label as plain text: a menu item's label may hold formatting. */
    private static function text(mixed $label): string
    {
        return \is_string($label) ? trim(html_entity_decode(wp_strip_all_tags($label), ENT_QUOTES)) : '';
    }

    /**
     * @param non-empty-list<array{label: string, url: string, children: list<array{label: string, url: string, category: bool, image: string}>}> $groups
     */
    private static function panel(array $groups): string
    {
        $menu  = __('Menu', 'pixonaut-shop');
        $panel = wp_unique_id(self::PANEL_ID);
        $rows  = '';
        $count = 0;

        foreach ($groups as $group) {
            if ([] !== $group['children']) {
                $rows .= self::group($group);
                ++$count;
            } elseif ('' !== $group['url']) {
                $rows .= \sprintf(
                    '<a class="%s__group" href="%s">%s</a>',
                    self::CLASS_NAME,
                    esc_url($group['url']),
                    esc_html($group['label'])
                );
                ++$count;
            }
        }

        return \sprintf(
            '<nav class="%1$s" aria-label="%2$s">'
            . '<button type="button" class="%1$s__toggle" popovertarget="%3$s" aria-controls="%3$s" aria-label="%4$s">%5$s</button>'
            . '<div id="%3$s" class="%1$s__panel" popover>'
            . '<div class="%1$s__head"><span class="%1$s__title">%6$s</span>'
            . '<button type="button" class="%1$s__close" popovertarget="%3$s" popovertargetaction="hide" aria-label="%7$s">%8$s</button></div>'
            . '<div class="%1$s__groups" style="%9$s:%10$d">%11$s</div>'
            . '</div>'
            . '</nav>',
            self::CLASS_NAME,
            esc_attr__('Main menu', 'pixonaut-shop'),
            esc_attr($panel),
            esc_attr($menu),
            self::MENU_ICON,
            esc_html($menu),
            esc_attr__('Close menu', 'pixonaut-shop'),
            self::CLOSE_ICON,
            self::ROWS_PROPERTY,
            $count,
            $rows
        );
    }

    /**
     * A group's button, and its items after it. Every group is open until
     * the script picks one, so the panel lists them all without it.
     *
     * @param array{label: string, url: string, children: list<array{label: string, url: string, category: bool, image: string}>} $group
     */
    private static function group(array $group): string
    {
        $label = esc_html($group['label']);
        $all   = '' === $group['url'] || self::NO_ADDRESS === $group['url'] ? '' : \sprintf(
            '<a class="%s__all" href="%s">%s</a>',
            self::CLASS_NAME,
            esc_url($group['url']),
            esc_html(\sprintf(
                /* translators: %s: a group of the header's menu, as the store named it. */
                __('All of %s', 'pixonaut-shop'),
                $group['label']
            ))
        );

        return \sprintf(
            '<button type="button" class="%1$s__group" id="%2$s" aria-expanded="true" aria-controls="%3$s">%4$s%5$s</button>'
            . '<div class="%1$s__children" id="%3$s" role="group" aria-labelledby="%2$s">'
            . '<div class="%1$s__children-head"><p class="%1$s__heading" aria-hidden="true">%4$s</p>%6$s</div>'
            . '%7$s'
            . '</div>',
            self::CLASS_NAME,
            esc_attr(wp_unique_id(self::GROUP_ID)),
            esc_attr(wp_unique_id(self::ITEMS_ID)),
            $label,
            self::CHEVRON_ICON,
            $all,
            self::children($group['children'])
        );
    }

    /**
     * Tiles when any item is a product category, otherwise a list of links.
     *
     * @param list<array{label: string, url: string, category: bool, image: string}> $children
     */
    private static function children(array $children): string
    {
        $tiles = \in_array(true, array_column($children, 'category'), true);
        $items = '';

        foreach ($children as $child) {
            $items .= $tiles
                ? \sprintf(
                    '<li><a class="%1$s__tile" href="%2$s"><span class="%1$s__picture%3$s">%4$s</span><span class="%1$s__name">%5$s</span></a></li>',
                    self::CLASS_NAME,
                    esc_url($child['url']),
                    '' === $child['image'] ? ' is-empty' : '',
                    $child['image'],
                    esc_html($child['label'])
                )
                : \sprintf('<li><a href="%s">%s</a></li>', esc_url($child['url']), esc_html($child['label']));
        }

        return \sprintf('<ul class="%s__%s">%s</ul>', self::CLASS_NAME, $tiles ? 'tiles' : 'links', $items);
    }
}
