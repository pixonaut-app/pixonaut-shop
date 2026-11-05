<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Pixonaut\Shop\Blocks\MenuHeading;
use WP_Block_Patterns_Registry;

/**
 * The footer as WordPress renders it: on the `strong` ground, with its
 * menus each headed by the name the store gave the menu.
 */
final class FooterTest extends TestCase
{
    private const MENU_NAME = 'Customer service';

    /** @var list<int> */
    private array $menus = [];

    protected function tearDown(): void
    {
        foreach ($this->menus as $menu) {
            wp_delete_post($menu, true);
        }
        $this->menus = [];
    }

    public function testTheFooterSitsOnTheStrongGround(): void
    {
        $footer = do_blocks('<!-- wp:template-part {"slug":"footer","theme":"' . get_stylesheet() . '"} /-->');

        self::assertStringContainsString('has-strong-background-color', $footer);
        self::assertStringContainsString('has-strong-contrast-color', $footer);
    }

    public function testTheFootersMenusAreHeadedByTheirNames(): void
    {
        $footer = self::footer_pattern();

        self::assertSame(2, substr_count($footer, MenuHeading::CLASS_NAME . '"'), 'The footer has other than two headed menus.');
    }

    public function testAHeadedMenuShowsItsNameAsAHeadingInsideItsNav(): void
    {
        $id = $this->menu(self::MENU_NAME);

        $menu = do_blocks('<!-- wp:navigation {"ref":' . $id . ',"overlayMenu":"never","className":"' . MenuHeading::CLASS_NAME . '"} /-->');

        self::assertMatchesRegularExpression(
            '/<nav\b[^>]*>\s*<h2 class="' . MenuHeading::HEADING_CLASS . '">' . self::MENU_NAME . '<\/h2>/',
            $menu
        );
    }

    public function testAMenuWithoutTheClassShowsNoHeading(): void
    {
        $id = $this->menu(self::MENU_NAME);

        $menu = do_blocks('<!-- wp:navigation {"ref":' . $id . ',"overlayMenu":"never"} /-->');

        self::assertStringNotContainsString(MenuHeading::HEADING_CLASS, $menu);
    }

    private static function footer_pattern(): string
    {
        $pattern = WP_Block_Patterns_Registry::get_instance()->get_registered('pixonaut-shop/footer');
        self::assertIsArray($pattern);

        return (string) $pattern['content'];
    }

    private function menu(string $name): int
    {
        $id = wp_insert_post([
            'post_type' => 'wp_navigation',
            'post_title' => $name,
            'post_status' => 'publish',
            'post_content' => '<!-- wp:navigation-link {"label":"Delivery","url":"/delivery/","kind":"custom"} /-->',
        ], true);
        self::assertIsInt($id);
        $this->menus[] = $id;

        return $id;
    }
}
