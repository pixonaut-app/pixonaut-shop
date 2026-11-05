<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Theme;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every family the theme names keeps its typeface's room while it loads: the
 * stack names a metric-matched face right after the typeface, and the
 * stylesheet declares that face at both weights it draws.
 */
final class FontFallbackTest extends TestCase
{
    private const STYLESHEET = '/src/styles/_fonts.css';

    /** The descriptors that make a face take the room of the typeface it stands in for. */
    private const METRIC_DESCRIPTORS = ['size-adjust', 'ascent-override', 'descent-override', 'line-gap-override'];

    /** A regular and a bold face, so a heading or a price is never synthesised bold. */
    private const FACES_PER_FAMILY = 2;

    /**
     * @return iterable<string, array{array<mixed>}>
     */
    public static function themes(): iterable
    {
        yield 'theme.json' => [ThemeJson::theme()];

        foreach (ThemeJson::variation_files() as $file) {
            yield basename($file) => [ThemeJson::read($file)];
        }
    }

    /**
     * @param array<mixed> $theme
     */
    #[DataProvider('themes')]
    public function testEveryFamilyNamesADeclaredFallbackAfterItsTypeface(array $theme): void
    {
        $families = ThemeJson::presets($theme, ['typography', 'fontFamilies'], 'fontFamily');

        self::assertNotSame([], $families);

        foreach ($families as $slug => $family) {
            self::assertIsString($family);
            self::assertSame(1, preg_match('/^"([^"]+)", "\1 Fallback", /', $family, $matches), $slug . ': ' . $family);
            self::assertFaceIsDeclared($matches[1] . ' Fallback');
        }
    }

    private static function assertFaceIsDeclared(string $name): void
    {
        $css = (string) file_get_contents(ThemeJson::ROOT . self::STYLESHEET);
        preg_match_all('/@font-face\s*\{[^}]*font-family:\s*"' . preg_quote($name, '/') . '";[^}]*\}/', $css, $faces);

        self::assertCount(self::FACES_PER_FAMILY, $faces[0], $name);

        foreach ($faces[0] as $face) {
            self::assertStringContainsString('src: local(', $face);

            foreach (self::METRIC_DESCRIPTORS as $descriptor) {
                self::assertStringContainsString($descriptor . ':', $face, $name);
            }
        }
    }
}
