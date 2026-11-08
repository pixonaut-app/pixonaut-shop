<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Header;

/**
 * The header's and footer's menus, each found by the name its navigation
 * menu is saved under.
 *
 * A navigation block that names no menu shows core's fallback, the newest
 * menu, so every menu in the header and footer would show the same links.
 * Each of the theme's menus carries a class that stands for one menu name
 * instead, and a block that names no menu of its own is pointed at the
 * published menu saved under that name. Without one, the block keeps core's
 * fallback; a menu the store picked in the Site Editor is kept as it is.
 */
final class NamedMenus
{
    /** The names (`post_name`) a store's navigation menus are saved under. */
    public const HEADER = 'pixonaut-shop-header';

    public const FOOTER = 'pixonaut-shop-footer';

    public const SERVICE = 'pixonaut-shop-service';

    public const LEGAL = 'pixonaut-shop-legal';

    /** The class the header's navigation block carries. */
    public const HEADER_CLASS = 'pixonaut-shop-header__menu';

    /**
     * The class each menu's navigation block carries in the header and
     * footer, and the menu name it stands for.
     */
    public const MENUS = [
        self::HEADER_CLASS => self::HEADER,
        'pixonaut-shop-footer__links' => self::FOOTER,
        'pixonaut-shop-footer__service' => self::SERVICE,
        'pixonaut-shop-footer__legal' => self::LEGAL,
    ];

    private const NAVIGATION_BLOCK = 'core/navigation';

    private const NAVIGATION_POST_TYPE = 'wp_navigation';

    private const PUBLISHED = 'publish';

    /** Fires when a navigation menu is saved; a deleted one fires `deleted_post`. */
    private const NAVIGATION_SAVED = 'save_post_wp_navigation';

    private const POST_DELETED = 'deleted_post';

    /**
     * The menu found under each name in this request, 0 for none, so every
     * render of a page's header and footer asks the database once per name.
     *
     * @var array<string, int>
     */
    private static array $found = [];

    public function register(): void
    {
        add_filter('render_block_data', [$this, 'name_menu']);
        add_action(self::NAVIGATION_SAVED, [self::class, 'forget']);
        add_action(self::POST_DELETED, [self::class, 'forget']);
    }

    /** Drops the menus found so far, once a menu has been saved or deleted. */
    public static function forget(): void
    {
        self::$found = [];
    }

    /**
     * The parsed block, pointed at its named menu where it is one of the
     * theme's menus, names none of its own, and the store has that menu.
     *
     * @param array<string, mixed> $block
     *
     * @return array<string, mixed>
     */
    public function name_menu(array $block): array
    {
        if (self::NAVIGATION_BLOCK !== ($block['blockName'] ?? null)) {
            return $block;
        }

        $attributes = $block['attrs'] ?? [];

        if (! \is_array($attributes) || isset($attributes['ref'])) {
            return $block;
        }

        $name = self::menu_name($attributes['className'] ?? null);
        $menu = null === $name ? 0 : self::menu($name);

        if (0 === $menu) {
            return $block;
        }

        $attributes['ref'] = $menu;
        $block['attrs'] = $attributes;

        return $block;
    }

    /** The menu name the block's classes stand for, if any does. */
    public static function menu_name(mixed $class_name): ?string
    {
        if (! \is_string($class_name)) {
            return null;
        }

        $classes = preg_split('/\s+/', $class_name);

        foreach (false === $classes ? [] : $classes as $class) {
            if (isset(self::MENUS[$class])) {
                return self::MENUS[$class];
            }
        }

        return null;
    }

    /** The published menu saved under the name, or 0 when there is none. */
    private static function menu(string $name): int
    {
        if (! isset(self::$found[$name])) {
            self::$found[$name] = self::find($name);
        }

        return self::$found[$name];
    }

    private static function find(string $name): int
    {
        $menus = get_posts([
            'post_type' => self::NAVIGATION_POST_TYPE,
            'post_status' => self::PUBLISHED,
            'name' => $name,
            'numberposts' => 1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);

        return (int) ($menus[0] ?? 0);
    }
}
