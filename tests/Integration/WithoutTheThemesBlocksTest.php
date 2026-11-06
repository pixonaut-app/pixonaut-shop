<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use PHPUnit\Framework\TestCase;
use WP_Block_Patterns_Registry;
use WP_Block_Type;
use WP_Block_Type_Registry;

/**
 * The patterns and parts that hold the theme's own blocks, on a site where
 * those blocks are not registered: the wordpress.org build, which ships
 * without them. Each pattern then holds no reference to a block the site
 * does not have: the categories fall back to WooCommerce's own block, and
 * the icons and the free-shipping note are left out.
 */
final class WithoutTheThemesBlocksTest extends TestCase
{
    private const BLOCKS = ['pixonaut-shop/icon', 'pixonaut-shop/product-categories', 'pixonaut-shop/free-shipping-note'];

    /** The patterns that print one of the theme's blocks when it is registered. */
    private const PATTERNS = [
        'pixonaut-shop/front-page',
        'pixonaut-shop/not-found',
        'pixonaut-shop/search-no-results',
        'pixonaut-shop/promises',
        'pixonaut-shop/mini-cart-empty-icon',
        'pixonaut-shop/free-shipping-note',
    ];

    /** The patterns whose categories fall back to WooCommerce's block. */
    private const CATEGORY_PATTERNS = ['pixonaut-shop/front-page', 'pixonaut-shop/not-found', 'pixonaut-shop/search-no-results'];

    private const WOOCOMMERCE_CATEGORIES = '<!-- wp:woocommerce/product-categories ';

    /** @var list<WP_Block_Type> */
    private array $unregistered = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::BLOCKS as $name) {
            $block = WP_Block_Type_Registry::get_instance()->unregister($name);
            self::assertInstanceOf(WP_Block_Type::class, $block, "{$name} was not registered.");
            $this->unregistered[] = $block;
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->unregistered as $block) {
            WP_Block_Type_Registry::get_instance()->register($block);
        }

        parent::tearDown();
    }

    public function testNoPatternNamesABlockTheSiteDoesNotHave(): void
    {
        foreach (self::PATTERNS as $slug) {
            self::assertStringNotContainsString('<!-- wp:pixonaut-shop/', $this->pattern($slug), "{$slug} names a missing block.");
        }
    }

    public function testTheCategoriesFallBackToWooCommercesBlock(): void
    {
        foreach (self::CATEGORY_PATTERNS as $slug) {
            self::assertStringContainsString(self::WOOCOMMERCE_CATEGORIES, $this->pattern($slug), $slug);
        }
    }

    public function testTheMiniCartRendersWithoutTheNoteOrTheIcon(): void
    {
        $part = (string) file_get_contents(get_theme_file_path('parts/mini-cart.html'));

        self::assertStringNotContainsString('<!-- wp:pixonaut-shop/', $part);
        self::assertStringNotContainsString('pixonaut-shop-free-shipping-note', do_blocks($part));
    }

    /**
     * The pattern's markup as its file prints it now. Not the registry's
     * copy, which keeps what the file printed the first time a request read
     * it, while the blocks were registered.
     */
    private function pattern(string $slug): string
    {
        self::assertIsArray(WP_Block_Patterns_Registry::get_instance()->get_registered($slug), "{$slug} is not registered.");

        ob_start();
        include get_theme_file_path('patterns/' . substr($slug, \strlen('pixonaut-shop/')) . '.php');

        return (string) ob_get_clean();
    }
}
