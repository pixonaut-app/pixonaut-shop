<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Blocks;

use Pixonaut\Shop\Assets\BuiltScript;

/**
 * Heads a navigation block with its menu's name, as the store named the
 * menu under Navigation.
 *
 * Core prints a menu's name only as its `<nav>`'s accessible name, so the
 * footer's menus would otherwise carry no visible heading, and a heading
 * written into the pattern would be the theme's words for the store's menu. Only a navigation block that carries `CLASS_NAME` and names a
 * published menu is headed; one falling back to core's default menu has no
 * name to show, and is left as core prints it.
 *
 * The Site Editor renders the navigation block in the browser, past this
 * filter, so an editor script (`src/editor/menu-heading.js`) heads it there
 * the same way, from the class names handed to it here.
 */
final class MenuHeading
{
    /** The class a navigation block carries to be headed by its menu's name. */
    public const CLASS_NAME = 'pixonaut-shop-headed-menu';

    public const HEADING_CLASS = 'pixonaut-shop-headed-menu__heading';

    /** The element the editor wraps a headed menu and its heading in. */
    public const PREVIEW_CLASS = 'pixonaut-shop-headed-menu__preview';

    public const EDITOR_SCRIPT_HANDLE = 'pixonaut-shop-menu-heading';

    /** The built editor script's name in `build/`. */
    private const EDITOR_SCRIPT = 'menu-heading';

    /** The global the editor script reads its class names from. */
    private const EDITOR_CONFIG_GLOBAL = 'window.pixonautShopMenuHeading';

    private const PUBLISHED = 'publish';

    /** The navigation block's own element, the first tag core prints for it. */
    private const NAV_OPENING_TAG = '/<nav\b[^>]*>/';

    public function register(): void
    {
        add_filter('render_block_core/navigation', [$this, 'add_heading'], 10, 2);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_editor_script']);
    }

    /** Loads the editor script, unless the checkout has not been built. */
    public function enqueue_editor_script(): void
    {
        if (! BuiltScript::enqueue(self::EDITOR_SCRIPT_HANDLE, self::EDITOR_SCRIPT)) {
            return;
        }

        wp_add_inline_script(
            self::EDITOR_SCRIPT_HANDLE,
            self::EDITOR_CONFIG_GLOBAL . ' = ' . wp_json_encode([
                'className' => self::CLASS_NAME,
                'headingClassName' => self::HEADING_CLASS,
                'previewClassName' => self::PREVIEW_CLASS,
            ]) . ';',
            'before'
        );
    }

    /**
     * @param array<string, mixed> $block The parsed block.
     */
    public function add_heading(string $content, array $block): string
    {
        $attributes = $block['attrs'] ?? [];

        if (! \is_array($attributes) || ! self::is_headed($attributes)) {
            return $content;
        }

        $name = self::menu_name($attributes['ref'] ?? null);

        if ('' === $name) {
            return $content;
        }

        $heading = \sprintf('<h2 class="%s">%s</h2>', esc_attr(self::HEADING_CLASS), esc_html($name));

        return (string) preg_replace_callback(
            self::NAV_OPENING_TAG,
            static fn (array $nav): string => $nav[0] . $heading,
            $content,
            1
        );
    }

    /**
     * @param array<mixed> $attributes
     */
    private static function is_headed(array $attributes): bool
    {
        $class_name = $attributes['className'] ?? '';

        if (! \is_string($class_name)) {
            return false;
        }

        $classes = preg_split('/\s+/', $class_name);

        return false !== $classes && \in_array(self::CLASS_NAME, $classes, true);
    }

    /** The saved menu's name, or '' when the block names none that is published. */
    private static function menu_name(mixed $ref): string
    {
        if (! \is_int($ref) || self::PUBLISHED !== get_post_status($ref)) {
            return '';
        }

        return trim((string) get_post_field('post_title', $ref));
    }
}
