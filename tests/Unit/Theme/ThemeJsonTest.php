<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Theme;

use PHPUnit\Framework\Attributes\CoversNothing;
use Pixonaut\Shop\Tests\TestCase;

/**
 * The presets every store's Styles, every template and Pixonaut for
 * WooCommerce refer to by slug, and the neutral look a new store starts from. A slug here is fixed: a variation or a store's Styles
 * may change its value, never its name.
 */
#[CoversNothing]
final class ThemeJsonTest extends TestCase
{
    /** The theme's default palette: the design's neutral skin. */
    private const PALETTE = [
        'base'               => '#FFFFFF',
        'surface'            => '#F4F5F7',
        'contrast'           => '#16181D',
        'muted'              => '#5B606B',
        'accent'             => '#2F5BD3',
        'accent-contrast'    => '#FFFFFF',
        'border'             => '#D9DCE1',
        'border-strong'      => '#868C96',
        'highlight'          => '#FFC83D',
        'highlight-contrast' => '#16181D',
        'tint-1'             => '#EAF0FC',
        'tint-2'             => '#FFF1CC',
        'strong'             => '#16181D',
        'strong-contrast'    => '#FFFFFF',
        'error'              => '#C8302C',
        'success'            => '#1F7A4D',
    ];

    private const RADIUS = ['small' => '6px', 'large' => '10px'];

    private const SHADOWS = ['card' => '0 1px 3px rgba(22, 24, 29, 0.08)'];

    private const BUTTON_RADIUS = '6px';

    private const HEADING_WEIGHT = '700';

    /** Core gives an outline button padding of its own, so both buttons name theirs. */
    private const BUTTON_PADDING = [
        'top'    => 'var:preset|spacing|30',
        'right'  => 'var:preset|spacing|50',
        'bottom' => 'var:preset|spacing|30',
        'left'   => 'var:preset|spacing|50',
    ];

    /** The primary button: the design's `Button`, in the heading family at 700, sentence case. */
    private const BUTTON = [
        'color'      => [
            'background' => 'var:preset|color|accent',
            'text'       => 'var:preset|color|accent-contrast',
        ],
        'border'     => [
            'color'  => 'var:preset|color|accent',
            'radius' => self::BUTTON_RADIUS,
            'style'  => 'solid',
            'width'  => '2px',
        ],
        'spacing'    => ['padding' => self::BUTTON_PADDING],
        'typography' => [
            'fontFamily'    => 'var:preset|font-family|heading',
            'fontSize'      => 'var:preset|font-size|medium',
            'fontWeight'    => '700',
            'lineHeight'    => '1.2',
            'textTransform' => 'none',
        ],
    ];

    /** The secondary button: core/button's Outline style, `base` with an `accent` outline. */
    private const OUTLINE_BUTTON = [
        'color'   => [
            'background' => 'var:preset|color|base',
            'text'       => 'var:preset|color|accent',
        ],
        'border'  => [
            'color' => 'var:preset|color|accent',
            'style' => 'solid',
            'width' => '2px',
        ],
        'spacing' => ['padding' => self::BUTTON_PADDING],
    ];

    private const FONT_SIZES = [
        'small'    => '0.875rem',
        'medium'   => '1rem',
        'large'    => '1.25rem',
        'x-large'  => '1.75rem',
        'xx-large' => '2.5rem',
    ];

    /** The fluid sizes' smallest value, on the narrowest screen. */
    private const FLUID_MINIMUMS = [
        'large'    => '1.125rem',
        'x-large'  => '1.5rem',
        'xx-large' => '2rem',
    ];

    private const SPACING = [
        '20' => '0.5rem',
        '30' => '0.75rem',
        '40' => '1rem',
        '50' => '1.5rem',
        '60' => '2.5rem',
        '70' => '4rem',
        '80' => 'clamp(3.5rem, 8vw, 6rem)',
    ];

    private const CONTENT_SIZE = '760px';

    private const WIDE_SIZE = '1240px';

    private const DEFAULT_TYPEFACE = 'Inter';

    /** The face that holds the typeface's room while it loads, in `src/styles/_fonts.css`. */
    private const METRIC_FALLBACK = 'Inter Fallback';

    /** What every font family falls back to when its typeface is not loaded. */
    private const SYSTEM_STACK = 'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif';

    /** The weights the theme serves its typeface in. */
    private const FONT_WEIGHTS = ['400', '500', '600', '700'];

    /** The character sets each weight is split into, matched to a file by name. */
    private const FONT_SUBSETS = ['latin', 'latin-ext'];

    /** The typeface's licence, shipped beside its files. */
    private const FONT_LICENCE = 'assets/fonts/inter/OFL.txt';

    public function testThePaletteIsTheNeutralLook(): void
    {
        $expected = self::PALETTE;
        $actual   = array_map(
            static fn (mixed $colour): mixed => \is_string($colour) ? strtoupper($colour) : $colour,
            ThemeJson::presets(ThemeJson::theme(), ['color', 'palette'], 'color')
        );
        ksort($expected);
        ksort($actual);

        self::assertSame($expected, $actual);
    }

    public function testEveryPaletteSlugHasANameToShowInStyles(): void
    {
        foreach (ThemeJson::presets(ThemeJson::theme(), ['color', 'palette'], 'name') as $slug => $name) {
            self::assertIsString($name, "{$slug} has no name.");
            self::assertNotSame('', $name);
        }
    }

    public function testTheRadiusSteps(): void
    {
        self::assertSame(self::RADIUS, ThemeJson::at(ThemeJson::theme(), 'settings', 'custom', 'radius'));
    }

