<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Product;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use Pixonaut\Shop\Product\PriceRow;
use Pixonaut\Shop\Tests\TestCase;

#[CoversClass(PriceRow::class)]
final class PriceRowTest extends TestCase
{
    private const PRICE_HTML = '<span class="woocommerce-Price-amount amount"><bdi>249&nbsp;kr</bdi></span>';

    protected function setUp(): void
    {
        parent::setUp();
        Functions\when('wp_kses_post')->returnArg();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['product']);
        parent::tearDown();
    }

    public function testOpensTheRowBeforeTheQuantityAndClosesItAfterTheButton(): void
    {
        (new PriceRow())->register();

        self::assertSame('woocommerce_before_add_to_cart_form', PriceRow::FORM_HOOK);
        self::assertNotFalse(has_action(PriceRow::FORM_HOOK, 'Pixonaut\Shop\Product\PriceRow->start_form()'));
        self::assertSame('woocommerce_before_add_to_cart_quantity', PriceRow::OPEN_HOOK);
        self::assertSame('woocommerce_after_add_to_cart_button', PriceRow::CLOSE_HOOK);
        self::assertNotFalse(has_action(PriceRow::OPEN_HOOK, 'Pixonaut\Shop\Product\PriceRow->open()'));
        self::assertNotFalse(has_action(PriceRow::CLOSE_HOOK, 'Pixonaut\Shop\Product\PriceRow->close()'));
        self::assertNotFalse(has_filter(PriceRow::FORM_BLOCK_FILTER, 'Pixonaut\Shop\Product\PriceRow->with_price()'));
    }

    public function testPrintsTheProductsPriceInTheTargetThePluginUpdates(): void
    {
        $GLOBALS['product'] = self::product('simple');

        $output = $this->row(new PriceRow());

        self::assertStringStartsWith('<div class="pixonaut-shop-buy-row">', $output);
        self::assertStringContainsString(
            '<div class="pixonaut-shop-price" data-pixonaut-price>' . self::PRICE_HTML . '</div>',
            $output
        );
        self::assertStringContainsString('<div class="pixonaut-shop-buy-row__actions">', $output);
        self::assertStringEndsWith('</div>', $output);
        self::assertSame(substr_count($output, '<div'), substr_count($output, '</div>'));
    }

    public function testPrintsTheRowForAVariableProduct(): void
    {
        $GLOBALS['product'] = self::product('variable');

        self::assertStringContainsString('data-pixonaut-price', $this->row(new PriceRow()));
    }

    /**
     * An external product's form fires the button's hooks but not the
     * quantity's, so the row never opens and nothing is closed.
     */
    public function testClosesOnlyARowItOpened(): void
    {
        $GLOBALS['product'] = self::product('simple');
        $row = new PriceRow();

        ob_start();
        $row->close();

        self::assertSame('', ob_get_clean());
    }

    /**
     * A grouped product's form fires the quantity's hook once per product in
     * its table, each a simple product set as the global `$product` in turn.
     */
    public function testPrintsNothingInAGroupedProductsTable(): void
    {
        $row = new PriceRow();
        $GLOBALS['product'] = self::product('grouped');
        $row->start_form();
        $GLOBALS['product'] = self::product('simple');

        ob_start();
        $row->open();
        $row->open();
        $row->close();

        self::assertSame('', ob_get_clean());
    }

    public function testOpensOneRowPerForm(): void
    {
        $GLOBALS['product'] = self::product('simple');
        $row = new PriceRow();
        $row->start_form();

        ob_start();
        $row->open();
        $row->open();
        $row->close();
        $output = (string) ob_get_clean();

        self::assertSame(1, substr_count($output, 'data-pixonaut-price'));
    }

    public function testShowsThePriceAboveAFormThatPrintedNoRow(): void
    {
        $GLOBALS['product'] = self::product('external');
        $row = new PriceRow();

        self::assertSame('render_block_woocommerce/add-to-cart-form', PriceRow::FORM_BLOCK_FILTER);
        self::assertSame(
            '<div class="pixonaut-shop-price">' . self::PRICE_HTML . '</div><form class="cart"></form>',
            $row->with_price('<form class="cart"></form>')
        );
    }

    public function testLeavesAFormThatPrintedItsRowAsItIs(): void
    {
        $GLOBALS['product'] = self::product('simple');
        $row = new PriceRow();
        $this->row($row);

        self::assertSame('<form class="cart"></form>', $row->with_price('<form class="cart"></form>'));
        self::assertStringStartsWith(
            '<div class="pixonaut-shop-price">',
            $row->with_price('<p class="stock out-of-stock">Out of stock</p>'),
            'The next form, which printed no row, shows the price again.'
        );
    }

    public function testShowsNoPriceForAProductWithoutOne(): void
    {
        $GLOBALS['product'] = self::product('simple', '');

        self::assertSame('<p class="stock"></p>', (new PriceRow())->with_price('<p class="stock"></p>'));
    }

    public function testPrintsNothingWithoutAProduct(): void
    {
        self::assertSame('', $this->row(new PriceRow()));
    }

    private function row(PriceRow $row): string
    {
        $row->start_form();
        ob_start();
        $row->open();
        $row->close();

        return (string) ob_get_clean();
    }

    /**
     * A stand-in for the `WC_Product` WooCommerce sets as the global
     * `$product` while it renders the add-to-cart form.
     */
    private static function product(string $type, string $price_html = self::PRICE_HTML): object
    {
        return new class ($type, $price_html) {
            public function __construct(private string $type, private string $price_html)
            {
            }

            /** @param string|list<string> $types */
            public function is_type(string|array $types): bool
            {
                return \in_array($this->type, (array) $types, true);
            }

            public function get_price_html(): string
            {
                return $this->price_html;
            }
        };
    }
}
