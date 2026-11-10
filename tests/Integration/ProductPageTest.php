<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pixonaut\Shop\Product\PreviewFrame;
use Pixonaut\Shop\Tests\Support\ShopFixtures;
use WC_Product_Attribute;
use WC_Product_External;
use WC_Product_Simple;
use WC_Product_Variable;
use WC_Product_Variation;
use WP_Block_Styles_Registry;
use WP_Post;
use WP_Query;

/**
 * The product page as WooCommerce renders it with the theme's template: the
 * price in the add-to-cart form's last row, after where Pixonaut for
 * WooCommerce prints its designer, the description in its own panel, and
 * the preview frame at the shape the store picks on the gallery block.
 */
final class ProductPageTest extends TestCase
{
    private const INSERTION_POINT = 'woocommerce_before_add_to_cart_button';

    /** What this test prints where the plugin prints its designer, on every product. */
    private const MARKER = '<div data-test-insertion-point></div>';

    /** The prices of the variable product's two sizes. */
    private const SIZE_PRICES = ['Small' => '100', 'Large' => '150'];

    private const SIMPLE_PRICE = '249';

    /** Where the external product sends a customer; never requested. */
    private const EXTERNAL_URL = 'https://shop.invalid/rowless-tee';

    private const ADD_TO_CART_FORM = '/<form class="(?:variations_form )?cart"/';

    /** @var list<int> */
    private array $product_ids = [];

    public static function setUpBeforeClass(): void
    {
        ShopFixtures::prepare();
    }

    protected function setUp(): void
    {
        parent::setUp();
        add_action(self::INSERTION_POINT, [self::class, 'print_marker']);
    }

    protected function tearDown(): void
    {
        remove_action(self::INSERTION_POINT, [self::class, 'print_marker']);

        foreach ($this->product_ids as $id) {
            wp_delete_post($id, true);
        }

        $GLOBALS['wp_query']->init();
        wp_reset_postdata();
        unset($GLOBALS['product']);
        parent::tearDown();
    }

    public static function print_marker(): void
    {
        echo self::MARKER; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a fixed string.
    }

    public function testPrintsASimpleProductsPriceInTheFormsRowAfterTheDesigner(): void
    {
        $page = $this->render($this->simple_product());

        $this->assertPriceRowFollowsTheInsertionPoint($page);
        self::assertMatchesRegularExpression('/data-pixonaut-price>.*' . self::SIMPLE_PRICE . '/s', $page);
    }

    public function testPrintsAVariableProductsPriceInTheVariationsRowAfterTheDesigner(): void
    {
        $page = $this->render($this->variable_product());

        $this->assertPriceRowFollowsTheInsertionPoint($page);
        self::assertStringContainsString('woocommerce-variation-add-to-cart', $page);
        self::assertMatchesRegularExpression(
            '/data-pixonaut-price>From .*' . min(self::SIZE_PRICES) . '/s',
            $page,
            'The row shows the sizes\' prices from the lowest.'
        );
    }

    /**
     * WooCommerce prints no form for a simple product out of stock, and an
     * external product's form has no quantity: both still show their price,
     * once, without a row or the plugin's target.
     *
     * @return iterable<string, array{string}>
     */
    public static function formsWithoutARow(): iterable
    {
        yield 'out of stock' => ['out of stock'];
        yield 'external' => ['external'];
    }

    #[DataProvider('formsWithoutARow')]
    public function testShowsThePriceOfAProductWhoseFormPrintsNoRow(string $kind): void
    {
        $product = 'external' === $kind ? new WC_Product_External() : new WC_Product_Simple();
        $product->set_name('Rowless tee');
        $product->set_regular_price(self::SIMPLE_PRICE);
        $product->set_status('publish');

        if ($product instanceof WC_Product_External) {
            $product->set_product_url(self::EXTERNAL_URL);
        } else {
            $product->set_stock_status('outofstock');
        }

        $id = $product->save();
        $this->product_ids[] = $id;

        $page = $this->render($id);

        self::assertSame(1, preg_match_all('/<div class="pixonaut-shop-price">.*?' . self::SIMPLE_PRICE . '/s', $page));
        self::assertStringNotContainsString('pixonaut-shop-buy-row', $page);
        self::assertStringNotContainsString('data-pixonaut-price', $page);
    }

    public function testThePluginsFormStaysAboveThePriceRow(): void
    {
        $post = get_page_by_path(ShopFixtures::PRODUCT_SLUG, OBJECT, 'product');
        self::assertInstanceOf(WP_Post::class, $post);

        $page = $this->render($post->ID);

        $designer = strpos($page, 'data-fake-pixonaut="form"');
        $approval = strpos($page, 'data-fake-pixonaut="approval"');
        $price = strpos($page, 'data-pixonaut-price');

        self::assertNotFalse($designer);
        self::assertNotFalse($approval);
        self::assertNotFalse($price);
        self::assertGreaterThan($approval, $price);
    }

    public function testPrintsTheDescriptionInItsPanelBelowTheForm(): void
    {
        $page = $this->render($this->simple_product('A description the store wrote.'));

        $form = self::add_to_cart_form($page);
        self::assertNotFalse($form);
        $form_end = strpos($page, '</form>', $form);
        $panel = strpos($page, 'pixonaut-shop-product-description');

        self::assertNotFalse($form_end);
        self::assertNotFalse($panel);
        self::assertGreaterThan($form_end, $panel);
        self::assertStringContainsString('A description the store wrote.', substr($page, $panel));
    }

