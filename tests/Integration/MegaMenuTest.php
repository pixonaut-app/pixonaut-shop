<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Pixonaut\Shop\Header\HeaderVariant;
use Pixonaut\Shop\Header\NamedMenus;
use WP_Error;

/**
 * The header as WordPress renders it with each header variant: the simple
 * header's one row with core's navigation block, and the mega header's menu
 * button with its panel in the navigation block's place, built from the menu
 * saved under the header's name.
 */
final class MegaMenuTest extends TestCase
{
    private const CATEGORY_TAXONOMY = 'product_cat';

    /** The term meta WooCommerce keeps a category's picture in. */
    private const THUMBNAIL_META = 'thumbnail_id';

    /** The mega header's own markup, and the simple header's navigation block. */
    private const MEGA = 'class="pixonaut-shop-mega"';



    private const NAVIGATION = 'wp-block-navigation__container';

    /** @var list<int> */
    private array $posts = [];

    /** @var list<int> */
    private array $terms = [];

    protected function tearDown(): void
    {
        foreach ($this->posts as $post) {
            wp_delete_post($post, true);
        }
        foreach ($this->terms as $term) {
            wp_delete_term($term, self::CATEGORY_TAXONOMY);
        }
        $this->posts = [];
        $this->terms = [];
        delete_option(HeaderVariant::OPTION);
        NamedMenus::forget();
    }

    public function testTheSimpleHeaderShowsCoresMenuInItsRow(): void
    {
        $this->menu();
        update_option(HeaderVariant::OPTION, HeaderVariant::SIMPLE);

        $header = self::header();

        self::assertStringNotContainsString(self::MEGA, $header);
        self::assertStringContainsString(self::NAVIGATION, $header);
        self::assertStringContainsString('>Birthday<', $header);
    }

    public function testAStoreWithoutTheOptionShowsTheSimpleHeader(): void
    {
        $this->menu();

        self::assertStringNotContainsString(self::MEGA, self::header());
    }

    public function testTheMegaHeaderShowsTheMenuButtonAndItsPanelInTheMenusPlace(): void
    {
        $this->menu();
        update_option(HeaderVariant::OPTION, HeaderVariant::MEGA);

        $header = self::header();

        self::assertStringContainsString('<nav ' . self::MEGA, $header);
        self::assertStringNotContainsString(self::NAVIGATION, $header);
        self::assertMatchesRegularExpression('/<nav class="pixonaut-shop-mega" aria-label="Main menu">/', $header);
        self::assertSame(1, preg_match('/<button type="button" class="pixonaut-shop-mega__toggle" popovertarget="([^"]+)" aria-controls="\1"/', $header, $toggle));
        self::assertStringContainsString('<div id="' . $toggle[1] . '" class="pixonaut-shop-mega__panel" popover>', $header);
        // Inside the header's row, where the menu was, with the search after it.
        self::assertMatchesRegularExpression('/pixonaut-shop-header__row.*' . preg_quote(self::MEGA, '/') . '.*wp-block-search/s', $header);
    }

    public function testTheGroupsAreTheMenusTopItemsAndTheirCategoriesAreTiles(): void
    {
        [$birthday] = $this->menu();
        update_option(HeaderVariant::OPTION, HeaderVariant::MEGA);

        $header = self::header();

        preg_match_all('/<button type="button" class="pixonaut-shop-mega__group"[^>]*>([^<]*)</', $header, $groups);
        self::assertSame(['Occasion', 'Customer service'], $groups[1]);

        $link = get_term_link($birthday, self::CATEGORY_TAXONOMY);
        self::assertIsString($link);
        self::assertMatchesRegularExpression(
            '/<a class="pixonaut-shop-mega__tile" href="' . preg_quote(esc_url($link), '/') . '"><span class="pixonaut-shop-mega__picture"><img\b[^>]*\bloading="lazy"[^>]*><\/span><span class="pixonaut-shop-mega__name">Birthday<\/span><\/a>/',
            $header
        );
        self::assertMatchesRegularExpression('/<span class="pixonaut-shop-mega__picture is-empty"><\/span><span class="pixonaut-shop-mega__name">Wedding<\/span>/', $header);
        self::assertStringContainsString('<ul class="pixonaut-shop-mega__links"><li><a href="/contact/">Contact</a></li></ul>', $header);
        self::assertStringContainsString('<a class="pixonaut-shop-mega__group" href="/sale/">Sale</a>', $header);
    }

