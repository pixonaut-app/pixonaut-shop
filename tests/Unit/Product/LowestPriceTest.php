<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Product;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Pixonaut\Shop\Product\LowestPrice;
use Pixonaut\Shop\Tests\TestCase;
use WC_Product;

/**
 * The lowest price a product had in the 30 days before its current
 * reduction, shown beside every reduced price, from the price history the
 * theme keeps in the product's meta.
 */
#[CoversClass(LowestPrice::class)]
final class LowestPriceTest extends TestCase
{
    private const NOW = '2026-11-27T12:00:00+00:00';

    private const PRODUCT_ID = 7;

    private const REGULAR = '300';

    private const SALE = '200';

    private const PRICE_HTML = '<del>300 kr</del> <ins>200 kr</ins>';

    private const LABEL = 'Lowest price in the last 30 days';

    /** @var array<int, mixed> Each product's stored history, by its ID. */
    private array $histories = [];

    /** @var list<array{int, string, mixed}> Every `update_post_meta()` call, in order. */
    private array $written = [];

    /** @var list<int> Every product ID `wc_get_product()` was asked for, in order. */
    private array $loaded = [];

    /** @var list<list<int>> Every `_prime_post_caches()` call's IDs, in order. */
    private array $primed = [];

    /** The cart's `woocommerce_tax_display_cart` setting in a test. */
    private string $cart_tax_display = 'incl';

    /** What the stand-in for VAT divides a price including it by, for a price excluding it. */
    private const WITH_VAT = 1.25;

    protected function setUp(): void
    {
        parent::setUp();
        Functions\stubTranslationFunctions();
        Functions\stubEscapeFunctions();
        Functions\when('get_post_meta')->alias(fn (int $id): mixed => $this->histories[$id] ?? '');
        Functions\when('update_post_meta')->alias(function (int $id, string $key, mixed $value): bool {
            $this->written[] = [$id, $key, $value];

            return true;
        });
        Functions\when('wc_price')->alias(static fn (float $price): string => '<span class="amount">' . $price . '&nbsp;kr</span>');
        Functions\when('wc_get_price_to_display')->alias(static fn (WC_Product $product, array $args): float => (float) $args['price']);
        Functions\when('wp_strip_all_tags')->alias(static fn (string $text): string => strip_tags($text));
        Functions\when('wc_get_price_including_tax')->alias(static fn (WC_Product $product, array $args): float => (float) $args['price']);
        Functions\when('wc_get_price_excluding_tax')->alias(static fn (WC_Product $product, array $args): float => (float) $args['price'] / self::WITH_VAT);
        Functions\when('get_option')->alias(fn (string $name): mixed => 'woocommerce_tax_display_cart' === $name ? $this->cart_tax_display : false);
        Functions\when('is_admin')->justReturn(false);
        Functions\when('wp_doing_ajax')->justReturn(false);
        Functions\when('_prime_post_caches')->alias(function (array $ids): void {
            $this->primed[] = array_values($ids);
        });
    }

    /**
     * `wc_get_product()` over these products, counting each load.
     *
     * @param array<int, WC_Product> $products
     */
    private function products(array $products): void
    {
        Functions\when('wc_get_product')->alias(function (int $id) use ($products): WC_Product|false {
            $this->loaded[] = $id;

            return $products[$id] ?? false;
        });
    }

    /**
     * A variable product's prices as `get_variation_prices()` gives them: its
     * visible variations' only, each reduced from the regular price to the
     * sale price unless named as not reduced.
     *
     * @param list<int> $shown       The variations the range shows.
     * @param list<int> $not_reduced Those of them at their regular price.
     *
     * @return array{price: array<int, string>, regular_price: array<int, string>, sale_price: array<int, string>}
     */
    private static function variation_prices(array $shown, array $not_reduced = []): array
    {
        $prices = ['price' => [], 'regular_price' => [], 'sale_price' => []];

        foreach ($shown as $id) {
            $price = \in_array($id, $not_reduced, true) ? self::REGULAR : self::SALE;
            $prices['price'][$id] = $price;
            $prices['regular_price'][$id] = self::REGULAR;
            $prices['sale_price'][$id] = $price;
        }

        return $prices;
    }