    public function testOffersAFrameStyleForEveryShapeOnTheGallery(): void
    {
        $styles = WP_Block_Styles_Registry::get_instance()->get_registered_styles_for_block(PreviewFrame::GALLERY_BLOCK);

        self::assertArrayHasKey('frame-5-7', $styles);
        self::assertSame('Portrait - 5:7', $styles['frame-5-7']['label']);
        self::assertStringContainsString('--ps-frame-aspect:0.7143', $styles['frame-5-7']['inline_style']);
    }

    public function testHoldsThePreviewFrameAtTheShapeTheStorePicks(): void
    {
        $page = $this->render($this->simple_product(), '{"className":"is-style-frame-5-7"}');

        self::assertMatchesRegularExpression(
            '#<div class="' . PreviewFrame::FRAME_CLASS . '" style="--ps-frame-aspect: 0.7143">\s*<div [^>]*class="[^"]*(?:wp-block-woocommerce-product-image-gallery|pixonaut-preview)#',
            $page
        );
    }

    public function testLeavesTheFrameToThePluginWithoutAShape(): void
    {
        self::assertStringNotContainsString(PreviewFrame::FRAME_CLASS, $this->render($this->simple_product()));
    }

    public function testPrintsNoStandalonePriceBlockAboveTheForm(): void
    {
        $page = $this->render($this->simple_product());
        $form = strpos($page, '<form class="cart"');

        self::assertNotFalse($form);
        self::assertStringNotContainsString('wp-block-woocommerce-product-price', substr($page, 0, $form));
    }

    private function assertPriceRowFollowsTheInsertionPoint(string $page): void
    {
        $form = self::add_to_cart_form($page);
        self::assertNotFalse($form, 'No add-to-cart form on the page.');

        $order = [
            'form'       => $form,
            'designer'   => strpos($page, self::MARKER),
            'row'        => strpos($page, '<div class="pixonaut-shop-buy-row">'),
            'price'      => strpos($page, '<div class="pixonaut-shop-price" data-pixonaut-price>'),
            'quantity'   => strpos($page, 'name="quantity"'),
            'button'     => strpos($page, 'single_add_to_cart_button'),
            'form end'   => strpos($page, '</form>', $form),
        ];

        foreach ($order as $what => $position) {
            self::assertNotFalse($position, "No {$what} on the page.");
        }

        $positions = array_values($order);
        $sorted = $positions;
        sort($sorted);

        self::assertSame($sorted, $positions, 'Expected, in order: ' . implode(', ', array_keys($order)) . '.');
        self::assertSame(1, substr_count($page, 'data-pixonaut-price'));
    }

    /**
     * Where the add-to-cart form opens: `cart` for a simple product,
     * `variations_form cart` for a variable one. The header has a form too.
     */
    private static function add_to_cart_form(string $page): int|false
    {
        return preg_match(self::ADD_TO_CART_FORM, $page, $match, PREG_OFFSET_CAPTURE) ? $match[0][1] : false;
    }

    private function simple_product(string $description = ''): int
    {
        $product = new WC_Product_Simple();
        $product->set_name('Row tee');
        $product->set_description($description);
        $product->set_regular_price(self::SIMPLE_PRICE);
        $product->set_status('publish');

        $id = $product->save();
        $this->product_ids[] = $id;

        return $id;
    }

    private function variable_product(): int
    {
        $size = new WC_Product_Attribute();
        $size->set_name('Size');
        $size->set_options(array_keys(self::SIZE_PRICES));
        $size->set_visible(true);
        $size->set_variation(true);

        $product = new WC_Product_Variable();
        $product->set_name('Sized tee');
        $product->set_attributes([$size]);
        $product->set_status('publish');
        $id = $product->save();
        $this->product_ids[] = $id;

        foreach (self::SIZE_PRICES as $option => $price) {
            $variation = new WC_Product_Variation();
            $variation->set_parent_id($id);
            $variation->set_attributes(['size' => $option]);
            $variation->set_regular_price($price);
            $variation->set_status('publish');
            $variation->save();
        }

        WC_Product_Variable::sync($id);

        return $id;
    }

    /**
     * The single-product template for a product, as its page renders it.
     */
    /**
     * @param string $gallery_attributes The gallery block's attributes as the Site Editor saves them, if any.
     */
    private function render(int $product_id, string $gallery_attributes = ''): string
    {
        $query = $GLOBALS['wp_query'];
        self::assertInstanceOf(WP_Query::class, $query);
        $query->query(['post_type' => 'product', 'p' => $product_id]);
        $query->the_post();

        $template = get_block_template(get_stylesheet() . '//single-product');
        self::assertNotNull($template);
        $content = '' === $gallery_attributes ? $template->content : str_replace(
            '<!-- wp:' . PreviewFrame::GALLERY_BLOCK . ' /-->',
            '<!-- wp:' . PreviewFrame::GALLERY_BLOCK . " {$gallery_attributes} /-->",
            $template->content
        );

        return do_blocks($content);
    }
}