    /**
     * WordPress's photo and video shapes are left out; the print shapes come
     * from `Blocks\AspectRatios`.
     */
    public function testWordPresssOwnAspectRatiosAreOff(): void
    {
        self::assertFalse(ThemeJson::at(ThemeJson::theme(), 'settings', 'dimensions', 'defaultAspectRatios'));
    }

    public function testTheCardShadowIsTheOnlyShadow(): void
    {
        $theme = ThemeJson::theme();

        self::assertFalse(ThemeJson::at($theme, 'settings', 'shadow', 'defaultPresets'));
        self::assertSame(self::SHADOWS, ThemeJson::presets($theme, ['shadow', 'presets'], 'shadow'));
    }

    public function testButtonsAndHeadings(): void
    {
        $theme = ThemeJson::theme();

        self::assertSame(self::BUTTON_RADIUS, ThemeJson::at($theme, 'styles', 'elements', 'button', 'border', 'radius'));
        self::assertSame(self::HEADING_WEIGHT, ThemeJson::at($theme, 'styles', 'elements', 'heading', 'typography', 'fontWeight'));
    }

    public function testThePrimaryAndSecondaryButtons(): void
    {
        $theme = ThemeJson::theme();

        self::assertSame(self::BUTTON, ThemeJson::at($theme, 'styles', 'elements', 'button'));
        self::assertSame(self::OUTLINE_BUTTON, ThemeJson::at($theme, 'styles', 'blocks', 'core/button', 'variations', 'outline'));
    }

    public function testTheFontSizes(): void
    {
        $theme = ThemeJson::theme();

        self::assertSame(self::FONT_SIZES, ThemeJson::presets($theme, ['typography', 'fontSizes'], 'size'));

        foreach (ThemeJson::presets($theme, ['typography', 'fontSizes'], 'fluid') as $slug => $fluid) {
            if (isset(self::FLUID_MINIMUMS[$slug])) {
                self::assertSame(['min' => self::FLUID_MINIMUMS[$slug], 'max' => self::FONT_SIZES[$slug]], $fluid, $slug);
            } else {
                self::assertFalse($fluid, "{$slug} is a fixed size.");
            }
        }
    }

    public function testTheSpacingSteps(): void
    {
        self::assertSame(self::SPACING, ThemeJson::presets(ThemeJson::theme(), ['spacing', 'spacingSizes'], 'size'));
    }

    public function testTheLayoutWidths(): void
    {
        $theme = ThemeJson::theme();

        self::assertSame(self::CONTENT_SIZE, ThemeJson::at($theme, 'settings', 'layout', 'contentSize'));
        self::assertSame(self::WIDE_SIZE, ThemeJson::at($theme, 'settings', 'layout', 'wideSize'));
    }

    public function testBodyAndHeadingsAreTheDefaultTypefaceWithTheSystemFallback(): void
    {
        self::assertSame(
            ['body' => self::family(), 'heading' => self::family()],
            ThemeJson::presets(ThemeJson::theme(), ['typography', 'fontFamilies'], 'fontFamily')
        );
    }

    public function testTheThemeServesItsTypefaceInEveryWeightAndSubset(): void
    {
        $served = [];

        foreach (self::fontFaces() as $face) {
            self::assertSame(self::DEFAULT_TYPEFACE, $face['fontFamily'] ?? null);
            self::assertSame('normal', $face['fontStyle'] ?? null);
            self::assertIsString($face['unicodeRange'] ?? null);

            $sources = $face['src'] ?? null;
            self::assertIsArray($sources);

            foreach ($sources as $source) {
                self::assertIsString($source);
                self::assertStringStartsWith(ThemeJson::FILE_PREFIX, $source);
                self::assertStringEndsWith('.woff2', $source);
                self::assertFileExists(ThemeJson::ROOT . '/' . substr($source, \strlen(ThemeJson::FILE_PREFIX)));
                $served[] = basename($source);
            }
        }

        $expected = [];
        foreach (self::FONT_SUBSETS as $subset) {
            foreach (self::FONT_WEIGHTS as $weight) {
                $expected[] = strtolower(self::DEFAULT_TYPEFACE) . "-{$subset}-{$weight}-normal.woff2";
            }
        }

        sort($served);
        sort($expected);
        self::assertSame($expected, $served);
    }

    public function testTheTypefacesLicenceShipsBesideIt(): void
    {
        $licence = ThemeJson::ROOT . '/' . self::FONT_LICENCE;

        self::assertFileExists($licence);
        self::assertStringContainsString('SIL Open Font License', (string) file_get_contents($licence));
    }

    public function testNothingIsUppercasedOrLetterSpaced(): void
    {
        foreach ([ThemeJson::ROOT . '/theme.json', ...ThemeJson::variation_files()] as $file) {
            $contents = (string) file_get_contents($file);

            self::assertStringNotContainsStringIgnoringCase('uppercase', $contents, basename($file));
            self::assertStringNotContainsString('letterSpacing', $contents, basename($file));
        }
    }

    private static function family(): string
    {
        return '"' . self::DEFAULT_TYPEFACE . '", "' . self::METRIC_FALLBACK . '", ' . self::SYSTEM_STACK;
    }

    /**
     * @return list<array<mixed>>
     */
    private static function fontFaces(): array
    {
        $faces = [];

        foreach (ThemeJson::presets(ThemeJson::theme(), ['typography', 'fontFamilies'], 'fontFace') as $list) {
            foreach (\is_array($list) ? $list : [] as $face) {
                self::assertIsArray($face);
                $faces[] = $face;
            }
        }

        self::assertNotSame([], $faces, 'The theme serves no font face.');

        return $faces;
    }
}
