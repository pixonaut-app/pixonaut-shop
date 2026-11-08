<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Pixonaut\Shop\Campaign\CampaignBand;
use Pixonaut\Shop\Product\LowestPrice;
use WC_Product;
use WC_Product_Attribute;
use WC_Product_Simple;
use WC_Product_Variable;
use WC_Product_Variation;
use WP_Block_Patterns_Registry;
use WP_Query;

/**
 * The lowest price of the 30 days before a reduction, as WordPress and
 * WooCommerce save products and render their prices: recorded on every
 * save and when a scheduled sale starts, and shown beside the reduced price
 * on the product page, in the product grid, on a cart line and in a
 * campaign band that links to the product.
 */
final class LowestPriceTest extends TestCase
{
    private const LABEL = 'Lowest price in the last 30 days';

    private const REGULAR = '300';

    private const SALE = '200';

    /** The price the product had 20 days before now, between its regular and its sale price. */
    private const EARLIER = '250';

    private const GRID_PATTERN = 'pixonaut-shop/product-grid';

    /** WooCommerce's setting that hides out-of-stock products and variations from the catalogue. */
    private const HIDE_OUT_OF_STOCK_OPTION = 'woocommerce_hide_out_of_stock_items';

    /** The sale-price start date's meta, which WooCommerce's scheduled sale reads. */
    private const SALE_FROM_META = '_sale_price_dates_from';

    /** @var list<int> */
    private array $product_ids = [];

    protected function tearDown(): void
    {
        foreach ($this->product_ids as $id) {
            wp_delete_post($id, true);
        }

        delete_option(CampaignBand::OPTION);
        $GLOBALS['wp_query']->init();
        wp_reset_postdata();
        unset($GLOBALS['product']);
        parent::tearDown();
    }

    public function testRecordsEachChangeOfTheActivePriceWhenAProductIsSaved(): void
    {
        $product = $this->simple_product(null);
        self::assertSame([self::REGULAR], $this->recorded_prices($product));

        $product->set_sale_price(self::SALE);
        $product->save();
        self::assertSame([self::REGULAR, self::SALE], $this->recorded_prices($product));

        $product->set_name('Renamed tee');
        $product->save();
        self::assertSame([self::REGULAR, self::SALE], $this->recorded_prices($product), 'A save that keeps the price records nothing.');
    }

    public function testRecordsAScheduledSaleWhenItStarts(): void
    {
        $product = $this->simple_product(null);
        $product->set_sale_price(self::SALE);
        $product->set_date_on_sale_from((string) (time() + HOUR_IN_SECONDS));
        $product->save();
        self::assertSame([self::REGULAR], $this->recorded_prices($product), 'A sale that has not started leaves the price.');

        // The start date passes without a save, as it does on the clock.
        update_post_meta($product->get_id(), self::SALE_FROM_META, time() - MINUTE_IN_SECONDS);
        wc_delete_product_transients($product->get_id());
        do_action('wc_product_start_scheduled_sale', $product->get_id());

        self::assertSame([self::REGULAR, self::SALE], $this->recorded_prices($product));
    }

    /**
     * The price a shopper sees once a scheduled sale has started carries
     * the line, with the price before the sale; before it, none.
     */
    public function testShowsTheLowestPriceOnceAScheduledSaleHasStarted(): void
    {
        $product = $this->simple_product(null);
        $product->set_sale_price(self::SALE);
        $product->set_date_on_sale_from((string) (time() + HOUR_IN_SECONDS));
        $product->save();
        self::assertStringNotContainsString(self::LABEL, $this->price_html($product));

        update_post_meta($product->get_id(), self::SALE_FROM_META, time() - MINUTE_IN_SECONDS);
        wc_delete_product_transients($product->get_id());
        do_action('wc_product_start_scheduled_sale', $product->get_id());

        $price_html = $this->price_html($product);
        self::assertMatchesRegularExpression('/<ins\b.*' . self::SALE . '.*' . self::LABEL . ': .*' . self::REGULAR . '/s', $price_html);
    }

    /** Raised five days before it was reduced: the line shows the price before the raise. */
    public function testShowsThePriceBeforeARaiseThatPrecededTheReduction(): void
    {
        $product = $this->simple_product('120', 'Raised tee', '150');
        $this->history($product, ['100' => '-90 days', '150' => '-5 days', '120' => '-1 minute']);

        self::assertMatchesRegularExpression('/' . self::LABEL . ': .*100/s', $this->price_html($product));
    }

