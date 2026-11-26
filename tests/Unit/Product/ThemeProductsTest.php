<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Product;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use Pixonaut\Shop\Product\ThemeProducts;
use Pixonaut\Shop\Product\ThemeProductsShown;
use Pixonaut\Shop\Tests\TestCase;

/**
 * Which tag a product's "More in the theme" section is for, and what it
 * leaves alone.
 */
#[CoversClass(ThemeProducts::class)]
#[CoversClass(ThemeProductsShown::class)]
final class ThemeProductsTest extends TestCase
{
    private const PRODUCT = 7;

    private const SECTION = ['blockName' => 'core/group', 'attrs' => ['className' => 'alignwide ' . ThemeProducts::CLASS_NAME]];

    public function testRegistersAroundTheSectionAndOnTheCollectionsQuery(): void
    {
        (new ThemeProducts())->register();

        self::assertNotFalse(has_filter('pre_render_block', 'Pixonaut\Shop\Product\ThemeProducts->skip()'));
        self::assertNotFalse(has_filter('render_block_data', 'Pixonaut\Shop\Product\ThemeProducts->start()'));
        self::assertSame(20, has_filter('query_loop_block_query_vars', 'Pixonaut\Shop\Product\ThemeProducts->narrow()'), 'After WooCommerce builds the collection\'s query.');
        self::assertNotFalse(has_filter('render_block_core/group', 'Pixonaut\Shop\Product\ThemeProducts->render()'));
    }

    public function testNamesTheTagInTheHeading(): void
    {
        Functions\stubTranslationFunctions();

        self::assertSame('More in the theme Sloths', ThemeProducts::title('Sloths'));
    }

    public function testPicksTheFirstTagByNameThenByID(): void
    {
        $shown = ThemeProducts::first_tag(self::PRODUCT, [
            $this->tag(30, 'sloths'),
            $this->tag(21, 'Dinosaurs'),
            $this->tag(12, 'dinosaurs'),
            'not a tag',
        ]);

        self::assertNotNull($shown);
        self::assertSame(self::PRODUCT, $shown->product_id);
        self::assertSame(12, $shown->tag_id, 'Two tags of the same name, but for case: the older first.');
        self::assertSame('dinosaurs', $shown->tag_name);
    }

    public function testFindsNoTagForAProductWithout(): void
    {
        self::assertNull(ThemeProducts::first_tag(self::PRODUCT, []));
    }

    public function testLeavesEveryOtherBlockAlone(): void
    {
        $products = new ThemeProducts();
        $group = ['blockName' => 'core/group', 'attrs' => ['className' => 'pixonaut-shop-theme-products-extra']];
        $paragraph = ['blockName' => 'core/paragraph', 'attrs' => ['className' => ThemeProducts::CLASS_NAME]];

        self::assertNull($products->skip(null, $group));
        self::assertNull($products->skip(null, $paragraph));
        self::assertSame('<p>Shown</p>', $products->skip('<p>Shown</p>', self::SECTION), 'Another filter already rendered it.');
        self::assertSame($group, $products->start($group));
        self::assertSame('<div>Group</div>', $products->render('<div>Group</div>', $group));
    }

    public function testLeavesEveryQueryAloneOutsideTheSection(): void
    {
        $query = ['post_type' => 'product'];

        self::assertSame($query, (new ThemeProducts())->narrow($query, $this->collection_block()));
    }

    public function testSkipsTheSectionOffAProductsPage(): void
    {
        Functions\when('wc_get_products')->justReturn([]);
        Functions\when('get_queried_object')->justReturn(null);

        self::assertSame('', (new ThemeProducts())->skip(null, self::SECTION));
    }

    public function testDropsTheSectionWhenItRendersWithoutHavingBeenFound(): void
    {
        $markup = '<section class="' . ThemeProducts::CLASS_NAME . '"><div class="wp-block-woocommerce-product-collection"></div></section>';

        self::assertSame('', (new ThemeProducts())->render($markup, self::SECTION));
    }

    private function tag(int $id, string $name): object
    {
        return (object) ['term_id' => $id, 'name' => $name];
    }

    private function collection_block(): object
    {
        return (object) ['context' => ['query' => ['isProductCollectionBlock' => true]]];
    }
}
