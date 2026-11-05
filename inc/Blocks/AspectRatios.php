<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Blocks;

use WP_Theme_JSON_Data;

/**
 * The shapes a store picks for its pictures: the aspect-ratio presets the
 * Site Editor lists for an image, a product card's picture among them, and
 * the shapes the product page's preview frame can be held at
 * (`Product\PreviewFrame`).
 *
 * They are the sizes prints are sold in, square first, then the portrait
 * sizes and their landscape turns. theme.json turns WordPress's own list off
 * (`defaultAspectRatios`), whose photo and video shapes a print shop has no
 * use for, and these are added to the theme's settings here, so a developer
 * can change them through `pixonaut_shop_aspect_ratios` without a child
 * theme.
 */
final class AspectRatios
{
    /**
     * Filters the shapes, a list of theme.json aspect-ratio presets: each a
     * `name` the editor shows, a `slug` of lowercase letters, digits and
     * hyphens, and a `ratio` as width/height (`5/7`) or one number.
     */
    public const FILTER = 'pixonaut_shop_aspect_ratios';

    /** The theme.json schema version `theme.json` is written in. */
    private const THEME_JSON_VERSION = 3;

    /** Each portrait print size as width and height; each is offered turned to landscape too. */
    private const PORTRAIT_SIZES = [[4, 5], [5, 7], [3, 4], [2, 3]];

    /** A slug's characters, as WordPress prints them into a preset's custom property. */
    private const SLUG_PATTERN = '/^[a-z0-9-]+$/';

    /** A ratio: one positive number, or two with a slash between them. */
    private const RATIO_PATTERN = '#^\s*(\d+(?:\.\d+)?)\s*(?:/\s*(\d+(?:\.\d+)?)\s*)?$#';

    public function register(): void
    {
        add_filter('wp_theme_json_data_theme', [$this, 'add_presets']);
    }

    public function add_presets(WP_Theme_JSON_Data $data): WP_Theme_JSON_Data
    {
        return $data->update_with([
            'version'  => self::THEME_JSON_VERSION,
            'settings' => ['dimensions' => ['aspectRatios' => $this->presets()]],
        ]);
    }

    /**
     * The shapes, after the filter, leaving out any it got wrong. A slug the
     * filter repeats keeps its first place and takes its last values, as
     * WordPress merges presets, so every reader of the list sees one shape
     * per slug.
     *
     * @return list<array{name: string, slug: string, ratio: string}>
     */
    public function presets(): array
    {
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- the theme's own filter, prefixed.
        $filtered = apply_filters(self::FILTER, $this->defaults());
        $presets  = [];

        foreach (\is_array($filtered) ? $filtered : [] as $preset) {
            if (! \is_array($preset)) {
                continue;
            }

            $name  = $preset['name'] ?? null;
            $slug  = $preset['slug'] ?? null;
            $ratio = $preset['ratio'] ?? null;
            if (
                \is_string($name) && '' !== $name
                && \is_string($slug) && 1 === preg_match(self::SLUG_PATTERN, $slug)
                && \is_string($ratio) && null !== self::aspect($ratio)
            ) {
                $presets[$slug] = ['name' => $name, 'slug' => $slug, 'ratio' => $ratio];
            }
        }

        return array_values($presets);
    }

    /** A ratio's width per unit of height, or null when it is not a ratio. */
    public static function aspect(string $ratio): ?float
    {
        if (1 !== preg_match(self::RATIO_PATTERN, $ratio, $parts)) {
            return null;
        }

        $width  = (float) $parts[1];
        $height = isset($parts[2]) ? (float) $parts[2] : 1.0;

        return $width > 0 && $height > 0 ? $width / $height : null;
    }

    /**
     * @return list<array{name: string, slug: string, ratio: string}>
     */
    private function defaults(): array
    {
        $presets   = [['name' => __('Square - 1:1', 'pixonaut-shop'), 'slug' => 'square', 'ratio' => '1']];
        $landscape = [];

        foreach (self::PORTRAIT_SIZES as [$width, $height]) {
            $presets[] = $this->preset(
                /* translators: %s: a print size's width and height, such as 5:7. */
                __('Portrait - %s', 'pixonaut-shop'),
                $width,
                $height
            );
            $landscape[] = $this->preset(
                /* translators: %s: a print size's width and height, such as 7:5. */
                __('Landscape - %s', 'pixonaut-shop'),
                $height,
                $width
            );
        }

        return [...$presets, ...$landscape];
    }

    /**
     * @return array{name: string, slug: string, ratio: string}
     */
    private function preset(string $name, int $width, int $height): array
    {
        return [
            'name'  => \sprintf($name, "{$width}:{$height}"),
            'slug'  => "{$width}-{$height}",
            'ratio' => "{$width}/{$height}",
        ];
    }
}
