<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Header;

use Pixonaut\Shop\Assets\BuiltScript;

/**
 * The search block that is only its button, as the header holds it.
 *
 * Core's script opens the field from the button and submits it when it is
 * pressed again, and its field is required, so an empty one gets the
 * browser's validation message. The theme's script
 * (`src/header/search.ts`) keeps an empty field open and focused instead,
 * without the message. It loads only on a page with such a search.
 */
final class HeaderSearch
{
    public const SCRIPT_HANDLE = 'pixonaut-shop-header-search';

    /** The built script's name in `build/`. */
    private const SCRIPT = 'header-search';

    /** Filters the search block's output, with the block's attributes. */
    public const SEARCH_BLOCK_FILTER = 'render_block_core/search';

    /** The `buttonPosition` that shows the button alone, until it opens the field. */
    private const BUTTON_ONLY = 'button-only';

    public function register(): void
    {
        add_filter(self::SEARCH_BLOCK_FILTER, [$this, 'load_script'], 10, 2);
    }

    /**
     * The block's output, as it is, after loading the script when the block
     * is only its button.
     *
     * @param array{attrs?: array<string, mixed>} $block
     */
    public function load_script(string $content, array $block): string
    {
        if (self::BUTTON_ONLY === ($block['attrs']['buttonPosition'] ?? null)) {
            BuiltScript::enqueue(self::SCRIPT_HANDLE, self::SCRIPT);
        }

        return $content;
    }
}