    /** The categories' pictures are loaded together, not one query each. */
    public function testThePicturesAreLoadedTogether(): void
    {
        [, , $picture] = $this->menu();
        update_option(HeaderVariant::OPTION, HeaderVariant::MEGA);
        wp_cache_flush();
        $queries = [];
        $record  = static function (string $query) use (&$queries): string {
            $queries[] = $query;

            return $query;
        };
        add_filter('query', $record);

        try {
            self::header();
        } finally {
            remove_filter('query', $record);
        }

        $alone = array_filter($queries, static fn (string $query): bool => 1 === preg_match('/\bID = ' . $picture . ' LIMIT 1/', $query));
        self::assertSame([], array_values($alone), 'The picture was loaded on its own.');
        self::assertNotEmpty(array_filter($queries, static fn (string $query): bool => 1 === preg_match('/\bID IN \([^)]*\b' . $picture . '\b/', $query)));
    }

    public function testTheMegaHeaderWithoutANamedMenuKeepsCoresMenu(): void
    {
        update_option(HeaderVariant::OPTION, HeaderVariant::MEGA);

        $header = self::header();

        self::assertStringNotContainsString(self::MEGA, $header);
    }

    public function testOnlyTheHeadersMenuBecomesThePanel(): void
    {
        $this->menu();
        update_option(HeaderVariant::OPTION, HeaderVariant::MEGA);

        self::assertStringNotContainsString(self::MEGA, do_blocks('<!-- wp:template-part {"slug":"footer","theme":"' . get_stylesheet() . '"} /-->'));
    }

    private static function header(): string
    {
        return do_blocks('<!-- wp:template-part {"slug":"header","theme":"' . get_stylesheet() . '"} /-->');
    }

    /**
     * The menu saved under the header's name: a group of two categories,
     * one with a picture, a plain link, and a group of plain links.
     *
     * @return array{int, int, int} The two categories, and the first one's picture.
     */
    private function menu(): array
    {
        $picture  = $this->image();
        $birthday = $this->category('Birthday', $picture);
        $wedding  = $this->category('Wedding', 0);

        $id = wp_insert_post([
            'post_type' => 'wp_navigation',
            'post_name' => NamedMenus::HEADER,
            'post_title' => 'Header',
            'post_status' => 'publish',
            'post_content' => '<!-- wp:navigation-submenu {"label":"Occasion","url":"#","kind":"custom"} -->'
                . self::category_link('Birthday', $birthday)
                . self::category_link('Wedding', $wedding)
                . '<!-- /wp:navigation-submenu -->'
                . '<!-- wp:navigation-link {"label":"Sale","url":"/sale/","kind":"custom"} /-->'
                . '<!-- wp:navigation-submenu {"label":"Customer service","url":"#","kind":"custom"} -->'
                . '<!-- wp:navigation-link {"label":"Contact","url":"/contact/","kind":"custom"} /-->'
                . '<!-- /wp:navigation-submenu -->',
        ], true);
        self::assertIsInt($id);
        $this->posts[] = $id;
        NamedMenus::forget();

        return [$birthday, $wedding, $picture];
    }

    private static function category_link(string $label, int $term): string
    {
        return '<!-- wp:navigation-link {"label":"' . $label . '","type":"product_cat","id":' . $term . ',"url":"/old/","kind":"taxonomy"} /-->';
    }

    /** A product category, with the picture given, or none for 0. */
    private function category(string $name, int $image): int
    {
        $term = wp_insert_term($name . ' ' . uniqid(), self::CATEGORY_TAXONOMY);
        self::assertNotInstanceOf(WP_Error::class, $term);
        $this->terms[] = (int) $term['term_id'];

        if (0 !== $image) {
            update_term_meta((int) $term['term_id'], self::THUMBNAIL_META, $image);
        }

        return (int) $term['term_id'];
    }

    /** An image in the media library; its file is never read for the markup. */
    private function image(): int
    {
        $id = wp_insert_attachment(['post_mime_type' => 'image/png', 'post_title' => 'Birthday'], 'birthday.png');
        self::assertIsInt($id);
        $this->posts[] = $id;

        return $id;
    }
}
