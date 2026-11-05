<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Support;

/**
 * The class-name-to-file-path rule used by the Composer-less fallback
 * autoloader in `functions.php`.
 *
 * Extracted so the translation rule is unit-testable on its own: the
 * fallback branch only runs when `vendor/autoload.php` is absent, which is
 * the theme's real production condition (uploaded without Composer) but
 * not one the test suite can force without moving the real `vendor/`
 * directory out from under every other test. `functions.php` calls
 * `resolve()` from the closure it registers with `spl_autoload_register()`
 * rather than duplicating the rule inline.
 */
final class FallbackAutoloadPath
{
    /** Namespace prefix every class in this theme shares. */
    private const NAMESPACE_PREFIX = 'Pixonaut\\Shop\\';

    /**
     * @param string $class_name   Fully-qualified class name passed to the autoloader.
     * @param string $theme_dir    Theme root directory (`__DIR__` in `functions.php`).
     *
     * @return string|null The file to require, or null when the class is outside this theme's namespace.
     */
    public static function resolve(string $class_name, string $theme_dir): ?string
    {
        if (!str_starts_with($class_name, self::NAMESPACE_PREFIX)) {
            return null;
        }

        $relative = substr($class_name, \strlen(self::NAMESPACE_PREFIX));

        return $theme_dir . '/inc/' . str_replace('\\', '/', $relative) . '.php';
    }
}
