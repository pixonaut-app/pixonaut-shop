<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Theme;

use PHPUnit\Framework\Assert;

/**
 * Reads `theme.json` and the style variations the way the unit suite needs:
 * decoded, and walked by path, with no WordPress runtime to resolve them.
 */
final class ThemeJson
{
    public const ROOT = __DIR__ . '/../../..';

    /** The theme.json schema version every file uses. */
    public const VERSION = 3;

    /** The prefix theme.json gives a path to a file inside the theme. */
    public const FILE_PREFIX = 'file:./';

    /**
     * @return array<mixed>
     */
    public static function theme(): array
    {
        return self::read(self::ROOT . '/theme.json');
    }

    /**
     * @return list<string>
     */
    public static function variation_files(): array
    {
        $files = glob(self::ROOT . '/styles/*.json');

        return false === $files ? [] : $files;
    }

    /**
     * @return array<mixed>
     */
    public static function read(string $file): array
    {
        // No WordPress runtime in a unit test, so no WP_Filesystem.
        $decoded = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        Assert::assertIsArray($decoded);

        return $decoded;
    }

    /**
     * The value at a path of keys, or null when any step is missing.
     *
     * @param array<mixed> $json
     */
    public static function at(array $json, string ...$path): mixed
    {
        $value = $json;

        foreach ($path as $key) {
            if (!\is_array($value) || !\array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * One preset list under `settings`, keyed by slug, with the given field as
     * each value; empty when the file does not set the list.
     *
     * @param array<mixed> $json
     * @param list<string> $path the keys under `settings` that lead to the list
     *
     * @return array<string, mixed>
     */
    public static function presets(array $json, array $path, string $field): array
    {
        $list    = self::at($json, 'settings', ...$path);
        $presets = [];

        foreach (\is_array($list) ? $list : [] as $preset) {
            if (\is_array($preset) && \is_string($preset['slug'] ?? null)) {
                $presets[$preset['slug']] = $preset[$field] ?? null;
            }
        }

        return $presets;
    }

    /**
     * The sorted slugs of one preset list under `settings`.
     *
     * @param array<mixed> $json
     * @param list<string> $path
     *
     * @return list<string>
     */
    public static function slugs(array $json, array $path): array
    {
        $slugs = array_map('strval', array_keys(self::presets($json, $path, 'slug')));
        sort($slugs);

        return $slugs;
    }
}
