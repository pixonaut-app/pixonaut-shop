<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Fonts;

/**
 * Asks for the site title's typeface before the stylesheets do, so the
 * header is drawn in it from the first paint far more often: the face the
 * title's family and weight select, from the theme, its Styles or the Font
 * Library, covering Latin text.
 */
final class TitleFontPreload
{
    private const TITLE_BLOCK = 'core/site-title';

    /** Origins from the lowest to the highest: a later one's family replaces an earlier one's of the same slug. */
    private const ORIGINS = ['default', 'theme', 'custom'];

    private const NORMAL_WEIGHT = 400;

    /** CSS font matching: a wanted weight from 400 to 500 looks up to 500 before it looks lighter. */
    private const NORMAL_UP_TO = 500;

    /** The first code point of Latin text; a face whose range leaves it out draws other scripts. */
    private const BASIC_LATIN = 0x41;

    private const FONT_TYPES = [
        'woff2' => 'font/woff2',
        'woff'  => 'font/woff',
        'ttf'   => 'font/ttf',
        'otf'   => 'font/otf',
    ];

    private const THEME_FILE_PREFIX = 'file:./';

    public function register(): void
    {
        // Before the stylesheets, which name the same file.
        add_action('wp_head', [$this, 'print_link'], 1);
    }

    public function print_link(): void
    {
        $href = $this->href(self::families(), self::title_styles(), self::root_styles());

        if (null === $href) {
            return;
        }

        $extension = strtolower(pathinfo((string) wp_parse_url($href, PHP_URL_PATH), PATHINFO_EXTENSION));

        printf(
            '<link rel="preload" href="%s" as="font" type="%s" crossorigin>' . "\n",
            esc_url($href),
            esc_attr(self::FONT_TYPES[$extension] ?? 'font/' . $extension)
        );
    }

    /**
     * The address of the title's face, or null when the title is drawn in
     * a font the site does not load.
     *
     * @param array<mixed> $families     The families by origin.
     * @param array<mixed> $title_styles The site title block's styles.
     * @param array<mixed> $root_styles  The site's own styles.
     */
    public function href(array $families, array $title_styles, array $root_styles): ?string
    {
        $slug = self::slug(
            Settings::string_at($title_styles, 'typography', 'fontFamily')
            ?? Settings::string_at($root_styles, 'typography', 'fontFamily')
        );

        if (null === $slug) {
            return null;
        }

        $faces  = self::faces($families, $slug);
        $weight = Settings::weight_range(
            ['fontWeight' => Settings::string_at($title_styles, 'typography', 'fontWeight') ?? Settings::string_at($root_styles, 'typography', 'fontWeight')],
            self::NORMAL_WEIGHT
        )[0];
        $face   = self::match($faces, $weight);
        $src    = Settings::at($face, 'src');
        $src    = \is_array($src) ? ($src[0] ?? null) : $src;

        if (!\is_string($src) || '' === $src) {
            return null;
        }

        return str_starts_with($src, self::THEME_FILE_PREFIX)
            ? get_theme_file_uri(substr($src, \strlen(self::THEME_FILE_PREFIX)))
            : $src;
    }

    /**
     * The preset slug a `var:preset|font-family|…` or `var(--wp--preset--font-family--…)` value names.
     */
    private static function slug(mixed $value): ?string
    {
        if (!\is_string($value) || 1 !== preg_match('/font-family(?:\||--)([a-z0-9-]+)/', $value, $matches)) {
            return null;
        }

        return $matches[1];
    }

    /**
     * The families in effect: one per slug, a later origin's replacing an earlier one's.
     *
     * @param array<mixed> $families
     * @return list<array<mixed>>
     */
    private static function effective(array $families): array
    {
        $by_slug = [];

        foreach (self::ORIGINS as $origin) {
            foreach (\is_array($families[$origin] ?? null) ? $families[$origin] : [] as $family) {
                $slug = Settings::string_at($family, 'slug');

                if (\is_array($family) && null !== $slug) {
                    $by_slug[$slug] = $family;
                }
            }
        }

        return array_values($by_slug);
    }

