<?php

declare(strict_types=1);

namespace Pixonaut\Shop\I18n;

/**
 * Loads the theme's translation catalogue.
 */
final class TextDomain
{
    /** Fixed forever, like the theme's slug. */
    public const DOMAIN = 'pixonaut-shop';

    /** When on `init` the catalogue loads: WordPress's default, named so later hooks can follow it. */
    public const PRIORITY = 10;

    /** Relative to the theme root; must match the make-pot destination and `Domain Path`. */
    private const LANGUAGES_DIR = '/languages';

    public function register(): void
    {
        add_action('init', [$this, 'load'], self::PRIORITY);
    }

    public function load(): void
    {
        load_theme_textdomain(self::DOMAIN, PIXONAUT_SHOP_THEME_DIR . self::LANGUAGES_DIR);
    }
}
