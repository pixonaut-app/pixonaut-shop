<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Fonts;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Pixonaut\Shop\Fonts\TitleFontPreload;
use Pixonaut\Shop\Tests\TestCase;

#[CoversClass(TitleFontPreload::class)]
final class TitleFontPreloadTest extends TestCase
{
    private const LATIN = 'U+0000-00FF, U+0131, U+2000-206F';

    private const LATIN_EXT = 'U+0100-02BA, U+1E00-1E9F';

    private const UPLOADS = 'https://example.test/wp-content/uploads/fonts/';

    private const BODY = ['typography' => ['fontFamily' => 'var:preset|font-family|body']];

    /**
     * @return array<string, mixed>
     */
    private static function face(string $weight, string $file, string $range = self::LATIN, string $style = 'normal'): array
    {
        return [
            'fontFamily'   => 'Nunito',
            'fontStyle'    => $style,
            'fontWeight'   => $weight,
            'src'          => [self::UPLOADS . $file],
            'unicodeRange' => $range,
        ];
    }

    /**
     * The theme's Inter overridden by a store's Nunito of the same slug.
     *
     * @return array<string, mixed>
     */
    private static function families(): array
    {
        return [
            'theme'  => [
                [
                    'slug'     => 'body',
                    'fontFace' => [['fontFamily' => 'Inter', 'fontWeight' => '700', 'src' => ['file:./assets/fonts/inter/inter-700.woff2']]],
                ],
            ],
            'custom' => [
                [
                    'slug'     => 'body',
                    'fontFace' => [
                        self::face('400', 'nunito-ext-400.woff2', self::LATIN_EXT),
                        self::face('400', 'nunito-400.woff2'),
                        self::face('800', 'nunito-ext-800.woff2', self::LATIN_EXT),
                        self::face('800', 'nunito-800.woff2'),
                        self::face('800', 'nunito-800-italic.woff2', self::LATIN, 'italic'),
                    ],
                ],
            ],
        ];
    }

    public function testTheTitlesBoldWeightPicksTheNearestHeavierLatinFace(): void
    {
        $href = (new TitleFontPreload())->href(self::families(), ['typography' => ['fontWeight' => '700']], self::BODY);

        self::assertSame(self::UPLOADS . 'nunito-800.woff2', $href);
    }

    public function testTheTitlesOwnFamilyComesBeforeTheSites(): void
    {
        $families                    = self::families();
        $families['custom'][0]['slug'] = 'heading';

        $href = (new TitleFontPreload())->href(
            $families,
            ['typography' => ['fontFamily' => 'var(--wp--preset--font-family--heading)', 'fontWeight' => '400']],
            self::BODY
        );

        self::assertSame(self::UPLOADS . 'nunito-400.woff2', $href);
    }

    public function testAThemeFileIsAskedForAtTheThemesAddress(): void
    {
        Functions\expect('get_theme_file_uri')->once()->with('assets/fonts/inter/inter-700.woff2')->andReturn('https://example.test/theme/inter-700.woff2');

        $families = self::families();
        unset($families['custom']);

        self::assertSame('https://example.test/theme/inter-700.woff2', (new TitleFontPreload())->href($families, ['typography' => ['fontWeight' => '700']], self::BODY));
    }

    /**
     * @return iterable<string, array{array<mixed>, array<mixed>}>
     */
    public static function nothingToLoad(): iterable
    {
        yield 'no family set' => [[], []];
        yield 'a family no origin has' => [[], ['typography' => ['fontFamily' => 'var:preset|font-family|mono']]];
        yield 'a font stack, not a preset' => [[], ['typography' => ['fontFamily' => 'Georgia, serif']]];
    }

    /**
     * @param array<mixed> $title
     * @param array<mixed> $root
     */
    #[DataProvider('nothingToLoad')]
    public function testATitleInAFontTheSiteDoesNotLoadAsksForNothing(array $title, array $root): void
    {
        self::assertNull((new TitleFontPreload())->href(self::families(), $title, $root));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function weights(): iterable
    {
        yield 'the weight itself' => ['400', 'nunito-400.woff2'];
        yield 'regular looks heavier up to 500 first' => ['450', 'nunito-500.woff2'];
        yield 'light looks lighter first' => ['300', 'nunito-200.woff2'];
        yield 'bold looks heavier first' => ['600', 'nunito-800.woff2'];
        yield 'black looks lighter when nothing is heavier' => ['900', 'nunito-800.woff2'];
    }

    #[DataProvider('weights')]
    public function testTheFaceIsTheOneTheBrowserPicks(string $weight, string $file): void
    {
        $families = ['custom' => [[
            'slug'     => 'body',
            'fontFace' => [
                self::face('200', 'nunito-200.woff2'),
                self::face('400', 'nunito-400.woff2'),
                self::face('500', 'nunito-500.woff2'),
                self::face('800', 'nunito-800.woff2'),
            ],
        ],],];

        self::assertSame(self::UPLOADS . $file, (new TitleFontPreload())->href($families, ['typography' => ['fontWeight' => $weight]], self::BODY));
    }

    public function testTheLinkIsPrintedAsAFontPreload(): void
    {
        Functions\when('wp_get_global_settings')->justReturn(self::families());
        Functions\when('wp_get_global_styles')->alias(
            static fn (array $path): array => [] === $path ? self::BODY : ['typography' => ['fontWeight' => '700']]
        );
        Functions\when('wp_parse_url')->alias('parse_url');
        Functions\when('esc_url')->returnArg();
        Functions\when('esc_attr')->returnArg();

        $this->expectOutputString('<link rel="preload" href="' . self::UPLOADS . 'nunito-800.woff2" as="font" type="font/woff2" crossorigin>' . "\n");

        (new TitleFontPreload())->print_link();
    }
}