    /**
     * The upright faces that draw Latin text in the typeface the preset
     * `$slug` names first, wherever a family in effect declares them: a
     * preset may share the faces another declares.
     *
     * @param array<mixed> $families
     * @return list<array<mixed>>
     */
    private static function faces(array $families, string $slug): array
    {
        $effective = self::effective($families);
        $stack     = null;

        foreach ($effective as $family) {
            if ($slug === Settings::string_at($family, 'slug')) {
                $stack = Settings::string_at($family, 'fontFamily');
            }
        }

        $typeface = strtolower(Settings::typeface($stack ?? ''));
        $found    = [];

        if ('' === $typeface) {
            return [];
        }

        foreach ($effective as $family) {
            $faces = Settings::at($family, 'fontFace');

            foreach (\is_array($faces) ? $faces : [] as $face) {
                if (
                    \is_array($face)
                    && $typeface === strtolower(Settings::typeface(Settings::string_at($face, 'fontFamily') ?? ''))
                    && 'italic' !== Settings::string_at($face, 'fontStyle')
                    && self::draws_latin(Settings::string_at($face, 'unicodeRange') ?? '')
                ) {
                    $found[] = $face;
                }
            }
        }

        return $found;
    }

    private static function draws_latin(string $range): bool
    {
        if ('' === trim($range)) {
            return true;
        }

        foreach (explode(',', $range) as $part) {
            $bounds = array_map('hexdec', explode('-', str_ireplace('U+', '', trim($part))));

            if ($bounds[0] <= self::BASIC_LATIN && ($bounds[1] ?? $bounds[0]) >= self::BASIC_LATIN) {
                return true;
            }
        }

        return false;
    }

    /**
     * The face the browser picks for `$weight`, by the CSS font matching
     * order: the weight itself, then (from 400 to 500) heavier up to 500,
     * then lighter, then heavier; above 500 heavier first, then lighter.
     *
     * @param list<array<mixed>> $faces
     * @return array<mixed>|null
     */
    private static function match(array $faces, int $weight): ?array
    {
        $best      = null;
        $best_rank = null;

        foreach ($faces as $face) {
            [$low, $high] = Settings::weight_range($face, self::NORMAL_WEIGHT);
            $rank         = self::rank($weight, $low, $high);

            if (null === $best_rank || $rank < $best_rank) {
                $best      = $face;
                $best_rank = $rank;
            }
        }

        return $best;
    }

    /**
     * How far down the matching order a face of weights `$low`–`$high`
     * comes for `$weight`: smaller is picked first.
     *
     * @return array{int, int}
     */
    private static function rank(int $weight, int $low, int $high): array
    {
        if ($weight >= $low && $weight <= $high) {
            return [0, 0];
        }

        $heavier = $low > $weight;
        $gap     = $heavier ? $low - $weight : $weight - $high;

        if ($weight > self::NORMAL_UP_TO) {
            return [$heavier ? 1 : 2, $gap];
        }

        if ($weight >= self::NORMAL_WEIGHT && $heavier && $low <= self::NORMAL_UP_TO) {
            return [1, $gap];
        }

        return [$heavier ? 3 : 2, $gap];
    }

    /**
     * @return array<mixed>
     */
    private static function families(): array
    {
        $families = wp_get_global_settings(['typography', 'fontFamilies']);

        return \is_array($families) ? $families : [];
    }

    /**
     * @return array<mixed>
     */
    private static function title_styles(): array
    {
        $styles = wp_get_global_styles(['blocks', self::TITLE_BLOCK]);

        return \is_array($styles) ? $styles : [];
    }

    /**
     * @return array<mixed>
     */
    private static function root_styles(): array
    {
        $styles = wp_get_global_styles([]);

        return \is_array($styles) ? $styles : [];
    }
}
