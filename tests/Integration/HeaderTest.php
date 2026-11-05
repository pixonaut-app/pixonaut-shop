<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WP_Block_Patterns_Registry;

/**
 * The header as WordPress renders it, with WooCommerce's block hooks
 * applied: WooCommerce inserts its account and mini-cart blocks after the
 * navigation of any header part that does not already hold them. Above its
 * row, the `announcement` part a store fills in the Site Editor.
 */
final class HeaderTest extends TestCase
{
    private const ANNOUNCEMENT = 'Free delivery this week';

    /** Where a store's own copy of a theme part is filed: its theme, and its area. */
    private const THEME_TAXONOMY = 'wp_theme';

    private const AREA_TAXONOMY = 'wp_template_part_area';

    /** The class core gives the announcement's ground, from the `highlight` preset. */
    private const HIGHLIGHT_GROUND = 'has-highlight-background-color';

    private const SITE_LOGO = 'wp-block-site-logo';

    private const SITE_TITLE = 'wp-block-site-title';

    /**
     * The classes core gives the open menu's overlay from the navigation
     * block's overlay colours: `base` ground, `contrast` text.
     */
    private const OVERLAY_COLOURS = ['has-base-background-color', 'has-contrast-color'];

    /** The theme mod core keeps the Site Logo's attachment in. */
    private const CUSTOM_LOGO = 'custom_logo';

    /** @var list<int> */
    private array $posts = [];

    protected function tearDown(): void
    {
        foreach ($this->posts as $post) {
            wp_delete_post($post, true);
        }
        $this->posts = [];
        remove_theme_mod(self::CUSTOM_LOGO);
        delete_option('site_logo');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function headerBlocks(): iterable
    {
        yield 'the account link' => ['wp-block-woocommerce-customer-account'];
        yield 'the mini-cart' => ['wp-block-woocommerce-mini-cart '];
    }

    #[DataProvider('headerBlocks')]
    public function testTheHeaderShowsWooCommercesBlockOnce(string $block_class): void
    {
        self::assertSame(1, substr_count(self::header(), $block_class), "The header renders {$block_class} other than once.");
    }

    public function testWithoutAnAnnouncementTheHeaderShowsNoBar(): void
    {
        $header = self::header();

        self::assertStringNotContainsString(self::HIGHLIGHT_GROUND, $header);
        self::assertStringContainsString(self::SITE_TITLE, $header);
    }

    public function testWithAnAnnouncementTheHeaderShowsItAboveItsRow(): void
    {
        $this->announce(self::ANNOUNCEMENT);

        $header = self::header();

        self::assertStringContainsString(self::HIGHLIGHT_GROUND, $header);
        $announcement = strpos($header, self::ANNOUNCEMENT);
        self::assertNotFalse($announcement, 'The header does not show the announcement.');
        self::assertLessThan((int) strpos($header, self::SITE_TITLE), $announcement);
    }

    public function testWithoutASiteLogoTheHeaderShowsTheTitleAlone(): void
    {
        self::assertStringNotContainsString(self::SITE_LOGO, self::header());
    }

    public function testWithASiteLogoTheHeaderShowsItBesideTheTitle(): void
    {
        set_theme_mod(self::CUSTOM_LOGO, $this->logo());

        $header = self::header();

        $logo = strpos($header, self::SITE_LOGO);
        self::assertNotFalse($logo, 'The header does not show the Site Logo.');
        self::assertLessThan((int) strpos($header, self::SITE_TITLE), $logo);
        self::assertMatchesRegularExpression('/class="[^"]*' . self::SITE_LOGO . '[^"]*"[^>]*>.*?<img\b/s', $header);
    }

    public function testTheMenusOverlayIsPaintedFromTheStylesPresets(): void
    {
        $matched = preg_match(
            '/<div\b[^>]*\bclass="(wp-block-navigation__responsive-container\b[^"]*)"/',
            self::header(),
            $container
        );
        self::assertSame(1, $matched, 'The header renders no menu overlay.');

        $classes = preg_split('/\s+/', $container[1]);
        self::assertIsArray($classes);
        foreach (self::OVERLAY_COLOURS as $class) {
            self::assertContains($class, $classes, "The menu overlay lacks {$class}.");
        }
    }

    private static function header(): string
    {
        return do_blocks('<!-- wp:template-part {"slug":"header","theme":"' . get_stylesheet() . '"} /-->');
    }

    /**
     * The store's own copy of the announcement part, holding the theme's
     * pattern with its text, as the Site Editor saves it.
     */
    private function announce(string $text): void
    {
        $pattern = WP_Block_Patterns_Registry::get_instance()->get_registered('pixonaut-shop/announcement');
        self::assertIsArray($pattern, 'The announcement pattern is not registered.');

        $content = (string) preg_replace('/(<p\b[^>]*>)(<\/p>)/', '$1' . $text . '$2', $pattern['content'], 1);

        $id = wp_insert_post([
            'post_type' => 'wp_template_part',
            'post_name' => 'announcement',
            'post_title' => 'Announcement',
            'post_status' => 'publish',
            'post_content' => $content,
        ], true);
        self::assertIsInt($id);
        $this->posts[] = $id;

        wp_set_post_terms($id, [get_stylesheet()], self::THEME_TAXONOMY);
        wp_set_post_terms($id, ['uncategorized'], self::AREA_TAXONOMY);
    }

    /** An image attachment for the Site Logo; core needs only its record, not the file. */
    private function logo(): int
    {
        $id = wp_insert_attachment([
            'post_mime_type' => 'image/png',
            'post_title' => 'Logo',
            'post_status' => 'inherit',
        ], 'logo.png');
        self::assertIsInt($id);
        $this->posts[] = $id;

        return $id;
    }
}
