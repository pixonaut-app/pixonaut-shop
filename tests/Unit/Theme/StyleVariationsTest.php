<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Theme;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Pixonaut\Shop\Tests\TestCase;

/**
 * The style variations are starting points a store picks in Styles and then
 * makes its own. Each one may change the values of the theme's
 * presets, never their slugs: templates, patterns, Pixonaut for WooCommerce
 * and a store's saved Styles refer to presets by slug, so a slug only one
 * variation has would leave them pointing at nothing after a switch.
 */
#[CoversNothing]
final class StyleVariationsTest extends TestCase
{
    /** The variations the theme ships, by file name. */
    private const VARIATIONS = ['natur.json'];

    private const NATURE = 'natur.json';

    private const NATURE_TITLE = 'Nature';

    private const NATURE_TYPEFACE = '"Figtree"';

    /** Every preset list a variation may set, as its keys under `settings`. */
    private const PRESET_LISTS = [
        'colours'       => ['color', 'palette'],
        'font families' => ['typography', 'fontFamilies'],
        'font sizes'    => ['typography', 'fontSizes'],
        'spacing steps' => ['spacing', 'spacingSizes'],
        'shadows'       => ['shadow', 'presets'],
    ];

    public function testShipsNatureAsTheOnlyVariation(): void
    {
        self::assertSame(self::VARIATIONS, array_map('basename', ThemeJson::variation_files()));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function variations(): iterable
    {
        foreach (ThemeJson::variation_files() as $file) {
            yield basename($file) => [$file];
        }
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function variationPresetLists(): iterable
    {
        foreach (ThemeJson::variation_files() as $file) {
            foreach (self::PRESET_LISTS as $name => $path) {
                yield basename($file) . " {$name}" => [$file, $path];
            }
        }
    }

    #[DataProvider('variations')]
    public function testIsANamedVariationOnTheThemesSchema(string $file): void
    {
        $variation = ThemeJson::read($file);

        self::assertSame(ThemeJson::VERSION, $variation['version'] ?? null);
        self::assertIsString($variation['title'] ?? null);
        self::assertNotSame('', $variation['title']);
    }

    /**
     * A variation that sets a preset list sets exactly the theme's slugs; one
     * that leaves it out keeps the theme's own list.
     *
     * @param list<string> $path
     */
    #[DataProvider('variationPresetLists')]
    public function testUsesOnlyTheThemesSlugs(string $file, array $path): void
    {
        self::assertContains(
            ThemeJson::slugs(ThemeJson::read($file), $path),
            [[], ThemeJson::slugs(ThemeJson::theme(), $path)]
        );
    }

    /**
     * A store installs a variation's typeface from the Font Library; the
     * theme serves only its own default.
     */
    #[DataProvider('variations')]
    public function testServesNoFontFile(string $file): void
    {
        self::assertStringNotContainsString('fontFace', (string) file_get_contents($file));
    }

    public function testThemeJsonIsOnTheSameSchema(): void
    {
        self::assertSame(ThemeJson::VERSION, ThemeJson::theme()['version'] ?? null);
    }

    public function testNatureSetsEveryColourAndBothFamiliesInFigtree(): void
    {
        $nature = ThemeJson::read(ThemeJson::ROOT . '/styles/' . self::NATURE);

        self::assertSame(self::NATURE_TITLE, $nature['title'] ?? null);
        self::assertSame(
            ThemeJson::slugs(ThemeJson::theme(), self::PRESET_LISTS['colours']),
            ThemeJson::slugs($nature, self::PRESET_LISTS['colours'])
        );

        foreach (ThemeJson::presets($nature, self::PRESET_LISTS['font families'], 'fontFamily') as $slug => $family) {
            self::assertIsString($family);
            self::assertStringStartsWith(self::NATURE_TYPEFACE . ', ', $family, $slug);
        }
    }
}