    /**
     * A variation hidden from the range (out of stock, with the store
     * hiding those) is left out of the range's line, as from the range.
     */
    public function testRangeLeavesOutAVariationItDoesNotShow(): void
    {
        update_option(self::HIDE_OUT_OF_STOCK_OPTION, 'yes');

        try {
            [$variable, $small, $large] = $this->variable_product();
            $small->set_stock_status('outofstock');
            $small->save();
            WC_Product_Variable::sync($variable->get_id());
            $this->history($small, ['100' => '-20 days', self::SALE => '-2 days']);
            $this->history($large, ['180' => '-20 days', self::SALE => '-2 days']);

            $price_html = $this->price_html($variable);

            self::assertMatchesRegularExpression('/' . self::LABEL . ': .*180/s', $price_html);
            self::assertStringNotContainsString('100', $price_html);
        } finally {
            delete_option(self::HIDE_OUT_OF_STOCK_OPTION);
        }
    }

    public function testShowsTheLowestPriceOnAReducedProductsPage(): void
    {
        $product = $this->simple_product(self::SALE);
        $this->history($product, [self::EARLIER => '-20 days', self::SALE => '-2 days']);

        $page = $this->product_page($product->get_id());

        self::assertMatchesRegularExpression(
            '/data-pixonaut-price>.*' . self::SALE . '.*' . self::LABEL . ': .*' . self::EARLIER . '/s',
            $page,
            'The price row shows the lowest price after the reduced price.'
        );
    }

    public function testShowsNoLowestPriceOnAProductAtItsRegularPrice(): void
    {
        $product = $this->simple_product(null);

        self::assertStringNotContainsString(self::LABEL, $this->product_page($product->get_id()));
    }

    public function testShowsTheLowestPriceOnAReducedProductsCardOnly(): void
    {
        $reduced = $this->simple_product(self::SALE, 'Reduced tee');
        $this->history($reduced, [self::EARLIER => '-20 days', self::SALE => '-2 days']);
        $this->simple_product(null, 'Regular tee');

        $grid = $this->grid();

        self::assertSame(1, substr_count($grid, self::LABEL));
        self::assertMatchesRegularExpression(
            '/Reduced tee.*wc-block-components-product-price.*' . self::LABEL . ': .*' . self::EARLIER . '/s',
            $grid
        );
    }

    public function testVariationsShowTheirOwnLowestPriceAndTheRangeTheLowest(): void
    {
        [$variable, $small, $large] = $this->variable_product();
        $this->history($small, ['180' => '-20 days', self::SALE => '-2 days']);
        $this->history($large, ['160' => '-20 days', self::SALE => '-2 days']);

        self::assertStringContainsString(self::LABEL . ': ', $small->get_price_html());
        self::assertMatchesRegularExpression('/' . self::LABEL . ': .*180/s', $small->get_price_html());
        self::assertMatchesRegularExpression('/' . self::LABEL . ': .*160/s', $large->get_price_html());
        self::assertMatchesRegularExpression(
            '/data-pixonaut-price>.*' . self::LABEL . ': .*160/s',
            $this->product_page($variable->get_id()),
            'The range shows the lowest of the reduced variations.'
        );
    }

    public function testAddsTheLowestPriceToAReducedCartLine(): void
    {
        $product = $this->simple_product(self::SALE);
        $this->history($product, [self::EARLIER => '-20 days', self::SALE => '-2 days']);

        /** @var list<array{key: string, display: string}> $rows */
        $rows = apply_filters('woocommerce_get_item_data', [], ['data' => wc_get_product($product->get_id())]);

        self::assertSame([self::LABEL], array_column($rows, 'key'));
        self::assertStringContainsString(self::EARLIER, $rows[0]['display']);
        self::assertSame([], apply_filters('woocommerce_get_item_data', [], ['data' => wc_get_product($this->simple_product(null)->get_id())]));
    }

