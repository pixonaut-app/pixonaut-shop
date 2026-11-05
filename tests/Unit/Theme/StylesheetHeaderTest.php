<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Theme;

use PHPUnit\Framework\Attributes\CoversNothing;
use Pixonaut\Shop\I18n\TextDomain;
use Pixonaut\Shop\Tests\TestCase;

/**
 * style.css's header carries what a site and the update checker read: the
 * fixed text domain, the repository updates come from,
 * and the version, which package.json repeats for the release scripts.
 */
#[CoversNothing]
final class StylesheetHeaderTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    private const REPOSITORY = 'https://github.com/pixonaut-app/pixonaut-shop';

    /** A release version, `X.Y.Z`: the shape release.yml accepts as a tag. */
    private const RELEASE_VERSION = '/^\d+\.\d+\.\d+$/';

    public function testNamesTheFixedTextDomain(): void
    {
        self::assertSame(TextDomain::DOMAIN, $this->header('Text Domain'));
    }

    public function testNamesTheRepositoryUpdatesComeFrom(): void
    {
        self::assertSame(self::REPOSITORY, $this->header('Update URI'));
    }

    public function testCarriesAReleaseVersionThatPackageJsonRepeats(): void
    {
        $version = $this->header('Version');
        $package = json_decode((string) file_get_contents(self::ROOT . '/package.json'), true, 512, JSON_THROW_ON_ERROR);

        self::assertMatchesRegularExpression(self::RELEASE_VERSION, $version);
        self::assertIsArray($package);
        self::assertSame($version, $package['version'] ?? null);
    }

    private function header(string $name): string
    {
        // No WordPress runtime in a unit test, so neither get_file_data() nor WP_Filesystem.
        $stylesheet = (string) file_get_contents(self::ROOT . '/style.css');
        $matched    = preg_match('/^' . preg_quote($name, '/') . ':\s*(.+)$/m', $stylesheet, $matches);

        self::assertSame(1, $matched, "style.css has no {$name} header.");

        return trim($matches[1]);
    }
}
