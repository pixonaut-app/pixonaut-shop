<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Blocks;

/**
 * The line icons the icon block offers: 1.6px strokes on a 24px grid, drawn
 * in `currentColor`, so an icon takes the colour of the text around it and a
 * store's Styles colour it like any text. A name is saved in every icon block
 * that uses it, so it never changes; `src/blocks/icon/block.json` declares
 * the same names for the editor.
 */
final class Icons
{
    /** Every icon's shapes inside the shared `<svg>`, by name. */
    private const SHAPES = [
        'account' => '<circle cx="12" cy="8.5" r="3.5"/><path d="M5 20c0-3.6 3.1-6 7-6s7 2.4 7 6"/>',
        'bag'     => '<path d="M5.5 8.5h13l-1 11.5h-11z"/><path d="M9 8.5V7a3 3 0 0 1 6 0v1.5"/>',
        'search'  => '<circle cx="10.5" cy="10.5" r="6"/><path d="M15 15l5 5"/>',
        'menu'    => '<path d="M4 8h16M4 16h16"/>',
        'close'   => '<path d="M6 6l12 12M18 6L6 18"/>',
        'chevron' => '<path d="M6 9l6 6 6-6"/>',
        'info'    => '<circle cx="12" cy="12" r="8.5"/><path d="M12 11v5.5M12 7.75v.5"/>',
        'check'   => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
        'truck'   => '<path d="M3 6.5h11v9H3zM14 9.5h4l3 3v3h-7z"/>'
            . '<circle cx="7" cy="17.5" r="1.8" fill="currentColor" stroke="none"/>'
            . '<circle cx="17" cy="17.5" r="1.8" fill="currentColor" stroke="none"/>',
        'gift'    => '<path d="M4 10h16v10H4zM3 7h18v3H3zM12 7v13M12 7C10 3 6.5 4 7.5 6.2 8.2 7 12 7 12 7zm0 0c2-4 5.5-3 4.5-.8C15.8 7 12 7 12 7z"/>',
        'heart'   => '<path d="M12 20s-7.5-4.6-7.5-10A4.3 4.3 0 0 1 12 7.6 4.3 4.3 0 0 1 19.5 10c0 5.4-7.5 10-7.5 10z"/>',
        'star'    => '<path d="M12 3.5l2.6 5.4 5.9.8-4.3 4.1 1 5.8L12 16.8l-5.2 2.8 1-5.8-4.3-4.1 5.9-.8z"/>',
        'leaf'    => '<path d="M5 19C5 10 10 5 20 4c0 10-5 15-14 15zM5 19l8-8"/>',
        'upload'  => '<path d="M12 15V4m0 0L7.5 8.5M12 4l4.5 4.5M4 15v4h16v-4"/>',
        'clock'   => '<circle cx="12" cy="12" r="8"/><path d="M12 7.5V12l3 2"/>',
        'lock'    => '<path d="M6 10.5h12V20H6zM8.5 10.5V8a3.5 3.5 0 0 1 7 0v2.5"/>',
        'eye'     => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/>',
    ];

    /**
     * Decorative: the heading and text beside an icon say what it means, so a
     * screen reader skips it.
     */
    private const SVG_OPEN = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"'
        . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">';

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_keys(self::SHAPES);
    }

    /** The icon's `<svg>`, or an empty string for a name the set does not have. */
    public static function svg(string $name): string
    {
        $shapes = self::SHAPES[$name] ?? null;

        return null === $shapes ? '' : self::SVG_OPEN . $shapes . '</svg>';
    }
}