    public function testCampaignBandLinkingToAReducedProductShowsItsLowestPrice(): void
    {
        $product = $this->simple_product(self::SALE);
        $this->history($product, [self::EARLIER => '-20 days', self::SALE => '-2 days']);
        $now = current_datetime();
        update_option(CampaignBand::OPTION, [
            'text' => 'Tees on sale',
            'url' => get_permalink($product->get_id()),
            'starts' => $now->modify('-1 day')->format('Y-m-d\TH:i:s'),
            'ends' => $now->modify('+1 day')->format('Y-m-d\TH:i:s'),
        ]);

        $header = do_blocks('<!-- wp:template-part {"slug":"header","theme":"' . get_stylesheet() . '"} /-->');

        self::assertMatchesRegularExpression('/Tees on sale<\/a> <span class="pixonaut-shop-lowest-price">' . self::LABEL . ': .*' . self::EARLIER . '/s', $header);
    }

    private function simple_product(?string $sale_price, string $name = 'Plain tee', string $regular_price = self::REGULAR): WC_Product
    {
        $product = new WC_Product_Simple();
        $product->set_name($name);
        $product->set_regular_price($regular_price);
        $product->set_sale_price($sale_price ?? '');
        $product->set_status('publish');
        $this->product_ids[] = $product->save();

        return $product;
    }

    /**
     * A variable product in two sizes, both reduced from the regular price
     * to the sale price.
     *
     * @return array{WC_Product, WC_Product, WC_Product}
     */
    private function variable_product(): array
    {
        $size = new WC_Product_Attribute();
        $size->set_name('Size');
        $size->set_options(['Small', 'Large']);
        $size->set_visible(true);
        $size->set_variation(true);

        $product = new WC_Product_Variable();
        $product->set_name('Sized tee');
        $product->set_attributes([$size]);
        $product->set_status('publish');
        $id = $product->save();
        $this->product_ids[] = $id;
        $variations = [];

        foreach (['Small', 'Large'] as $option) {
            $variation = new WC_Product_Variation();
            $variation->set_parent_id($id);
            $variation->set_attributes(['size' => $option]);
            $variation->set_regular_price(self::REGULAR);
            $variation->set_sale_price(self::SALE);
            $variation->set_status('publish');
            $variation->save();
            $variations[] = $variation;
        }

        WC_Product_Variable::sync($id);
        $variable = wc_get_product($id);
        self::assertInstanceOf(WC_Product::class, $variable);

        return [$variable, ...$variations];
    }

    /**
     * Gives a product a history of its own, each price from a moment
     * relative to now.
     *
     * @param array<string, string> $prices Each price and when it took effect, oldest first.
     */
    private function history(WC_Product $product, array $prices): void
    {
        $now = current_datetime();
        $history = [];

        foreach ($prices as $price => $relative) {
            $history[] = ['price' => (string) $price, 'from' => $now->modify($relative)->format(DATE_ATOM)];
        }

        update_post_meta($product->get_id(), LowestPrice::HISTORY_META, $history);
    }

    /** The product's price HTML as a shopper sees it, read fresh from the store. */
    private function price_html(WC_Product $product): string
    {
        $fresh = wc_get_product($product->get_id());
        self::assertInstanceOf(WC_Product::class, $fresh);

        return $fresh->get_price_html();
    }

    /** @return list<string> */
    private function recorded_prices(WC_Product $product): array
    {
        $history = get_post_meta($product->get_id(), LowestPrice::HISTORY_META, true);
        self::assertIsArray($history);

        return array_values(array_map(static fn (array $entry): string => $entry['price'], $history));
    }

    private function product_page(int $product_id): string
    {
        $query = $GLOBALS['wp_query'];
        self::assertInstanceOf(WP_Query::class, $query);
        $query->query(['post_type' => 'product', 'p' => $product_id]);
        $query->the_post();

        $template = get_block_template(get_stylesheet() . '//single-product');
        self::assertNotNull($template);

        return do_blocks($template->content);
    }

    private function grid(): string
    {
        $query = $GLOBALS['wp_query'];
        self::assertInstanceOf(WP_Query::class, $query);
        $query->query(['post_type' => 'product', 'post__in' => $this->product_ids]);

        $pattern = WP_Block_Patterns_Registry::get_instance()->get_registered(self::GRID_PATTERN);
        self::assertIsArray($pattern);

        return do_blocks($pattern['content']);
    }
}
