<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Header;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Pixonaut\Shop\Header\NamedMenus;
use Pixonaut\Shop\Tests\TestCase;

#[CoversClass(NamedMenus::class)]
final class NamedMenusTest extends TestCase
{
    /** A wp_navigation post's ID, as a navigation block's `ref` holds it. */
    private const MENU_ID = 42;

    /** A menu the store picked for the block in the Site Editor. */
    private const PICKED_MENU_ID = 7;

    public function testRegistersOnTheParsedBlockBeforeItRenders(): void
    {
        (new NamedMenus())->register();

        self::assertNotFalse(has_filter('render_block_data', 'Pixonaut\Shop\Header\NamedMenus->name_menu()'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function menus(): iterable
    {
        yield 'the header menu' => ['pixonaut-shop-header__menu', 'pixonaut-shop-header'];
        yield 'the footer links' => ['pixonaut-shop-footer__links', 'pixonaut-shop-footer'];
        yield 'the legal links' => ['pixonaut-shop-footer__legal', 'pixonaut-shop-legal'];
    }

    #[DataProvider('menus')]
    public function testPointsAMarkedMenuAtThePublishedMenuSavedUnderItsName(string $marker, string $name): void
    {
        $this->saved_menus([$name => self::MENU_ID]);

        $block = (new NamedMenus())->name_menu(self::block($marker . ' pixonaut-shop-headed-menu'));

        self::assertSame(self::MENU_ID, $block['attrs']['ref']);
        self::assertSame($marker . ' pixonaut-shop-headed-menu', $block['attrs']['className']);
    }

    public function testLeavesAMarkedMenuWithoutASavedMenuToCoresFallback(): void
    {
        $this->saved_menus([]);
        $block = self::block('pixonaut-shop-header__menu');

        self::assertSame($block, (new NamedMenus())->name_menu($block));
    }

    public function testKeepsTheMenuTheStorePicked(): void
    {
        Functions\expect('get_posts')->never();
        $block = self::block('pixonaut-shop-header__menu');
        $block['attrs']['ref'] = self::PICKED_MENU_ID;

        self::assertSame($block, (new NamedMenus())->name_menu($block));
    }

    public function testLeavesAMenuWithoutAMarkerAsItIs(): void
    {
        Functions\expect('get_posts')->never();
        $block = self::block('pixonaut-shop-headed-menu');

        self::assertSame($block, (new NamedMenus())->name_menu($block));
    }

    public function testLeavesANavigationBlockWithoutAttributesAsItIs(): void
    {
        Functions\expect('get_posts')->never();
        $block = ['blockName' => 'core/navigation', 'attrs' => []];

        self::assertSame($block, (new NamedMenus())->name_menu($block));
    }

    public function testLeavesOtherBlocksAsTheyAre(): void
    {
        Functions\expect('get_posts')->never();
        $block = self::block('pixonaut-shop-header__menu');
        $block['blockName'] = 'core/group';

        self::assertSame($block, (new NamedMenus())->name_menu($block));
    }

    public function testAsksForThePublishedNavigationMenuByItsName(): void
    {
        Functions\expect('get_posts')->once()->andReturnUsing(static function (array $query): array {
            self::assertSame('wp_navigation', $query['post_type']);
            self::assertSame('publish', $query['post_status']);
            self::assertSame('pixonaut-shop-legal', $query['name']);
            self::assertSame(1, $query['numberposts']);
            self::assertSame('ids', $query['fields']);

            return [self::MENU_ID];
        });

        (new NamedMenus())->name_menu(self::block('pixonaut-shop-footer__legal'));
    }

    /**
     * Answers `get_posts` from the menus saved under each name.
     *
     * @param array<string, int> $menus
     */
    private function saved_menus(array $menus): void
    {
        Functions\when('get_posts')->alias(
            static fn (array $query): array => isset($menus[$query['name']]) ? [$menus[$query['name']]] : []
        );
    }

    /**
     * @return array{blockName: string, attrs: array<string, mixed>}
     */
    private static function block(string $class_name): array
    {
        return [
            'blockName' => 'core/navigation',
            'attrs' => ['overlayMenu' => 'never', 'className' => $class_name],
        ];
    }
}
