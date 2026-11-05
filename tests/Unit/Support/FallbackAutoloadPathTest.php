<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\CoversClass;
use Pixonaut\Shop\Support\FallbackAutoloadPath;
use Pixonaut\Shop\Tests\TestCase;

/**
 * The Composer-less fallback autoloader in `functions.php` only runs when
 * `vendor/autoload.php` is missing, which the suite cannot force without
 * moving the real vendor directory. This exercises the class-to-path rule it
 * delegates to, so a broken namespace prefix fails here rather than as a
 * fatal "class not found" on a site.
 */
#[CoversClass(FallbackAutoloadPath::class)]
final class FallbackAutoloadPathTest extends TestCase
{
    private const THEME_DIR = '/theme';

    public function testResolvesAKnownClassToItsFilePath(): void
    {
        self::assertSame(
            '/theme/inc/Support/ThemeVersion.php',
            FallbackAutoloadPath::resolve('Pixonaut\\Shop\\Support\\ThemeVersion', self::THEME_DIR)
        );
    }

    public function testResolvesANestedNamespaceToItsDirectory(): void
    {
        self::assertSame(
            '/theme/inc/Updates/GitHubUpdater.php',
            FallbackAutoloadPath::resolve('Pixonaut\\Shop\\Updates\\GitHubUpdater', self::THEME_DIR)
        );
    }

    public function testReturnsNullForAClassOutsideTheThemeNamespace(): void
    {
        self::assertNull(FallbackAutoloadPath::resolve('Pixonaut\\WooCommerce\\Plugin', self::THEME_DIR));
    }
}
