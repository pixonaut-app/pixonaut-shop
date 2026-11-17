<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Fonts;

use Pixonaut\Shop\Assets\AssetManager;
use WP_Theme_JSON;
use WP_Theme_JSON_Data;

/**
 * What holds a store's typeface's room while it loads, for any family a store
 * picks: in the Font Library, in its Styles or through a tool. Each family the
 * metrics table knows gets faces on the fonts devices already have, scaled
 * and given the typeface's line metrics, named in its stack right after it,
 * so the text keeps its size and its line breaks when the typeface arrives.
 *
 * A family whose stack already names a fallback of its own (the theme's,
 * `src/styles/_fonts.css`) is left as it is.
 */
final class FallbackFaces
{
    /**
     * The fonts a fallback is drawn in, by the typeface's kind, in the order
     * the stack tries them: Arial or a font drawn to its measures (Windows,
     * macOS, iOS, Linux), then Roboto (Android, which has no Arial). Each
     * names its regular and bold faces as `local()` matches them.
     */
    private const LOCAL_FONTS = [
        'sans' => [
            'Arial'  => [
                'regular' => ['Arial', 'Liberation Sans', 'Arimo'],
                'bold'    => ['Arial Bold', 'Liberation Sans Bold', 'Arimo Bold'],
            ],
            'Roboto' => [
                'regular' => ['Roboto', 'Roboto-Regular'],
                'bold'    => ['Roboto Bold', 'Roboto-Bold'],
            ],
        ],
        'serif' => [
            'Times New Roman' => [
                'regular' => ['Times New Roman', 'Liberation Serif', 'Tinos'],
                'bold'    => ['Times New Roman Bold', 'Liberation Serif Bold', 'Tinos Bold'],
            ],
            'Noto Serif'      => [
                'regular' => ['Noto Serif', 'NotoSerif-Regular'],
                'bold'    => ['Noto Serif Bold', 'NotoSerif-Bold'],
            ],
        ],
    ];

    /** The weight from which a face is bold, so its fallback is the local bold face. */
    private const BOLD_FROM = 600;

    /** The heaviest weight a regular local face stands in for. */
    private const REGULAR_UP_TO = 500;

    /**
     * The weights of the local regular and bold faces, which a variable
     * face's two fallback faces are also measured at.
     */
    private const REGULAR_WEIGHT = 400;
    private const BOLD_WEIGHT    = 700;

    private const PERCENT = 100;

    /** Decimals of a percentage: a hundredth of a percent of a 16px line is far below a pixel. */
    private const PRECISION = 2;

    private FontMetrics $metrics;

    public function __construct(?FontMetrics $metrics = null)
    {
        $this->metrics = $metrics ?? new FontMetrics();
    }

    public function register(): void
    {
        add_filter('wp_theme_json_data_theme', [$this, 'filter_theme_json']);
        add_filter('wp_theme_json_data_user', [$this, 'filter_theme_json']);
        // After AssetManager has enqueued the stylesheet the faces are added to.
        add_action('wp_enqueue_scripts', [$this, 'enqueue'], 11);
        add_filter('block_editor_settings_all', [$this, 'editor_settings']);
    }

    /**
     * Names the fallbacks in every family's stack of a theme.json origin.
     */
    public function filter_theme_json(WP_Theme_JSON_Data $theme_json): WP_Theme_JSON_Data
    {
        $data     = $theme_json->get_data();
        $families = Settings::at($data, 'settings', 'typography', 'fontFamilies');

        if (!\is_array($families)) {
            return $theme_json;
        }

        $named = $this->with_fallbacks($families);

        if ($named === $families) {
            return $theme_json;
        }

        return $theme_json->update_with(
            [
                'version'  => Settings::at($data, 'version') ?? WP_Theme_JSON::LATEST_SCHEMA,
                'settings' => ['typography' => ['fontFamilies' => $named]],
            ]
        );
    }

