<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Pixonaut;

use PHPUnit\Framework\Attributes\CoversClass;
use Pixonaut\Shop\Pixonaut\Integration;
use Pixonaut\Shop\Tests\TestCase;
use stdClass;

#[CoversClass(Integration::class)]
final class IntegrationTest extends TestCase
{
    public function testRegistersOnThePluginsCartItemDisplayHook(): void
    {
        (new Integration())->register();

        self::assertSame('pixonaut_wc_cart_item_display', Integration::CART_ITEM_DISPLAY);
        self::assertNotFalse(
            has_filter(Integration::CART_ITEM_DISPLAY, 'Pixonaut\Shop\Pixonaut\Integration->cart_item_display()')
        );
    }

    public function testRegistersOnThePluginsFulfilmentLineDisplayHook(): void
    {
        (new Integration())->register();

        self::assertSame('pixonaut_wc_fulfilment_line_display', Integration::FULFILMENT_LINE_DISPLAY);
        self::assertNotFalse(
            has_filter(Integration::FULFILMENT_LINE_DISPLAY, 'Pixonaut\Shop\Pixonaut\Integration->fulfilment_line_display()')
        );
    }

    public function testLeavesACartItemsRowsAsThePluginPrintsThem(): void
    {
        $rows = [['key' => 'Front', 'value' => 'https://proof.test/front.png', 'display' => '<a class="pixonaut-proof-link">View proof</a>']];

        self::assertSame($rows, (new Integration())->cart_item_display($rows));
    }

    public function testMarksAnOrderLinesStateAndTrackingRowsForTheTheme(): void
    {
        $state = self::row('Status', 'printing', 'Printing');
        $tracking = self::row('Tracking', 'https://track.test/1', '<a href="https://track.test/1">Track parcel</a>');
        $rows = [Integration::LINE_STATE_ROW => $state, Integration::TRACKING_ROW => $tracking];

        $displayed = (new Integration())->fulfilment_line_display($rows);

        self::assertSame('<span class="pixonaut-shop-line-state">Printing</span>', $displayed[Integration::LINE_STATE_ROW]->display_value);
        self::assertSame('<span class="pixonaut-shop-line-tracking"><a href="https://track.test/1">Track parcel</a></span>', $displayed[Integration::TRACKING_ROW]->display_value);
        self::assertSame('Status', $displayed[Integration::LINE_STATE_ROW]->display_key);
        self::assertSame('printing', $displayed[Integration::LINE_STATE_ROW]->value);
        self::assertSame('Printing', $state->display_value, 'The plugin\'s own row is not changed in place.');
    }

    /**
     * A design's field rows, WooCommerce's and other plugins' rows are
     * ordinary detail lines, styled as they are.
     */
    public function testLeavesAnOrderLinesOtherRowsAsThePluginPrintsThem(): void
    {
        $rows = [
            'pixonaut_field_front_0' => self::row('Name', 'Alva', 'Alva'),
            42                       => self::row('Colour', 'blue', 'Blue'),
        ];

        self::assertSame($rows, (new Integration())->fulfilment_line_display($rows));
    }

    private static function row(string $key, string $value, string $display_value): stdClass
    {
        $row = new stdClass();
        $row->key = $key;
        $row->value = $value;
        $row->display_key = $key;
        $row->display_value = $display_value;

        return $row;
    }
}
