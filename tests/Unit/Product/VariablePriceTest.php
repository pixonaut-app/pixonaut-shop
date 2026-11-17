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

    /** The choice a store made in the Customizer before it was an option; `null` while it made none. */
    private ?string $theme_mod = null;

    protected function setUp(): void
    {
        parent::setUp();
        Functions\stubTranslationFunctions();
        Functions\stubEscapeFunctions();
        Functions\when('wc_price')->alias(static fn (float $price): string => '<span class="amount">' . $price . '&nbsp;kr</span>');
        Functions\when('get_option')->alias(fn (string $name, mixed $fallback = false): mixed => $this->saved ?? $fallback);
        Functions\when('get_theme_mod')->alias(fn (string $name, mixed $fallback = false): mixed => $this->theme_mod ?? $fallback);
    }

    public function testFiltersWooCommercesVariablePriceAndRegistersTheOption(): void
    {
        (new VariablePrice())->register();

        self::assertNotFalse(has_filter('woocommerce_variable_price_html', 'Pixonaut\Shop\Product\VariablePrice->with_price_html()'));
        self::assertNotFalse(has_filter('woocommerce_get_price_html_from_text', 'Pixonaut\Shop\Product\VariablePrice->from_text()'));
        self::assertNotFalse(has_action('customize_register', 'Pixonaut\Shop\Product\VariablePrice->customize()'));
        self::assertNotFalse(has_filter('woocommerce_get_settings_products', 'Pixonaut\Shop\Product\VariablePrice->with_product_settings()'));
        self::assertNotFalse(has_filter('sanitize_option_' . VariablePrice::SETTING, 'Pixonaut\Shop\Product\VariablePrice->sanitize()'));
        self::assertNotFalse(has_filter('default_option_' . VariablePrice::SETTING, 'Pixonaut\Shop\Product\VariablePrice->unsaved()'));
    }

    public function testAddsTheChoiceToWooCommercesGeneralProductSettings(): void
    {
        $existing = [['id' => 'woocommerce_shop_page_id', 'type' => 'single_select_page']];

        $settings = (new VariablePrice())->with_product_settings($existing, '');

        self::assertSame($existing[0], $settings[0]);
        $fields = array_column(\array_slice($settings, 1), null, 'type');
        self::assertSame(['title', 'radio', 'sectionend'], array_keys($fields));
        self::assertSame(VariablePrice::SETTING, $fields['radio']['id']);
        self::assertSame(VariablePrice::DEFAULT, $fields['radio']['default']);
        self::assertSame([VariablePrice::FROM, VariablePrice::FROM_SHORT, VariablePrice::RANGE], array_keys($fields['radio']['options']));
        self::assertSame($fields['title']['id'], $fields['sectionend']['id']);
    }

    public function testLeavesWooCommercesOtherProductSettingsAsTheyAre(): void
    {
        $existing = [['id' => 'woocommerce_manage_stock', 'type' => 'checkbox']];

        self::assertSame($existing, (new VariablePrice())->with_product_settings($existing, 'inventory'));
        self::assertSame('not settings', (new VariablePrice())->with_product_settings('not settings', ''));
    }

    /** A store that chose in the Customizer before the choice was an option keeps it until it chooses again. */
    public function testAnUnsavedChoiceIsTheOneMadeInTheCustomizerBefore(): void
    {
        self::assertSame(VariablePrice::FROM, (new VariablePrice())->unsaved(VariablePrice::FROM));
        self::assertFalse((new VariablePrice())->unsaved(false));

        $this->theme_mod = VariablePrice::RANGE;

        self::assertSame(VariablePrice::RANGE, (new VariablePrice())->unsaved(VariablePrice::FROM));
        self::assertSame(VariablePrice::RANGE, (new VariablePrice())->unsaved(false));
    }

    public function testShowsFromTheLowestPriceInFullByDefault(): void
    {
        self::assertSame(
            '<span class="from">From </span><span class="amount">299&nbsp;kr</span>' . self::SUFFIX,
            (new VariablePrice())->with_price_html(self::RANGE_HTML, self::product(self::PRICES))
        );
    }

    public function testShowsTheShortFormWhenChosen(): void
    {
        $this->saved = VariablePrice::FROM_SHORT;

        self::assertSame(
            '<span class="from">Fr. </span><span class="amount">299&nbsp;kr</span>' . self::SUFFIX,
            (new VariablePrice())->with_price_html(self::RANGE_HTML, self::product(self::PRICES))
        );
    }

    /** WooCommerce's own "From:", as a grouped product or a plugin's card prints it, says what the store chose. */
    #[DataProvider('fromWords')]
    public function testWooCommercesFromTextSaysTheChosenWord(?string $choice, string $text): void
    {
        $this->saved = $choice;

        self::assertSame($text, (new VariablePrice())->from_text());
    }

    /** @return array<string, array{?string, string}> */
    public static function fromWords(): array
    {
        return [
            'by default'            => [null, '<span class="from">From </span>'],
            'short'                 => [VariablePrice::FROM_SHORT, '<span class="from">Fr. </span>'],
            'in full for the range' => [VariablePrice::RANGE, '<span class="from">From </span>'],
        ];
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
        self::assertSame('option', $added['setting'][VariablePrice::SETTING]['type']);
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
