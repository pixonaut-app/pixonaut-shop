<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Theme;

use PHPUnit\Framework\Attributes\CoversNothing;
use Pixonaut\Shop\Tests\TestCase;

/**
 * readme.txt, which the wordpress.org theme directory reads: the headers it
 * requires, in step with style.css's, a short description it shows in
 * listings, the sections it asks for, and the copyright and licence of
 * everything the theme bundles.
 */
#[CoversNothing]
final class ReadmeTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    /** The wordpress.org account the theme is submitted from. */
    private const CONTRIBUTOR = 'pixonaut';

    /** The longest short description the directory shows. */
    private const SHORT_DESCRIPTION_LIMIT = 150;

    /** Headers readme.txt must repeat from style.css, with the same value. */
    private const SHARED_HEADERS = ['Requires at least', 'Tested up to', 'Requires PHP', 'License', 'License URI'];

    private const SECTIONS = ['Description', 'Frequently Asked Questions', 'Changelog', 'Upgrade Notice', 'Copyright', 'Resources'];

    public function testIsTitledWithTheThemesName(): void
    {
        self::assertStringStartsWith('=== ' . $this->stylesheet_header('Theme Name') . " ===\n", $this->readme());
    }

    public function testNamesTheWordPressOrgAccountItIsSubmittedFrom(): void
    {
        self::assertSame(self::CONTRIBUTOR, $this->readme_header('Contributors'));
    }

    public function testRepeatsStyleCssHeaders(): void
    {
        foreach (self::SHARED_HEADERS as $name) {
            self::assertSame($this->stylesheet_header($name), $this->readme_header($name), "{$name} differs from style.css.");
        }
    }

    public function testHasAShortDescriptionTheDirectoryShowsWhole(): void
    {
        $short = $this->short_description();

        self::assertNotSame('', $short);
        self::assertLessThanOrEqual(self::SHORT_DESCRIPTION_LIMIT, mb_strlen($short));
    }

    public function testHasTheSectionsTheDirectoryAsksFor(): void
    {
        foreach (self::SECTIONS as $section) {
            self::assertStringContainsString("\n== {$section} ==\n", $this->readme());
        }
    }

    public function testCreditsTheBundledTypeface(): void
    {
        self::assertMatchesRegularExpression('/Inter.*The Inter Project Authors.*SIL Open Font License/s', $this->resources());
    }

    public function testCreditsTheScreenshotsPictures(): void
    {
        self::assertStringContainsString('screenshot.png', $this->resources());
    }

    private function readme(): string
    {
        return (string) file_get_contents(self::ROOT . '/readme.txt');
    }

    private function readme_header(string $name): string
    {
        $matched = preg_match('/^' . preg_quote($name, '/') . ':[ \t]*(.+)$/m', $this->readme(), $matches);

        self::assertSame(1, $matched, "readme.txt has no {$name} header.");

        return trim($matches[1]);
    }

    private function stylesheet_header(string $name): string
    {
        $stylesheet = (string) file_get_contents(self::ROOT . '/style.css');
        $matched    = preg_match('/^' . preg_quote($name, '/') . ':[ \t]*(.+)$/m', $stylesheet, $matches);

        self::assertSame(1, $matched, "style.css has no {$name} header.");

        return trim($matches[1]);
    }

    /** The paragraph between the headers and the first section. */
    private function short_description(): string
    {
        $head  = explode("\n== ", $this->readme(), 2)[0];
        $parts = preg_split('/\n\s*\n/', trim($head));

        return false === $parts ? '' : trim((string) end($parts));
    }

    private function resources(): string
    {
        $parts = explode("\n== Resources ==\n", $this->readme(), 2);

        return $parts[1] ?? '';
    }
}
