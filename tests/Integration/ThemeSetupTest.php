<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Pixonaut\Shop\I18n\TextDomain;
use Pixonaut\Shop\Product\LowestPrice;
use Pixonaut\Shop\Support\ThemeVersion;

final class ThemeSetupTest extends TestCase
{
    /**
     * wp-env maps the checkout to wp-content/themes/pixonaut-shop whatever
     * the checkout's own directory is called, so the slug a site stores its
     * Styles under is the same here as on every store.
     */
    public function testTheThemeIsActiveUnderItsFixedSlug(): void
    {
        self::assertSame('pixonaut-shop', get_stylesheet());
        self::assertSame('pixonaut-shop', get_template());
    }

    public function testTheTextDomainConstantMatchesTheStylesheetHeader(): void
    {
        self::assertSame(wp_get_theme()->get('TextDomain'), TextDomain::DOMAIN);
    }

    public function testTheThemeVersionMatchesTheStylesheetHeader(): void
    {
        self::assertSame(wp_get_theme()->get('Version'), ThemeVersion::get());
    }

    /**
     * TextDomain::register() hooks an array callback, so has_action()'s
     * string form can never match it: WordPress keys the callback by the
     * instance. Walking the registered callbacks is the reliable way to see
     * it is hooked.
     */
    public function testTheTextDomainIsLoadedOnInit(): void
    {
        global $wp_filter;

        $hooked = false;

        foreach ($wp_filter['init']->callbacks ?? [] as $callbacks) {
            foreach ($callbacks as $callback) {
                $function = $callback['function'];
                $hooked   = $hooked || (\is_array($function) && $function[0] instanceof TextDomain && 'load' === $function[1]);
            }
        }

        self::assertTrue($hooked);
    }

    /**
     * A Swedish store reads the theme's strings from languages/sv_SE.mo.
     * switch_to_locale() is no use here: it refuses a locale whose core
     * language pack is not installed, and the tests site has none. The site's
     * own locale is what a store sets, so the test sets that and reloads the
     * catalogue the way TextDomain does on `init`.
     */
    public function testTheSwedishCatalogueTranslatesTheThemesStrings(): void
    {
        $swedish = static fn (): string => 'sv_SE';
        add_filter('locale', $swedish);

        try {
            unload_textdomain(TextDomain::DOMAIN);
            (new TextDomain())->load();

            self::assertSame('Sidan hittades inte', __('Page not found', 'pixonaut-shop'));
            // The line beside a reduced price, as Swedish stores show it.
            self::assertSame(
                'Lägsta pris senaste 30 dagarna',
                \sprintf(
                    /* translators: %d: the number of days before a price reduction the lowest price is looked for. */
                    __('Lowest price in the last %d days', 'pixonaut-shop'),
                    LowestPrice::WINDOW_DAYS
                )
            );
            // A variable product's price before a variation is chosen, in full and short.
            self::assertSame(
                'Från %s',
                /* translators: %s: a variable product's lowest price. */
                _x('From %s', 'variable product price', 'pixonaut-shop')
            );
            self::assertSame(
                'Fr. %s',
                /* translators: %s: a variable product's lowest price. The short form of "From %s". */
                _x('Fr. %s', 'variable product price, short', 'pixonaut-shop')
            );
        } finally {
            remove_filter('locale', $swedish);
            unload_textdomain(TextDomain::DOMAIN);
            (new TextDomain())->load();
        }
    }
}
