<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Pixonaut\Shop\Cart\EmptyCart;
use WC_Product_Simple;
use WP_Block_Patterns_Registry;

/**
 * WooCommerce's own empty cart, as a store's Cart page saves it, rendered as
 * the theme's: the message in a panel, nothing between it and what follows,
 * and the newest products as the product grid's cards.
 */
final class EmptyCartTest extends TestCase
{
    /** The empty cart a Cart page made by a current WooCommerce holds. */
    private const BY_PATTERN = <<<'HTML'
<!-- wp:woocommerce/empty-cart-block -->
<div class="wp-block-woocommerce-empty-cart-block"><!-- wp:pattern {"slug":"woocommerce/cart-empty-message"} /-->

<!-- wp:spacer {"height":"40px"} -->
<div style="height:40px" aria-hidden="true" class="wp-block-spacer"></div>
<!-- /wp:spacer -->

<!-- wp:pattern {"slug":"woocommerce/cart-new-in-store-message"} /-->

<!-- wp:woocommerce/product-new {"columns":4,"rows":1} /--></div>
<!-- /wp:woocommerce/empty-cart-block -->
HTML;

    /** The empty cart a Cart page made by an older WooCommerce holds, its blocks in place. */
    private const IN_PLACE = <<<'HTML'
<!-- wp:woocommerce/empty-cart-block -->
<div class="wp-block-woocommerce-empty-cart-block"><!-- wp:heading {"textAlign":"center","className":"wc-block-cart__empty-cart__title"} -->
<h2 class="wp-block-heading has-text-align-center wc-block-cart__empty-cart__title">Your cart is empty</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center"><a href="/shop/">Browse store</a></p>
<!-- /wp:paragraph -->

<!-- wp:separator {"className":"is-style-dots"} -->
<hr class="wp-block-separator has-alpha-channel-opacity is-style-dots"/>
<!-- /wp:separator -->

<!-- wp:heading {"textAlign":"center"} -->
<h2 class="wp-block-heading has-text-align-center">New in store</h2>
<!-- /wp:heading -->

<!-- wp:woocommerce/product-new {"columns":2,"rows":1} /--></div>
<!-- /wp:woocommerce/empty-cart-block -->
HTML;

    private const CART_PATTERN = 'pixonaut-shop/cart';

    /** The class of the product grid's card list. */
    private const CARDS = 'wc-block-product-template';

    /** The class WooCommerce's legacy Newest Products block prints. */
    private const LEGACY_GRID = 'wp-block-woocommerce-product-new';

    /** @var list<int> */
    private array $product_ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->product('Newest card product');
    }

    protected function tearDown(): void
    {
        foreach ($this->product_ids as $id) {
            wp_delete_post($id, true);
        }

        parent::tearDown();
    }

    public function testTheMessageIsInOnePanelWithNothingAfterItButWhatFollows(): void
    {
        $html = do_blocks(self::BY_PATTERN);

        self::assertMatchesRegularExpression(
            '/<div class="wp-block-group ' . preg_quote(EmptyCart::PANEL_CLASSES, '/') . '[^"]*">\s*'
            . '<h2 [^>]*' . EmptyCart::TITLE_CLASS . '[^>]*>.*?<\/h2>\s*'
            . '<div class="wp-block-buttons[^"]*">.*?<\/div>\s*<\/div>\s*<\/div>/s',
            $html
        );
        self::assertStringNotContainsString('wp-block-spacer', $html);
        self::assertStringContainsString('New in store', $html);
    }

    public function testTheNewestProductsAreTheProductGridsCards(): void
    {
        $html = do_blocks(self::BY_PATTERN);

        self::assertStringContainsString(self::CARDS, $html);
        self::assertStringContainsString('Newest card product', $html);
        self::assertStringNotContainsString(self::LEGACY_GRID, $html);
        self::assertStringNotContainsString('add_to_cart_button', $html);
    }

    public function testAnOlderPagesMessageAndProductsAreDrawnTheSame(): void
    {
        $html = do_blocks(self::IN_PLACE);

        self::assertMatchesRegularExpression(
            '/<div class="wp-block-group ' . preg_quote(EmptyCart::PANEL_CLASSES, '/') . '[^"]*">\s*'
            . '<h2 [^>]*' . EmptyCart::TITLE_CLASS . '[^>]*>.*?<\/h2>\s*<p [^>]*><a href="\/shop\/">Browse store<\/a><\/p>\s*<\/div>/s',
            $html
        );
        self::assertStringNotContainsString('wp-block-separator', $html);
        self::assertStringContainsString(self::CARDS, $html);
        self::assertStringContainsString('columns-2', $html);
        self::assertStringNotContainsString(self::LEGACY_GRID, $html);
    }

    public function testTheCartPatternsEmptyCartIsLeftAsItIs(): void
    {
        $pattern = WP_Block_Patterns_Registry::get_instance()->get_registered(self::CART_PATTERN);
        self::assertIsArray($pattern);

        $html = do_blocks($pattern['content']);

        self::assertSame(1, substr_count($html, 'pixonaut-shop-empty-cart'));
        self::assertStringContainsString('pixonaut-shop-popular-products', $html);
    }

    private function product(string $name): void
    {
        $product = new WC_Product_Simple();
        $product->set_name($name);
        $product->set_regular_price('100');
        $product->set_status('publish');
        $this->product_ids[] = $product->save();
    }
}
