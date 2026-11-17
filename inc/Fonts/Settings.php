<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Fonts;

/**
 * Reads a value from theme.json data, settings or styles by path, where any
 * level may be missing or of another type than the schema says.
 */
final class Settings
{
    /** The weights CSS gives its weight keywords. */
    private const WEIGHT_KEYWORDS = ['normal' => '400', 'bold' => '700'];

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
     * The first typeface a font stack names, without its quotes.
     */
    public static function typeface(string $stack): string
    {
        return trim(explode(',', $stack, 2)[0], " \t\n\"'");
    }

    /**
     * The `fontWeight` of a face or a style as its lowest and highest weight:
     * the same twice for one weight, `$fallback` when it has none. The
     * keywords `normal` and `bold` are the weights CSS gives them.
     *
     * @return array{int, int}
     */
    public static function weight_range(mixed $data, int $fallback): array
    {
        $value = strtr(strtolower(trim(self::string_at($data, 'fontWeight') ?? '')), self::WEIGHT_KEYWORDS);
        $range = '' === $value ? [] : array_map('intval', explode(' ', (string) preg_replace('/\s+/', ' ', $value)));
        $low   = ($range[0] ?? 0) > 0 ? $range[0] : $fallback;
        $high  = ($range[1] ?? 0) > 0 ? $range[1] : $low;

        return [$low, $high];
    }
}