    private static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::NOW);
    }

    private static function at(string $relative): string
    {
        return self::now()->modify($relative)->format(DATE_ATOM);
    }

    /** @return array{price: string, from: string} */
    private static function entry(string $price, string $relative): array
    {
        return ['price' => $price, 'from' => self::at($relative)];
    }

    private function lowest_price(): LowestPrice
    {
        return new LowestPrice(static fn (): DateTimeImmutable => self::now());
    }

    /**
     * @param list<int>                         $children
     * @param array<string, array<int, string>> $variation_prices
     */
    private static function product(
        int $id,
        string $type = 'simple',
        bool $on_sale = true,
        string $price = self::SALE,
        string $regular = self::REGULAR,
        array $children = [],
        array $variation_prices = [],
    ): WC_Product {
        $product = Mockery::mock(WC_Product::class);
        $product->allows('get_id')->andReturn($id);
        $product->allows('is_type')->andReturnUsing(static fn (string|array $types): bool => \in_array($type, (array) $types, true));
        $product->allows('is_on_sale')->andReturn($on_sale);
        $product->allows('get_price')->andReturn($price);
        $product->allows('get_regular_price')->andReturn($regular);
        $product->allows('get_children')->andReturn($children);
        $product->allows('get_variation_prices')->with(true)->andReturn($variation_prices);

        return $product;
    }

    public function testLowestPriceIsTheMinimumOfTheLast30DaysBeforeTheReduction(): void
    {
        $history = [
            // Ended 60 days ago, before the window: never the reference.
            self::entry('150', '-100 days'),
            self::entry('250', '-60 days'),
            self::entry('230', '-20 days'),
            self::entry(self::SALE, '-5 days'),
        ];

        self::assertSame('230', LowestPrice::lowest($history, self::SALE, self::now()));
    }

    /**
     * A reduction that took effect 50 days ago looks at the 30 days before
     * it, not the 30 days before today.
     */
    public function testTheWindowEndsWhereTheReductionStarted(): void
    {
        $history = [
            self::entry('150', '-100 days'),
            self::entry(self::REGULAR, '-70 days'),
            self::entry(self::SALE, '-50 days'),
        ];

        self::assertSame('150', LowestPrice::lowest($history, self::SALE, self::now()));
    }

    public function testShortHistoryUsesTheLowestPriceKnown(): void
    {
        $history = [self::entry('260', '-10 days'), self::entry('240', '-6 days'), self::entry(self::SALE, '-2 days')];

        self::assertSame('240', LowestPrice::lowest($history, self::SALE, self::now()));
    }

    /**
     * A product first seen already on sale has no earlier price; its regular
     * price is the one shown.
     */
    public function testAProductFirstSeenOnSaleShowsItsRegularPrice(): void
    {
        self::assertNull(LowestPrice::lowest([self::entry(self::SALE, '-1 day')], self::SALE, self::now()));
        self::assertNull(LowestPrice::lowest([], self::SALE, self::now()));

        $this->histories[self::PRODUCT_ID] = [self::entry(self::SALE, '-1 day')];

        self::assertSame(300.0, $this->lowest_price()->reference(self::product(self::PRODUCT_ID)));
    }

    /**
     * A price that changed without the theme seeing it took effect no
     * later than now: every price the history knows is from before it.
     */
    public function testAPriceTheHistoryHasNotSeenCountsFromNow(): void
    {
        $history = [self::entry('260', '-10 days'), self::entry('240', '-6 days')];

        self::assertSame('240', LowestPrice::lowest($history, self::SALE, self::now()));
    }

    public function testVariableProductUsesEachVariationsHistory(): void
    {
        $this->histories = [
            11 => [self::entry('180', '-20 days'), self::entry(self::SALE, '-2 days')],
            12 => [self::entry('160', '-20 days'), self::entry(self::SALE, '-2 days')],
            // Not on sale: its history never counts.
            13 => [self::entry('100', '-20 days'), self::entry(self::SALE, '-2 days')],
        ];
        $variations = [
            11 => self::product(11, 'variation'),
            12 => self::product(12, 'variation'),
            13 => self::product(13, 'variation', false, self::SALE, self::SALE),
        ];
        $this->products($variations);
        $variable = self::product(self::PRODUCT_ID, 'variable', true, self::SALE, self::REGULAR, [11, 12, 13], self::variation_prices([11, 12, 13], [13]));
        $lowest_price = $this->lowest_price();

        self::assertSame(180.0, $lowest_price->reference($variations[11]));
        self::assertSame(160.0, $lowest_price->reference($variations[12]));
        self::assertSame(160.0, $lowest_price->reference($variable), 'The range shows the lowest of the reduced variations.');
        self::assertStringContainsString('180&nbsp;kr', $lowest_price->with_price_html(self::PRICE_HTML, $variations[11]));
        self::assertStringContainsString('160&nbsp;kr', $lowest_price->with_price_html(self::PRICE_HTML, $variable));
    }

    /** Raised before it was reduced: the window reaches back past the raise. */
    public function testRaisedThenReducedLooksBackPastTheRaise(): void
    {
        $history = [self::entry('100', '-90 days'), self::entry('150', '-5 days'), self::entry('120', '+0 days')];

        self::assertSame('100', LowestPrice::lowest($history, '120', self::now()));
    }

    /** A sale price lowered during the sale: the earlier sale price is the lowest before it. */
    public function testASalePriceLoweredMidSaleLooksBackToTheEarlierSalePrice(): void
    {
        $history = [self::entry('100', '-60 days'), self::entry('80', '-10 days'), self::entry('70', '+0 days')];

        self::assertSame('80', LowestPrice::lowest($history, '70', self::now()));
    }

    /** A price that ended at the very moment the window opens was never charged inside it. */
    public function testAnEntryEndingExactlyWhenTheWindowOpensIsLeftOut(): void
    {
        $history = [self::entry('50', '-60 days'), self::entry('100', '-30 days'), self::entry('70', '+0 days')];

        self::assertSame('100', LowestPrice::lowest($history, '70', self::now()));
    }

    /**
     * A grid prints a variable product's price several times; its reduced
     * variations are primed once and loaded once a request.
     */
    public function testLoadsAVariableProductsReducedVariationsOncePerRequest(): void
    {
        $this->histories = [
            11 => [self::entry('180', '-20 days'), self::entry(self::SALE, '-2 days')],
            12 => [self::entry('160', '-20 days'), self::entry(self::SALE, '-2 days')],
        ];
        $this->products([11 => self::product(11, 'variation'), 12 => self::product(12, 'variation'), 13 => self::product(13, 'variation', false, self::REGULAR)]);
        $variable = self::product(self::PRODUCT_ID, 'variable', true, self::SALE, self::REGULAR, [11, 12, 13], self::variation_prices([11, 12, 13], [13]));
        $lowest_price = $this->lowest_price();

        for ($printed = 0; $printed < 3; ++$printed) {
            self::assertStringContainsString('160&nbsp;kr', $lowest_price->with_price_html(self::PRICE_HTML, $variable));
        }

        self::assertSame([[11, 12]], $this->primed);
        self::assertSame([11, 12], $this->loaded);
    }

    /**
     * A hidden or out-of-stock variation is not in the range the product
     * shows, so its lower reference is not the range's.
     */
    public function testLeavesOutVariationsTheRangeDoesNotShow(): void
    {
        $this->histories = [
            11 => [self::entry('180', '-20 days'), self::entry(self::SALE, '-2 days')],
            // Hidden or out of stock, and reduced, with a lower price before it.
            14 => [self::entry('100', '-20 days'), self::entry(self::SALE, '-2 days')],
        ];
        $this->products([11 => self::product(11, 'variation'), 14 => self::product(14, 'variation')]);
        $variable = self::product(self::PRODUCT_ID, 'variable', true, self::SALE, self::REGULAR, [11, 14], self::variation_prices([11]));

        self::assertSame(180.0, $this->lowest_price()->reference($variable));
        self::assertNotContains(14, $this->loaded);
    }

    /** A price change seen in a request is the one its later prices show. */
    public function testRecordingForgetsTheReferencesWorkedOutBefore(): void
    {
        $this->histories[self::PRODUCT_ID] = [self::entry('250', '-20 days'), self::entry(self::SALE, '-2 days')];
        $lowest_price = $this->lowest_price();
        self::assertSame(250.0, $lowest_price->reference(self::product(self::PRODUCT_ID)));

        $this->histories[self::PRODUCT_ID] = [self::entry('220', '-20 days'), self::entry(self::SALE, '-2 days')];
        $lowest_price->record_saved(self::PRODUCT_ID, self::product(self::PRODUCT_ID));

        self::assertSame(220.0, $lowest_price->reference(self::product(self::PRODUCT_ID)));
    }

    /** The cart shows prices with or without tax by its own setting, which may differ from the shop's. */
    public function testCartLineFollowsTheCartsTaxDisplay(): void
    {
        $this->histories[self::PRODUCT_ID] = [self::entry('250', '-20 days'), self::entry(self::SALE, '-2 days')];
        $this->cart_tax_display = 'excl';

        $rows = $this->lowest_price()->with_item_data([], ['data' => self::product(self::PRODUCT_ID)]);

        self::assertSame("200\u{00A0}kr", $rows[0]['display'] ?? null);
    }

    /** The admin's product list shows the price without the line. */
    public function testLeavesTheAdminProductListAlone(): void
    {
        $this->histories[self::PRODUCT_ID] = [self::entry('250', '-20 days'), self::entry(self::SALE, '-2 days')];
        Functions\when('is_admin')->justReturn(true);

        self::assertSame(self::PRICE_HTML, $this->lowest_price()->with_price_html(self::PRICE_HTML, self::product(self::PRODUCT_ID)));
    }

    public function testShownOnlyWhileOnSale(): void
    {
        $this->histories[self::PRODUCT_ID] = [self::entry('150', '-20 days'), self::entry(self::REGULAR, '-2 days')];
        $product = self::product(self::PRODUCT_ID, 'simple', false, self::REGULAR);
        $lowest_price = $this->lowest_price();

        self::assertNull($lowest_price->reference($product));
        self::assertSame(self::PRICE_HTML, $lowest_price->with_price_html(self::PRICE_HTML, $product));
        self::assertSame([], $lowest_price->with_item_data([], ['data' => $product]));
    }

    public function testAddsTheLowestPriceBesideAReducedPrice(): void
    {
        $this->histories[self::PRODUCT_ID] = [self::entry('250', '-20 days'), self::entry(self::SALE, '-2 days')];

        $html = $this->lowest_price()->with_price_html(self::PRICE_HTML, self::product(self::PRODUCT_ID));

        self::assertSame(
            self::PRICE_HTML . '<span class="pixonaut-shop-lowest-price">' . self::LABEL . ': <span class="amount">250&nbsp;kr</span></span>',
            $html
        );
    }

    public function testAddsTheLowestPriceToAReducedCartLine(): void
    {
        $this->histories[self::PRODUCT_ID] = [self::entry('250', '-20 days'), self::entry(self::SALE, '-2 days')];
        $other_row = ['key' => 'Size', 'value' => 'M'];

        $rows = $this->lowest_price()->with_item_data([$other_row], ['data' => self::product(self::PRODUCT_ID)]);

        self::assertSame([$other_row, ['key' => self::LABEL, 'value' => "250\u{00A0}kr", 'display' => "250\u{00A0}kr"]], $rows);
    }

    /** @return iterable<string, array{mixed}> */
    public static function cartLinesWithoutAProduct(): iterable
    {
        yield 'no product' => [[]];
        yield 'not a product' => [['data' => 'tee']];
        yield 'not a line' => ['line'];
    }

    #[DataProvider('cartLinesWithoutAProduct')]
    public function testLeavesACartLineWithoutAProductAlone(mixed $cart_item): void
    {
        self::assertSame([], $this->lowest_price()->with_item_data([], $cart_item));
    }

    public function testLeavesPriceHtmlAloneWithoutAProduct(): void
    {
        self::assertSame(self::PRICE_HTML, $this->lowest_price()->with_price_html(self::PRICE_HTML, null));
        self::assertSame('', $this->lowest_price()->with_price_html('', self::product(self::PRODUCT_ID)));
    }

    /** @return iterable<string, array{mixed}> */
    public static function brokenHistories(): iterable
    {
        yield 'not a list' => ['300'];
        yield 'entry without a price' => [[['from' => '2026-11-07T12:00:00+00:00']]];
        yield 'price that is not a number' => [[['price' => 'cheap', 'from' => '2026-11-07T12:00:00+00:00']]];
        yield 'date that is not a date' => [[['price' => '100', 'from' => 'last week']]];
        yield 'entry that is not an array' => [['100']];
    }

    /** Entries that are not a price and a date are left out, so they never become the reference. */
    #[DataProvider('brokenHistories')]
    public function testIgnoresEntriesThatAreNotAPriceAndADate(mixed $stored): void
    {
        $this->histories[self::PRODUCT_ID] = $stored;

        self::assertSame(300.0, $this->lowest_price()->reference(self::product(self::PRODUCT_ID)));
    }

    public function testRecordsAChangeOfTheActivePrice(): void
    {
        $this->histories[self::PRODUCT_ID] = [self::entry(self::REGULAR, '-20 days')];

        $this->lowest_price()->record_saved(self::PRODUCT_ID, self::product(self::PRODUCT_ID));

        self::assertSame(
            [[self::PRODUCT_ID, LowestPrice::HISTORY_META, [self::entry(self::REGULAR, '-20 days'), self::entry(self::SALE, '+0 days')]]],
            $this->written
        );
    }

    public function testRecordsTheFirstPriceItSees(): void
    {
        $this->lowest_price()->record_saved(self::PRODUCT_ID, self::product(self::PRODUCT_ID, 'simple', false, self::REGULAR));

        self::assertSame([[self::PRODUCT_ID, LowestPrice::HISTORY_META, [self::entry(self::REGULAR, '+0 days')]]], $this->written);
    }

    public function testRecordsNothingWhenThePriceIsUnchanged(): void
    {
        $this->histories[self::PRODUCT_ID] = [self::entry('200.00', '-20 days')];

        $this->lowest_price()->record_saved(self::PRODUCT_ID, self::product(self::PRODUCT_ID));

        self::assertSame([], $this->written);
    }

    /** @return iterable<string, array{string, string}> */
    public static function productsWithoutAPriceOfTheirOwn(): iterable
    {
        yield 'variable' => ['variable', self::SALE];
        yield 'grouped' => ['grouped', self::SALE];
        yield 'no price' => ['simple', ''];
    }

    #[DataProvider('productsWithoutAPriceOfTheirOwn')]
    public function testRecordsNothingForAProductWithoutAPriceOfItsOwn(string $type, string $price): void
    {
        $this->lowest_price()->record_saved(self::PRODUCT_ID, self::product(self::PRODUCT_ID, $type, false, $price));

        self::assertSame([], $this->written);
    }

    /**
     * Entries that ended more than 30 days before the newest change are
     * dropped; the one in effect when the window opened is kept.
     */
    public function testKeepsOnlyTheEntriesTheWindowNeeds(): void
    {
        $history = [
            self::entry('100', '-120 days'),
            self::entry('150', '-90 days'),
            self::entry(self::REGULAR, '-40 days'),
        ];

        self::assertSame(
            [self::entry(self::REGULAR, '-40 days'), self::entry(self::SALE, '+0 days')],
            LowestPrice::appended($history, self::SALE, self::now())
        );
    }

    public function testRecordsTheProductsScheduledByWooCommerce(): void
    {
        Functions\when('wc_get_product')->alias(static fn (int $id): WC_Product|false => self::PRODUCT_ID === $id ? self::product($id) : false);
        $lowest_price = $this->lowest_price();
        $lowest_price->record_scheduled(self::PRODUCT_ID);
        $lowest_price->record_scheduled_run([self::PRODUCT_ID, 404]);

        // The stand-in's history is never stored, so each run records the sale price again; 404 is no product.
        self::assertSame([self::PRODUCT_ID, self::PRODUCT_ID], array_column($this->written, 0));
    }

    public function testNoteForTheProductAnAddressLinksTo(): void
    {
        $this->histories[self::PRODUCT_ID] = [self::entry('250', '-20 days'), self::entry(self::SALE, '-2 days')];
        Functions\when('url_to_postid')->alias(static fn (string $url): int => 'https://store.example/product/tee/' === $url ? self::PRODUCT_ID : 0);
        Functions\when('wc_get_product')->alias(static fn (int $id): WC_Product|false => self::PRODUCT_ID === $id ? self::product($id) : false);
        $lowest_price = $this->lowest_price();

        self::assertSame(
            '<span class="pixonaut-shop-lowest-price">' . self::LABEL . ': <span class="amount">250&nbsp;kr</span></span>',
            $lowest_price->for_url('https://store.example/product/tee/')
        );
        self::assertNull($lowest_price->for_url('https://store.example/shop/'));
    }

    public function testDuplicateStartsWithoutTheHistory(): void
    {
        self::assertSame(['_other', LowestPrice::HISTORY_META], $this->lowest_price()->without_history(['_other']));
    }

    public function testRegistersOnTheSavesTheScheduledSalesAndThePrices(): void
    {
        $lowest_price = $this->lowest_price();
        $lowest_price->register();

        foreach (['woocommerce_new_product', 'woocommerce_update_product', 'woocommerce_new_product_variation', 'woocommerce_update_product_variation'] as $hook) {
            self::assertNotFalse(has_action($hook, [$lowest_price, 'record_saved']), $hook);
        }

        foreach (['wc_product_start_scheduled_sale', 'wc_product_end_scheduled_sale'] as $hook) {
            // After WooCommerce's own callback, which changes the price.
            self::assertSame(20, has_action($hook, [$lowest_price, 'record_scheduled']), $hook);
        }

        foreach (['wc_after_products_starting_sales', 'wc_after_products_ending_sales'] as $hook) {
            self::assertNotFalse(has_action($hook, [$lowest_price, 'record_scheduled_run']), $hook);
        }

        self::assertNotFalse(has_filter('woocommerce_get_price_html', [$lowest_price, 'with_price_html']));
        self::assertNotFalse(has_filter('woocommerce_get_item_data', [$lowest_price, 'with_item_data']));
        self::assertNotFalse(has_filter('woocommerce_duplicate_product_exclude_meta', [$lowest_price, 'without_history']));
    }
}
