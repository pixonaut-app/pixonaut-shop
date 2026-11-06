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

    /**
     * The tags the wordpress.org theme directory accepts (its API's
     * `feature_list`); a tag outside them fails the directory's upload.
     */
    private const DIRECTORY_TAGS = [
        'grid-layout', 'one-column', 'two-columns', 'three-columns', 'four-columns', 'left-sidebar',
        'right-sidebar', 'wide-blocks', 'accessibility-ready', 'block-patterns', 'block-styles',
        'buddypress', 'custom-background', 'custom-colors', 'custom-header', 'custom-logo',
        'custom-menu', 'editor-style', 'featured-image-header', 'featured-images', 'flexible-header',
        'footer-widgets', 'front-page-post-form', 'full-site-editing', 'full-width-template',
        'microformats', 'post-formats', 'rtl-language-support', 'sticky-post', 'style-variations',
        'template-editing', 'theme-options', 'threaded-comments', 'translation-ready', 'blog',
        'e-commerce', 'education', 'entertainment', 'food-and-drink', 'holiday', 'news',
        'photography', 'portfolio',
    ];

    /** The headers the theme directory requires of style.css. */
    private const REQUIRED_HEADERS = [
        'Theme Name', 'Author', 'Description', 'Version', 'Requires at least', 'Tested up to',
        'Requires PHP', 'License', 'License URI', 'Text Domain',
    ];

    public function testCarriesEveryHeaderTheThemeDirectoryRequires(): void
    {
        foreach (self::REQUIRED_HEADERS as $name) {
            self::assertNotSame('', $this->header($name), "style.css's {$name} is empty.");
        }
    }

    public function testTagsOnlyWithTheThemeDirectorysTags(): void
    {
        $tags = array_map('trim', explode(',', $this->header('Tags')));

        self::assertSame([], array_values(array_diff($tags, self::DIRECTORY_TAGS)));
    }

    public function testNamesNoPluginOutsideTheThemeDirectory(): void
    {
        self::assertStringNotContainsString('Pixonaut for WooCommerce', $this->header('Description'));
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
