<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Pixonaut\Shop\Cart\EmptyCart;
use WC_Product_Simple;
use WP_Block_Patterns_Registry;
use WP_Block_Type_Registry;

/**
 * WooCommerce's own empty cart, as a store's Cart page saves it, rendered as
 * the theme's: the message in a panel, nothing between it and what follows,
 * then the popular products in place of WooCommerce's own "New in store",
 * or the store's own newest products as the product grid's cards.
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

    /** WooCommerce's "New in store" heading, renamed by the store. */
    private const RENAMED = 'Made this week';

    private const CART_PATTERN = 'pixonaut-shop/cart';

    /** The popular products' heading and link to the shop, as their English source strings. */
    private const POPULAR_TITLE = 'Popular right now';

    private const VIEW_ALL = 'View all';

    /** The class of the popular products' section. */
    private const POPULAR = 'pixonaut-shop-popular-products';

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
    }

    public function testWooCommercesNewInStoreIsThePopularProductsWithAWayToTheShop(): void
    {
        $html = do_blocks(self::BY_PATTERN);

        self::assertStringContainsString(self::POPULAR, $html);
        self::assertStringContainsString(self::POPULAR_TITLE, $html);
        self::assertMatchesRegularExpression(
            '/<a href="' . preg_quote(esc_url((string) get_post_type_archive_link('product')), '/') . '">' . self::VIEW_ALL . '<\/a>/',
            $html
        );
        self::assertStringNotContainsString(EmptyCart::NEW_IN_STORE, $html);
        self::assertStringContainsString(self::CARDS, $html);
        self::assertStringNotContainsString(self::LEGACY_GRID, $html);
        self::assertStringNotContainsString('add_to_cart_button', $html);
    }

    public function testThePopularProductsAreTheMostSoldFirst(): void
    {
        // The seldom sold product is the newer one, so the newest first would put it first.
        $this->product('Often sold product', 50);
        $this->product('Seldom sold product', 1);

        $html = do_blocks(self::BY_PATTERN);

        $often  = strpos($html, 'Often sold product');
        $seldom = strpos($html, 'Seldom sold product');
        self::assertNotFalse($often);
        self::assertNotFalse($seldom);
        self::assertLessThan($seldom, $often);
    }

    public function testAnOlderPagesNewInStoreIsThePopularProductsToo(): void
    {
        $html = do_blocks(self::IN_PLACE);

        self::assertStringContainsString(self::POPULAR_TITLE, $html);
        self::assertStringNotContainsString(EmptyCart::NEW_IN_STORE, $html);
    }

    public function testARenamedNewInStoreKeepsTheStoresNewestProductsAsTheProductGridsCards(): void
    {
        $html = do_blocks($this->renamed());

        self::assertStringContainsString(self::RENAMED, $html);
        self::assertStringNotContainsString(self::POPULAR, $html);
        self::assertStringContainsString(self::CARDS, $html);
        self::assertStringContainsString('Newest card product', $html);
        self::assertStringNotContainsString(self::LEGACY_GRID, $html);
    }

    public function testNewestProductsWithTheStoresOwnChoicesAreKept(): void
    {
        $html = do_blocks(str_replace('{"columns":2,"rows":1}', '{"columns":2,"rows":1,"stockStatus":["instock"]}', self::IN_PLACE));

        self::assertStringContainsString(EmptyCart::NEW_IN_STORE, $html);
        self::assertStringNotContainsString(self::POPULAR, $html);
    }

    public function testABlockOfTheStoresOwnAfterTheNewestProductsKeepsTheSection(): void
    {
        $own = str_replace('/--></div>', '/-->' . "\n\n" . '<!-- wp:paragraph --><p>Made by hand</p><!-- /wp:paragraph --></div>', self::IN_PLACE);

        $html = do_blocks($own);

        self::assertStringContainsString(EmptyCart::NEW_IN_STORE, $html);
        self::assertStringContainsString('Made by hand', $html);
        self::assertStringNotContainsString(self::POPULAR, $html);
    }

    public function testAnOlderPagesMessageAndProductsAreDrawnTheSame(): void
    {
        $html = do_blocks($this->renamed());

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

    public function testTheCartBlockRewritesItsEmptyCartBeforeItsChildrenAreBuilt(): void
    {
        $cart = parse_blocks('<!-- wp:woocommerce/cart --><div class="wp-block-woocommerce-cart">' . self::BY_PATTERN . '</div><!-- /wp:woocommerce/cart -->')[0];

        $filtered = apply_filters('render_block_data', $cart, $cart, null);

        $empty_cart = $filtered['innerBlocks'][0];
        self::assertSame('core/group', $empty_cart['innerBlocks'][0]['blockName']);
        self::assertSame(EmptyCart::PANEL_CLASSES, $empty_cart['innerBlocks'][0]['attrs']['className']);
        self::assertStringContainsString(self::CARDS, do_blocks(serialize_block($filtered)));
    }

    public function testAnEmptyCartWithMarkupOfItsOwnBetweenItsBlocksIsLeftAsItIs(): void
    {
        $own = str_replace('<!-- wp:separator', '<p>Made to order</p><!-- wp:separator', self::IN_PLACE);

        self::assertNull(EmptyCart::rewrite($this->parse_one($own)));
    }

    public function testANewestProductsBlockWithoutASizeTakesWooCommercesDefaultSize(): void
    {
        $query = $this->collection_attributes(str_replace('{"columns":2,"rows":1}', '{}', $this->renamed()));
        $type  = WP_Block_Type_Registry::get_instance()->get_registered(EmptyCart::NEWEST_PRODUCTS);
        self::assertNotNull($type);
        $columns = $type->attributes['columns']['default'];
        $rows    = $type->attributes['rows']['default'];

        self::assertSame($columns, $query['displayLayout']['columns']);
        self::assertSame($columns * $rows, $query['query']['perPage']);
    }

    public function testTheNewestProductsKeepTheirStockStatuses(): void
    {
        $query = $this->collection_attributes(str_replace('{"columns":2,"rows":1}', '{"columns":2,"rows":1,"stockStatus":["instock"]}', self::IN_PLACE));

        self::assertSame(['instock'], $query['query']['woocommerceStockStatus']);
    }

    public function testNewestProductsInEveryOneOfSeveralCategoriesStayWooCommerces(): void
    {
        $rewritten = EmptyCart::rewrite($this->parse_one(str_replace('{"columns":2,"rows":1}', '{"columns":2,"rows":1,"categories":[3,4],"catOperator":"all"}', self::IN_PLACE)));

        self::assertNotNull($rewritten);
        self::assertContains(EmptyCart::NEWEST_PRODUCTS, array_column($rewritten['innerBlocks'], 'blockName'));
    }

    /** The older page's empty cart with its "New in store" heading renamed by the store. */
    private function renamed(): string
    {
        return str_replace('>' . EmptyCart::NEW_IN_STORE . '<', '>' . self::RENAMED . '<', self::IN_PLACE);
    }

    /**
     * The attributes of the product collection a rewritten empty cart holds.
     *
     * @return array<array-key, mixed>
     */
    private function collection_attributes(string $markup): array
    {
        $rewritten = EmptyCart::rewrite($this->parse_one($markup));
        self::assertNotNull($rewritten);

        foreach ($rewritten['innerBlocks'] as $block) {
            if ('woocommerce/product-collection' === $block['blockName']) {
                return $block['attrs'];
            }
        }

        self::fail('The empty cart holds no product collection.');
    }

    /**
     * @return array{blockName: string|null, attrs: array<array-key, mixed>, innerBlocks: list<array<array-key, mixed>>, innerHTML: string, innerContent: list<string|null>}
     */
    private function parse_one(string $markup): array
    {
        $block = parse_blocks($markup)[0];
        $block['innerBlocks'] = array_values($block['innerBlocks']);
        $block['innerContent'] = array_values($block['innerContent']);

        return $block;
    }

    private function product(string $name, int $sales = 0): void
    {
        $product = new WC_Product_Simple();
        $product->set_name($name);
        $product->set_regular_price('100');
        $product->set_status('publish');
        $product->set_total_sales($sales);
        $this->product_ids[] = $product->save();
    }
}
