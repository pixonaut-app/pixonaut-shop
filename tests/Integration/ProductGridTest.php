<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use PHPUnit\Framework\TestCase;
use WC_Product_Simple;
use WP_Block_Patterns_Registry;
use WP_Query;

/**
 * The product grid as WooCommerce renders it: a card per product with its
 * price and, when it is on sale, the sale badge, and no add-to-cart button,
 * since every product is personalised on its own page.
 */
final class ProductGridTest extends TestCase
{
    private const PATTERN = 'pixonaut-shop/product-grid';

    private const REGULAR_PRICE = '300';

    private const SALE_PRICE = '200';

    /** The class WooCommerce's product-button block and its classic button print. */
    private const ADD_TO_CART = 'add_to_cart_button';

    private const SALE_BADGE = 'wc-block-components-product-sale-badge';

    /** @var list<int> */
    private array $product_ids = [];

    protected function tearDown(): void
    {
        foreach ($this->product_ids as $id) {
            wp_delete_post($id, true);
        }

        // The main query is the one object a request has; empty it again.
        $GLOBALS['wp_query']->init();
        wp_reset_postdata();
        parent::tearDown();
    }

    public function testShowsTheCardsPriceWithoutAnAddToCartButton(): void
    {
        $this->product('Grid product', self::REGULAR_PRICE);

        $grid = $this->render();

        self::assertStringContainsString('Grid product', $grid);
        self::assertStringContainsString('wc-block-components-product-price', $grid);
        self::assertStringContainsString(self::REGULAR_PRICE, $grid);
        self::assertStringNotContainsString(self::ADD_TO_CART, $grid);
        self::assertStringNotContainsString('wp-block-woocommerce-product-button', $grid);
    }

    public function testPutsTheSaleBadgeOnTheImageOfAProductOnSale(): void
    {
        $this->product('Sale product', self::REGULAR_PRICE, self::SALE_PRICE);

        $grid = $this->render();

        self::assertMatchesRegularExpression(
            '/wc-block-components-product-image\b.*?' . self::SALE_BADGE . '/s',
            $grid,
            'The sale badge is not inside the product image.'
        );
        self::assertStringContainsString(self::SALE_PRICE, $grid);
    }

    public function testShowsNoSaleBadgeOnAProductAtItsRegularPrice(): void
    {
        $this->product('Regular product', self::REGULAR_PRICE);

        self::assertStringNotContainsString(self::SALE_BADGE . ' ', $this->render());
    }

    private function product(string $name, string $regular_price, ?string $sale_price = null): void
    {
        $product = new WC_Product_Simple();
        $product->set_name($name);
        $product->set_regular_price($regular_price);

        if (null !== $sale_price) {
            $product->set_sale_price($sale_price);
        }

        $product->set_status('publish');
        $this->product_ids[] = $product->save();
    }

    /**
     * The pattern inherits the query of the template it is in, as on the
     * shop page: here, a query for the products this test made.
     */
    private function render(): string
    {
        $main_query = $GLOBALS['wp_query'];
        self::assertInstanceOf(WP_Query::class, $main_query);
        $main_query->query([
            'post_type' => 'product',
            'post__in'  => $this->product_ids,
        ]);

        $pattern = WP_Block_Patterns_Registry::get_instance()->get_registered(self::PATTERN);
        self::assertIsArray($pattern, self::PATTERN . ' is not registered.');

        return do_blocks($pattern['content']);
    }
}
