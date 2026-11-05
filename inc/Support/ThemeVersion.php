<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Support;

/**
 * The theme version, read from style.css.
 *
 * style.css is the single source of truth: the release workflow stamps it,
 * and WordPress reads it. Nothing else in the codebase repeats the number.
 */
final class ThemeVersion
{
    /** Used when style.css is unreadable or carries no Version header. */
    public const FALLBACK = '0.0.0';

    /** Only the header comment is scanned, so a large file is never read whole. */
    private const HEADER_BYTES = 8192;

    /** @var array<string, string> */
    private static array $cached = [];

    public static function get(?string $stylesheet = null): string
    {
        $path = $stylesheet ?? PIXONAUT_SHOP_THEME_DIR . '/style.css';

        if (isset(self::$cached[$path])) {
            return self::$cached[$path];
        }

        self::$cached[$path] = self::read($path);

        return self::$cached[$path];
    }

    private static function read(string $path): string
    {
        if (!is_readable($path)) {
            return self::FALLBACK;
        }

        // WP_Filesystem isn't guaranteed to be loaded this early in bootstrap;
        // WordPress core's own get_file_data() reads theme/plugin headers the
        // same way, via raw PHP I/O rather than the filesystem abstraction.
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $header = file_get_contents($path, false, null, 0, self::HEADER_BYTES);

        if (false === $header) {
            return self::FALLBACK;
        }

        if (1 !== preg_match('/^[ \t\/*#@]*Version:\s*(\S+)$/mi', $header, $matches)) {
            return self::FALLBACK;
        }

        return $matches[1];
    }

    /** Clears the memoised values. Tests only. */
    public static function reset(): void
    {
        self::$cached = [];
    }
}
