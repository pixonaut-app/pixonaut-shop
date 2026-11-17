<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Pixonaut\Shop\Product\PopularProducts;
use WC_Product;
use WC_Product_Simple;
use WP_Block_Patterns_Registry;

/**
 * The popular products: the best-selling a customer can buy now, which the
 * header search shows and the popular-products pattern lists alike.
 */
final class PopularProductsTest extends TestCase
{
    private const PATTERN = 'pixonaut-shop/popular-products';

    /** More sales than any other product on the tests site has. */
    private const MANY_SALES = 1_000_000;

    /** @var list<int> */
    private array $product_ids = [];

    protected function tearDown(): void
    {
        foreach ($this->product_ids as $id) {
            wp_delete_post($id, true);
        }

        parent::tearDown();
    }

    public function testTheMostSoldThatCanBeBoughtComeFirst(): void
    {
        $best = $this->product('Best seller', self::MANY_SALES, 'instock');
        $second = $this->product('Second seller', self::MANY_SALES - 1, 'onbackorder');
        $sold_out = $this->product('Sold-out seller', self::MANY_SALES + 1, 'outofstock');
        $hidden = $this->product('Hidden seller', self::MANY_SALES + 1, 'instock', 'hidden');

        $ids = array_map(static fn (WC_Product $product): int => $product->get_id(), PopularProducts::products());

        self::assertLessThanOrEqual(PopularProducts::COUNT, \count($ids));
        self::assertSame([$best, $second], \array_slice($ids, 0, 2));
        self::assertNotContains($sold_out, $ids);
        self::assertNotContains($hidden, $ids);
    }

    public function testThePatternListsTheSameProducts(): void
    {
        $this->product('Best seller', self::MANY_SALES, 'instock');
        $this->product('Sold-out seller', self::MANY_SALES + 1, 'outofstock');

        $pattern = WP_Block_Patterns_Registry::get_instance()->get_registered(self::PATTERN);
        self::assertIsArray($pattern);
        $html = do_blocks($pattern['content']);

        self::assertStringContainsString('Best seller', $html);
        self::assertStringNotContainsString('Sold-out seller', $html);
    }

    private function product(string $name, int $sales, string $stock, string $visibility = 'visible'): int
    {
        $product = new WC_Product_Simple();
        $product->set_name($name);
        $product->set_regular_price('100');
        $product->set_status('publish');
        $product->set_stock_status($stock);
        $product->set_catalog_visibility($visibility);
        $product->set_total_sales($sales);
        $id = $product->save();
        $this->product_ids[] = $id;

        return $id;
    }
}
