<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Pixonaut\Shop\Blocks\MenuHeading;
use Pixonaut\Shop\Header\NamedMenus;

/**
 * The header's and footer's menus as WordPress renders them, each showing the
 * navigation menu saved under its own name, where the store has one. Without
 * it, core's fallback shows the newest menu in every navigation block.
 */
final class NamedMenusTest extends TestCase
{
    /** The class each menu's navigation block carries in the header and footer. */
    private const HEADER_MENU_CLASS = 'pixonaut-shop-header__menu';

    private const FOOTER_MENU_CLASS = 'pixonaut-shop-footer__links';

    private const SERVICE_MENU_CLASS = 'pixonaut-shop-footer__service';

    private const LEGAL_MENU_CLASS = 'pixonaut-shop-footer__legal';

    /**
     * A post date that puts the named menus before the one core falls back
     * to, which is saved now, after any menu core created for its own
     * fallback on this site.
     */
    private const OLDER = '2020-01-01 00:00:00';

    /** @var list<int> */
    private array $menus = [];

    protected function tearDown(): void
    {
        foreach ($this->menus as $menu) {
            wp_delete_post($menu, true);
        }
        $this->menus = [];
    }

    public function testTheHeaderMenuRendersTheNavigationPostNamedForIt(): void
    {
        $this->menu(NamedMenus::HEADER, 'Shop', self::OLDER);
        $this->menu('', 'Newest', null);

        $menu = self::nav(self::header(), self::HEADER_MENU_CLASS);

        self::assertStringContainsString('>Shop<', $menu);
        self::assertStringNotContainsString('>Newest<', $menu);
    }

    public function testEachOfTheFourMenusRendersItsOwnNavigationPost(): void
    {
        $this->menu(NamedMenus::HEADER, 'Shop', self::OLDER);
        $this->menu(NamedMenus::FOOTER, 'Delivery', self::OLDER, 'Customer service');
        $this->menu(NamedMenus::SERVICE, 'Contact', self::OLDER, 'Help');
        $this->menu(NamedMenus::LEGAL, 'Terms', self::OLDER);
        $this->menu('', 'Newest', null);

        $footer = self::footer();
        $menus = [
            'Shop' => self::nav(self::header(), self::HEADER_MENU_CLASS),
            'Delivery' => self::nav($footer, self::FOOTER_MENU_CLASS),
            'Contact' => self::nav($footer, self::SERVICE_MENU_CLASS),
            'Terms' => self::nav($footer, self::LEGAL_MENU_CLASS),
        ];

        foreach ($menus as $label => $menu) {
            foreach (array_keys($menus) as $other) {
                $expected = $other === $label;
                self::assertSame($expected, str_contains($menu, ">{$other}<"), "The menu showing {$label} " . ($expected ? 'lacks' : 'shows') . " {$other}.");
            }
            self::assertStringNotContainsString('>Newest<', $menu);
        }
        self::assertStringContainsString('<h2 class="' . MenuHeading::HEADING_CLASS . '">Customer service</h2>', $menus['Delivery']);
        self::assertStringContainsString('<h2 class="' . MenuHeading::HEADING_CLASS . '">Help</h2>', $menus['Contact']);
    }

    public function testADraftMenuSavedUnderTheNameIsIgnored(): void
    {
        $this->menu(NamedMenus::HEADER, 'Draft', self::OLDER, 'Menu', 'draft');
        $this->menu('', 'Newest', null);

        $menu = self::nav(self::header(), self::HEADER_MENU_CLASS);

        self::assertStringContainsString('>Newest<', $menu);
        self::assertStringNotContainsString('>Draft<', $menu);
    }

    public function testAMissingNamedMenuFallsBackAsBefore(): void
    {
        $this->menu(NamedMenus::FOOTER, 'Delivery', self::OLDER);
        $this->menu('', 'Newest', null);

        self::assertStringContainsString('>Newest<', self::nav(self::header(), self::HEADER_MENU_CLASS));
        self::assertStringContainsString('>Newest<', self::nav(self::footer(), self::LEGAL_MENU_CLASS));
    }

    public function testAMenuTheStorePickedInTheSiteEditorIsKept(): void
    {
        $this->menu(NamedMenus::HEADER, 'Shop', self::OLDER);
        $picked = $this->menu('', 'Picked', self::OLDER);

        $menu = do_blocks('<!-- wp:navigation {"ref":' . $picked . ',"className":"' . self::HEADER_MENU_CLASS . '"} /-->');

        self::assertStringContainsString('>Picked<', $menu);
        self::assertStringNotContainsString('>Shop<', $menu);
    }

    private static function header(): string
    {
        return do_blocks('<!-- wp:template-part {"slug":"header","theme":"' . get_stylesheet() . '"} /-->');
    }

    private static function footer(): string
    {
        return do_blocks('<!-- wp:template-part {"slug":"footer","theme":"' . get_stylesheet() . '"} /-->');
    }

    /** The `<nav>` that carries the class, and everything inside it. */
    private static function nav(string $html, string $marker): string
    {
        $matched = preg_match(
            '/<nav\b[^>]*\bclass="[^"]*\b' . preg_quote($marker, '/') . '\b[^"]*"[^>]*>.*?<\/nav>/s',
            $html,
            $nav
        );
        self::assertSame(1, $matched, "Nothing rendered a menu with the class {$marker}.");

        return $nav[0];
    }

    /** A navigation menu holding one link, under the name given, or core's own, saved at the date given or now. */
    private function menu(string $slug, string $label, ?string $date, string $title = 'Menu', string $status = 'publish'): int
    {
        $post = [
            'post_type' => 'wp_navigation',
            'post_title' => $title,
            'post_status' => $status,
            'post_date' => $date ?? current_time('mysql'),
            'post_content' => '<!-- wp:navigation-link {"label":"' . $label . '","url":"/' . strtolower($label) . '/","kind":"custom"} /-->',
        ];
        if ('' !== $slug) {
            $post['post_name'] = $slug;
        }

        $id = wp_insert_post($post, true);
        self::assertIsInt($id);
        $this->menus[] = $id;

        return $id;
    }
}
