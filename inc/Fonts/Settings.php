<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Fonts;

/**
 * Reads a value from theme.json data, settings or styles by path, where any
 * level may be missing or of another type than the schema says.
 */
final class Settings
{
    public static function at(mixed $data, string ...$path): mixed
    {
        foreach ($path as $key) {
            if (!\is_array($data) || !\array_key_exists($key, $data)) {
                return null;
            }

            $data = $data[$key];
        }

        return $data;
    }

    public static function string_at(mixed $data, string ...$path): ?string
    {
        $value = self::at($data, ...$path);

        return \is_string($value) || \is_int($value) ? (string) $value : null;
    }

    /**
     * The `fontWeight` of a face or a style as its lowest and highest weight:
     * the same twice for one weight, `$fallback` when it has none.
     *
     * @return array{int, int}
     */
    public static function weight_range(mixed $data, int $fallback): array
    {
        $value = trim(self::string_at($data, 'fontWeight') ?? '');
        $range = '' === $value ? [] : array_map('intval', explode(' ', (string) preg_replace('/\s+/', ' ', $value)));
        $low   = ($range[0] ?? 0) > 0 ? $range[0] : $fallback;
        $high  = ($range[1] ?? 0) > 0 ? $range[1] : $low;

        return [$low, $high];
    }
}
