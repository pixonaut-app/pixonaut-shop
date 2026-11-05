<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use PHPUnit\Framework\TestCase;
use WP_Theme_JSON_Resolver;

/**
 * What WordPress prints from theme.json on every page: the custom properties
 * Pixonaut for WooCommerce styles its designer with, the theme's typeface,
 * and a style variation's values once a store applies it in Styles; and the
 * picture shapes the editor offers.
 */
final class GlobalStylesTest extends TestCase
{
    /** The custom properties the plugin reads; their slugs never change. */
    private const CONTRACT_PROPERTIES = [
        '--wp--preset--color--accent',
        '--wp--preset--color--accent-contrast',
        '--wp--preset--color--highlight',
        '--wp--preset--color--highlight-contrast',
        '--wp--preset--color--border',
        '--wp--preset--color--border-strong',
        '--wp--preset--color--error',
        '--wp--preset--color--success',
        '--wp--preset--color--contrast',
        '--wp--preset--color--muted',
        '--wp--preset--color--base',
        '--wp--preset--color--surface',
        '--wp--preset--font-family--body',
        '--wp--preset--font-family--heading',
        '--wp--custom--radius--small',
        '--wp--custom--radius--large',
        '--wp--preset--shadow--card',
    ];

    private const NATURE = 'Nature';

    private const NATURE_FILE = 'styles/natur.json';

    /** What Nature sets that the default does not: its accent, its typeface, its radius. */
    private const NATURE_DECLARATIONS = [
        '--wp--preset--color--accent: #3f6b45',
        '--wp--preset--font-family--body: "Figtree"',
        '--wp--custom--radius--small: 4px',
        '--wp--preset--shadow--card: none',
    ];

    private ?int $user_styles_id = null;

    protected function tearDown(): void
    {
        if (null !== $this->user_styles_id) {
            wp_delete_post($this->user_styles_id, true);
            $this->user_styles_id = null;
            self::clearCaches();
        }

        wp_set_current_user(0);

        parent::tearDown();
    }

    public function testEveryCustomPropertyThePluginReadsIsDeclared(): void
    {
        $stylesheet = wp_get_global_stylesheet();

        foreach (self::CONTRACT_PROPERTIES as $property) {
            self::assertTrue(str_contains($stylesheet, "{$property}:"), "Missing {$property}");
        }
    }

    public function testTheEditorOffersThePrintShapesInsteadOfWordPresss(): void
    {
        $theme = wp_get_global_settings(['dimensions', 'aspectRatios', 'theme']);
        self::assertIsArray($theme);

        self::assertContains(['name' => 'Portrait - 5:7', 'slug' => '5-7', 'ratio' => '5/7'], $theme);
        self::assertFalse(wp_get_global_settings(['dimensions', 'defaultAspectRatios']));
    }

    public function testTheThemesTypefaceIsServedFromItsOwnFiles(): void
    {
        ob_start();
        wp_print_font_faces();
        $faces = (string) ob_get_clean();

        self::assertStringContainsString('font-family:Inter', str_replace(['"', "'", ' '], '', $faces));
        self::assertStringContainsString(get_theme_file_uri('assets/fonts/inter/inter-latin-400-normal.woff2'), $faces);
    }

    /**
     * Applying a variation in Styles saves its file's contents as the site's
     * own global styles; the stylesheet then carries the variation's values.
     */
    public function testApplyingNatureSwitchesTheSiteToItsValues(): void
    {
        $nature = wp_json_file_decode(get_theme_file_path(self::NATURE_FILE), ['associative' => true]);
        self::assertIsArray($nature);
        self::assertSame(self::NATURE, $nature['title'] ?? null);

        // Styles are saved by an administrator. Signed out, WordPress would
        // neither file the post under the theme nor keep its settings.
        wp_set_current_user(self::administrator());
        $this->user_styles_id = WP_Theme_JSON_Resolver::get_user_global_styles_post_id();
        wp_update_post([
            'ID'           => $this->user_styles_id,
            // wp_update_post() unslashes its input, which would strip the JSON's escapes.
            'post_content' => wp_slash((string) wp_json_encode(['isGlobalStylesUserThemeJSON' => true] + $nature)),
        ]);
        self::clearCaches();

        $stylesheet = wp_get_global_stylesheet();

        foreach (self::NATURE_DECLARATIONS as $declaration) {
            // The stylesheet runs to thousands of characters; name only what is missing.
            self::assertTrue(str_contains($stylesheet, $declaration), "Missing {$declaration}");
        }
    }

    private static function administrator(): int
    {
        $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
        self::assertNotSame([], $administrators, 'The tests site has no administrator.');

        return (int) $administrators[0];
    }

    private static function clearCaches(): void
    {
        WP_Theme_JSON_Resolver::clean_cached_data();
        wp_clean_theme_json_cache();
    }
}
