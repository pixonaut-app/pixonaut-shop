<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Product;

use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Pixonaut\Shop\Product\VariablePrice;
use Pixonaut\Shop\Tests\TestCase;
use stdClass;
use WC_Product;
use WP_Customize_Manager;

/**
 * How a variable product's price shows before a variation is chosen: "from"
 * its lowest price, in full or short, or the range WooCommerce prints.
 */
#[CoversClass(VariablePrice::class)]
final class VariablePriceTest extends TestCase
{
    private const RANGE_HTML = '<span class="amount">299&nbsp;kr</span> – <span class="amount">749&nbsp;kr</span>';

    private const SUFFIX = ' <small class="woocommerce-price-suffix">incl. VAT</small>';

    /** The prices of the variable product's variations, cheapest first, as WooCommerce sorts them. */
    private const PRICES = [11 => '299', 12 => '449', 13 => '749'];

    /** The saved choice in a test; `null` while none is saved. */
    private ?string $saved = null;

    protected function setUp(): void
    {
        parent::setUp();
        Functions\stubTranslationFunctions();
        Functions\stubEscapeFunctions();
        Functions\when('wc_price')->alias(static fn (float $price): string => '<span class="amount">' . $price . '&nbsp;kr</span>');
        Functions\when('get_theme_mod')->alias(fn (string $name, mixed $fallback = false): mixed => $this->saved ?? $fallback);
    }

    public function testFiltersWooCommercesVariablePriceAndRegistersTheOption(): void
    {
        (new VariablePrice())->register();

        self::assertNotFalse(has_filter('woocommerce_variable_price_html', 'Pixonaut\Shop\Product\VariablePrice->with_price_html()'));
        self::assertNotFalse(has_action('customize_register', 'Pixonaut\Shop\Product\VariablePrice->customize()'));
    }

    public function testShowsFromTheLowestPriceInFullByDefault(): void
    {
        self::assertSame(
            'From <span class="amount">299&nbsp;kr</span>' . self::SUFFIX,
            (new VariablePrice())->with_price_html(self::RANGE_HTML, self::product(self::PRICES))
        );
    }

    public function testShowsTheShortFormWhenChosen(): void
    {
        $this->saved = VariablePrice::FROM_SHORT;

        self::assertSame(
            'Fr. <span class="amount">299&nbsp;kr</span>' . self::SUFFIX,
            (new VariablePrice())->with_price_html(self::RANGE_HTML, self::product(self::PRICES))
        );
    }

    public function testLeavesTheRangeWhenChosen(): void
    {
        $this->saved = VariablePrice::RANGE;

        self::assertSame(self::RANGE_HTML, (new VariablePrice())->with_price_html(self::RANGE_HTML, self::product(self::PRICES)));
    }

    /**
     * Variations all at one price have no range: WooCommerce shows that one
     * price, on sale or not, and it stays as it is.
     */
    public function testLeavesASinglePriceAsItIs(): void
    {
        $html = '<span class="amount">299&nbsp;kr</span>';

        self::assertSame($html, (new VariablePrice())->with_price_html($html, self::product([11 => '299', 12 => '299'])));
        self::assertSame('', (new VariablePrice())->with_price_html('', self::product([])));
    }

    public function testLeavesAnythingButAVariableProductAsItIs(): void
    {
        $simple = Mockery::mock(WC_Product::class);
        $simple->allows('is_type')->with('variable')->andReturn(false);

        self::assertSame(self::RANGE_HTML, (new VariablePrice())->with_price_html(self::RANGE_HTML, $simple));
        self::assertSame(self::RANGE_HTML, (new VariablePrice())->with_price_html(self::RANGE_HTML, null));
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function savedValues(): iterable
    {
        yield 'from' => [VariablePrice::FROM, VariablePrice::FROM];
        yield 'short' => [VariablePrice::FROM_SHORT, VariablePrice::FROM_SHORT];
        yield 'range' => [VariablePrice::RANGE, VariablePrice::RANGE];
        yield 'unknown' => ['cheapest', VariablePrice::FROM];
        yield 'not a string' => [['range'], VariablePrice::FROM];
    }

    #[DataProvider('savedValues')]
    public function testKeepsOnlyAChoiceItKnows(mixed $saved, string $expected): void
    {
        self::assertSame($expected, (new VariablePrice())->sanitize($saved));
    }

    public function testAddsTheOptionToWooCommercesPanelInTheCustomizer(): void
    {
        $added = $this->customize(new stdClass());

        self::assertSame('woocommerce', $added['section'][VariablePrice::SECTION]['panel'] ?? null);
        self::assertSame('theme_mod', $added['setting'][VariablePrice::SETTING]['type']);
        self::assertSame(VariablePrice::FROM, $added['setting'][VariablePrice::SETTING]['default']);
        self::assertIsCallable($added['setting'][VariablePrice::SETTING]['sanitize_callback']);
        self::assertSame(VariablePrice::SECTION, $added['control'][VariablePrice::SETTING]['section']);
        self::assertSame('radio', $added['control'][VariablePrice::SETTING]['type']);
        self::assertSame(
            [VariablePrice::FROM, VariablePrice::FROM_SHORT, VariablePrice::RANGE],
            array_keys($added['control'][VariablePrice::SETTING]['choices'])
        );
    }

    public function testAddsTheSectionOnItsOwnWithoutWooCommercesPanel(): void
    {
        $added = $this->customize(null);

        self::assertArrayHasKey(VariablePrice::SECTION, $added['section']);
        self::assertArrayNotHasKey('panel', $added['section'][VariablePrice::SECTION]);
    }

    /**
     * What the option adds to a Customizer whose WooCommerce panel is `$panel`.
     *
     * @return array<string, array<string, array<string, mixed>>> Each added section, setting and control's arguments, by kind and ID.
     */
    private function customize(?object $panel): array
    {
        $added = ['section' => [], 'setting' => [], 'control' => []];
        $manager = Mockery::mock(WP_Customize_Manager::class);
        $manager->allows('get_panel')->with('woocommerce')->andReturn($panel);

        foreach (array_keys($added) as $kind) {
            $manager->allows('add_' . $kind)->andReturnUsing(static function (string $id, array $args) use (&$added, $kind): void {
                $added[$kind][$id] = $args;
            });
        }

        (new VariablePrice())->customize($manager);

        return $added;
    }

    /**
     * A variable product whose variations have these prices.
     *
     * @param array<int, string> $prices
     */
    private static function product(array $prices): WC_Product
    {
        $product = Mockery::mock(WC_Product::class);
        $product->allows('is_type')->with('variable')->andReturn(true);
        $product->allows('get_variation_prices')->with(true)->andReturn(['price' => $prices, 'regular_price' => $prices, 'sale_price' => $prices]);
        $product->allows('get_price_suffix')->andReturn(self::SUFFIX);

        return $product;
    }
}