    public function enqueue(): void
    {
        $css = $this->css(self::merged_families());

        if ('' !== $css) {
            wp_add_inline_style(AssetManager::HANDLE, $css);
        }
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    public function editor_settings(array $settings): array
    {
        $css = $this->css(self::merged_families());

        if ('' !== $css) {
            $styles             = \is_array($settings['styles'] ?? null) ? $settings['styles'] : [];
            $styles[]           = ['css' => $css, '__unstableType' => 'theme', 'isGlobalStyles' => false];
            $settings['styles'] = $styles;
        }

        return $settings;
    }

    /**
     * The families with their fallbacks named, as one list or as lists by origin.
     *
     * @param array<mixed> $families
     * @return array<mixed>
     */
    public function with_fallbacks(array $families): array
    {
        if (!array_is_list($families)) {
            return array_map(fn ($origin) => \is_array($origin) ? $this->with_fallbacks($origin) : $origin, $families);
        }

        $with_faces = self::names_with_faces($families);

        return array_map(
            function ($family) use ($with_faces) {
                if (!\is_array($family) || !\is_string($family['fontFamily'] ?? null)) {
                    return $family;
                }

                $family['fontFamily'] = $this->stack($family['fontFamily'], $with_faces);

                return $family;
            },
            $families
        );
    }

    /**
     * The stack with the fallbacks right after its first typeface, when that
     * is a web font the table knows and the stack names no fallback for it.
     *
     * @param list<string> $with_faces Typefaces, in lower case, that have faces to load.
     */
    public function stack(string $stack, array $with_faces): string
    {
        $parts = explode(',', $stack, 2);
        $name  = Settings::typeface($stack);

        if (
            '' === $name
            || !\in_array(strtolower($name), $with_faces, true)
            || null === $this->metrics->row($name)
            || false !== stripos($stack, $name . ' Fallback')
        ) {
            return $stack;
        }

        $fallbacks = array_map(
            static fn (string $local): string => '"' . self::fallback_name($name, $local) . '"',
            array_keys($this->local_fonts($name))
        );

        return trim($parts[0]) . ', ' . implode(', ', $fallbacks) . (isset($parts[1]) ? ',' . $parts[1] : '');
    }

    /**
     * The fallback faces for every family whose stack names this class's
     * fallbacks: one per weight the typeface has, on each local font.
     *
     * @param array<mixed> $families One list, or lists by origin.
     */
    public function css(array $families): string
    {
        $families = self::flatten($families);
        $weights  = self::weights_by_name($families);
        $css      = [];

        foreach ($families as $family) {
            if (!\is_array($family) || !\is_string($family['fontFamily'] ?? null)) {
                continue;
            }

            $name = Settings::typeface($family['fontFamily']);

            foreach ($this->local_fonts($name) as $local => $faces) {
                $fallback = self::fallback_name($name, $local);

                if (isset($css[$fallback]) || false === strpos($family['fontFamily'], '"' . $fallback . '"')) {
                    continue;
                }

                $css[$fallback] = '';

                foreach ($weights[strtolower($name)] ?? [] as [$descriptor, $weight]) {
                    $css[$fallback] .= $this->face($name, $local, $faces, $descriptor, $weight);
                }
            }
        }

        return implode('', $css);
    }

    /**
     * @param array{regular: list<string>, bold: list<string>} $faces
     */
    private function face(string $name, string $local, array $faces, string $descriptor, int $weight): string
    {
        $bold      = $weight >= self::BOLD_FROM;
        $overrides = $this->metrics->overrides($name, $weight, $local, $bold ? self::BOLD_WEIGHT : self::REGULAR_WEIGHT);

        if (null === $overrides) {
            return '';
        }

        $sources = array_map(
            static fn (string $face): string => 'local("' . $face . '")',
            $bold ? $faces['bold'] : $faces['regular']
        );

        return \sprintf(
            '@font-face{font-family:"%s";font-style:normal;font-weight:%s;src:%s;size-adjust:%s;ascent-override:%s;descent-override:%s;line-gap-override:%s}',
            self::fallback_name($name, $local),
            $descriptor,
            implode(',', $sources),
            self::percent($overrides['size']),
            self::percent($overrides['ascent']),
            self::percent($overrides['descent']),
            self::percent($overrides['line_gap'])
        );
    }

    /**
     * @return array<string, array{regular: list<string>, bold: list<string>}>
     */
    private function local_fonts(string $name): array
    {
        return self::LOCAL_FONTS[$this->metrics->is_serif($name) ? 'serif' : 'sans'];
    }

    private static function fallback_name(string $name, string $local): string
    {
        return $name . ' Fallback ' . $local;
    }

    /**
     * The `font-weight` descriptors of each typeface's upright faces, with
     * the weight each is measured at. A variable face's range gets a regular
     * and a bold fallback face, as far as the range reaches.
     *
     * @param list<mixed> $families
     * @return array<string, array<array-key, array{string, int}>> By typeface, then by descriptor.
     */
    private static function weights_by_name(array $families): array
    {
        $weights = [];

        foreach ($families as $family) {
            $faces = Settings::at($family, 'fontFace');

            foreach (\is_array($faces) ? $faces : [] as $face) {
                $font = Settings::string_at($face, 'fontFamily');

                if (null === $font || 'italic' === Settings::string_at($face, 'fontStyle')) {
                    continue;
                }

                $name          = strtolower(Settings::typeface($font));
                [$low, $high]  = Settings::weight_range($face, self::REGULAR_WEIGHT);
                $weights[$name] ??= [];

                if ($low === $high) {
                    $weights[$name]["{$low}"] = ["{$low}", $low];
                    continue;
                }

                if ($low <= self::REGULAR_UP_TO) {
                    $descriptor                    = $low . ' ' . min($high, self::REGULAR_UP_TO);
                    $weights[$name][$descriptor] = [$descriptor, self::REGULAR_WEIGHT];
                }

                if ($high >= self::BOLD_FROM) {
                    $descriptor                    = max($low, self::BOLD_FROM) . ' ' . $high;
                    $weights[$name][$descriptor] = [$descriptor, self::BOLD_WEIGHT];
                }
            }
        }

        return $weights;
    }

    /**
     * @param list<mixed> $families
     * @return list<string>
     */
    private static function names_with_faces(array $families): array
    {
        return array_map('strval', array_keys(self::weights_by_name($families)));
    }

    /**
     * @param array<mixed> $families
     * @return list<mixed>
     */
    private static function flatten(array $families): array
    {
        if (array_is_list($families)) {
            return $families;
        }

        return array_values(array_merge(...array_values(array_filter($families, 'is_array'))));
    }

    /**
     * Every family the site uses, from the theme, its Styles and the Font Library.
     *
     * @return array<mixed>
     */
    private static function merged_families(): array
    {
        $families = wp_get_global_settings(['typography', 'fontFamilies']);

        return \is_array($families) ? $families : [];
    }

    private static function percent(float $fraction): string
    {
        return round($fraction * self::PERCENT, self::PRECISION) . '%';
    }
}
