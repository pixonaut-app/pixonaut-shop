<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Pixonaut\Shop\Fonts\FallbackFaces;
use Pixonaut\Shop\Fonts\TitleFontPreload;
use WP_Theme_JSON_Resolver;

/**
 * A typeface a store picks in its Styles, from the Font Library, holds its
 * room while it loads like the theme's own: its stack names metric-matched
 * fallbacks, their faces are printed, and the header asks for the title's
 * face first.
 */
final class FontFallbacksTest extends TestCase
{
    private const UPLOADS = 'https://example.test/wp-content/uploads/fonts/';

    private const STORE_STACK = '"Nunito", system-ui, sans-serif';

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

    public function testAStoresTypefaceNamesItsFallbacksInTheStylesheet(): void
    {
        $this->saveStoreTypeface();

        self::assertStringContainsString(
            '--wp--preset--font-family--body: "Nunito", "Nunito Fallback Arial", "Nunito Fallback Roboto", system-ui, sans-serif;',
            wp_get_global_stylesheet()
        );
    }

    public function testTheFallbackFacesArePrintedWithTheThemesStylesheet(): void
    {
        $this->saveStoreTypeface();

        wp_enqueue_style('pixonaut-shop', 'https://example.test/app.css', [], '1');
        (new FallbackFaces())->enqueue();

        $inline = implode('', (array) wp_styles()->get_data('pixonaut-shop', 'after'));

        self::assertStringContainsString('font-family:"Nunito Fallback Arial";font-style:normal;font-weight:800;src:local("Arial Bold")', $inline);
        self::assertStringContainsString('font-family:"Nunito Fallback Roboto";font-style:normal;font-weight:400;src:local("Roboto")', $inline);
    }

    public function testTheTitlesFaceIsPreloaded(): void
    {
        $this->saveStoreTypeface();

        ob_start();
        (new TitleFontPreload())->print_link();
        $head = (string) ob_get_clean();

        // The theme draws the title at 700; of 400 and 800 the browser takes 800.
        self::assertSame('<link rel="preload" href="' . self::UPLOADS . 'nunito-latin-800-normal.woff2" as="font" type="font/woff2" crossorigin>' . "\n", $head);
    }

    public function testTheThemesOwnTypefaceIsPreloadedForTheTitle(): void
    {
        ob_start();
        (new TitleFontPreload())->print_link();
        $head = (string) ob_get_clean();

        self::assertStringContainsString(get_theme_file_uri('assets/fonts/inter/inter-latin-700-normal.woff2'), $head);
    }

    /**
     * The body family as a store's Styles save it after installing Nunito
     * from the Font Library: the theme's slug, the store's stack and faces.
     */
    private function saveStoreTypeface(): void
    {
        $faces = [];

        foreach (['400', '800'] as $weight) {
            $faces[] = [
                'fontFamily' => 'Nunito',
                'fontStyle'  => 'normal',
                'fontWeight' => $weight,
                'fontDisplay' => 'swap',
                'src'        => [self::UPLOADS . 'nunito-latin-' . $weight . '-normal.woff2'],
                'unicodeRange' => 'U+0000-00FF, U+2000-206F',
            ];
        }

        $styles = [
            'isGlobalStylesUserThemeJSON' => true,
            'version'                     => 3,
            'settings'                    => [
                'typography' => [
                    'fontFamilies' => [
                        ['slug' => 'body', 'name' => 'Nunito', 'fontFamily' => self::STORE_STACK, 'fontFace' => $faces],
                    ],
                ],
            ],
        ];

        // Styles are saved by an administrator; signed out, WordPress keeps no settings.
        wp_set_current_user(self::administrator());
        $this->user_styles_id = WP_Theme_JSON_Resolver::get_user_global_styles_post_id();
        wp_update_post([
            'ID'           => $this->user_styles_id,
            // wp_update_post() unslashes its input, which would strip the JSON's escapes.
            'post_content' => wp_slash((string) wp_json_encode($styles)),
        ]);
        self::clearCaches();
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
