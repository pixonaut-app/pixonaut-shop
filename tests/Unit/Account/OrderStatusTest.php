<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Account;

use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use Pixonaut\Shop\Account\OrderStatus;
use Pixonaut\Shop\Pixonaut\Integration;
use Pixonaut\Shop\Tests\TestCase;
use WC_Order;
use WC_Order_Item_Product;

#[CoversClass(OrderStatus::class)]
final class OrderStatusTest extends TestCase
{
    /** Pixonaut for WooCommerce's default labels for the states these tests use. */
    private const STATE_LABELS = [
        'needs_attention' => 'Being reviewed by the store',
        'approved'        => 'Design approved',
        'printed'         => 'Print file ready',
        'ordered'         => 'In production',
        'shipped'         => 'Shipped',
        'cancelled'       => 'Cancelled',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Functions\stubEscapeFunctions();
        Functions\when('wc_get_order_status_name')->alias('ucfirst');
        Functions\when('wp_kses')->alias(static fn (string $html): string => strip_tags($html));
    }

    public function testRegistersOnTheOrdersTablesStatusColumn(): void
    {
        (new OrderStatus())->register();

        self::assertNotFalse(
            has_action('woocommerce_my_account_my_orders_column_order-status', 'Pixonaut\Shop\Account\OrderStatus->render()')
        );
    }

    public function testMarksAnOpenOrdersStatus(): void
    {
        self::assertSame(
            '<mark class="order-status status-processing">Processing</mark>',
            $this->render($this->order('processing'))
        );
    }

    public function testMarksAFinishedOrdersStatusAsDone(): void
    {
        self::assertSame(
            '<mark class="order-status status-completed is-done">Completed</mark>',
            $this->render($this->order('completed'))
        );
    }

    public function testShowsWooCommercesStatusForLinesWithoutAState(): void
    {
        self::assertSame(
            '<mark class="order-status status-processing">Processing</mark>',
            $this->render($this->order('processing', [null, null]))
        );
    }

    public function testShowsTheLeastAdvancedLinesStateInsteadOfWooCommercesStatus(): void
    {
        self::assertSame(
            '<mark class="order-status status-processing">Design approved</mark>',
            $this->render($this->order('processing', ['ordered', 'approved', 'printed']))
        );
    }

    public function testLeavesALineWithoutAStateOutOfTheComparison(): void
    {
        self::assertSame(
            '<mark class="order-status status-processing">In production</mark>',
            $this->render($this->order('processing', [null, 'ordered']))
        );
    }

    /** A line the store has to look at holds the order up, however far the others are. */
    public function testALineNeedingAttentionWinsOverTheForwardPath(): void
    {
        self::assertSame(
            '<mark class="order-status status-processing">Being reviewed by the store</mark>',
            $this->render($this->order('processing', ['shipped', 'needs_attention']))
        );
    }

    /** A cancelled line holds nothing up: the order goes on with its other lines. */
    public function testACancelledLineGivesWayToTheOthers(): void
    {
        self::assertSame(
            '<mark class="order-status status-processing">Printing</mark>',
            $this->render($this->order('processing', ['cancelled', 'printed'], ['printed' => 'Printing']))
        );
    }

    /** A state the plugin adds later is still on its way, so it holds up a shipped or cancelled line. */
    public function testAStateTheThemeDoesNotKnowWinsOverTheFinishedStates(): void
    {
        self::assertSame(
            '<mark class="order-status status-processing">Being packed</mark>',
            $this->render($this->order('processing', ['shipped', 'packing', 'cancelled'], ['packing' => 'Being packed']))
        );
    }

    public function testAStateTheThemeDoesNotKnowGivesWayToTheForwardPath(): void
    {
        self::assertSame(
            '<mark class="order-status status-processing">Design approved</mark>',
            $this->render($this->order('processing', ['packing', 'approved'], ['packing' => 'Being packed']))
        );
    }

    public function testMarksTheStateAsDoneOnceEveryLineIsShipped(): void
    {
        self::assertSame(
            '<mark class="order-status status-processing is-done">Shipped</mark>',
            $this->render($this->order('processing', ['shipped', 'shipped']))
        );
    }

    /** The plugin's label is escaped HTML, which the theme wraps in its own span. */
    public function testPrintsTheStatesLabelWithoutItsMarkup(): void
    {
        self::assertSame(
            '<mark class="order-status status-processing">Design approved</mark>',
            $this->render($this->order('processing', ['approved'], ['approved' => '<span class="pixonaut-shop-line-state">Design approved</span>']))
        );
    }

    public function testTheLabelIsThePillsText(): void
    {
        self::assertSame('In production', (new OrderStatus())->label($this->order('processing', ['ordered'])));
        self::assertSame('Processing', (new OrderStatus())->label($this->order('processing')));
    }

    public function testThePillIsWhatTheColumnPrints(): void
    {
        $order = $this->order('processing', ['ordered']);

        self::assertSame($this->render($order), (new OrderStatus())->pill($order));
    }

    /**
     * An order whose lines carry these states, as the plugin's state row
     * among each line's displayed meta, or none for `null`.
     *
     * @param list<string|null>     $states
     * @param array<string, string> $labels The label shown for a state, where not the plugin's default.
     */
    private function order(string $status, array $states = [], array $labels = []): WC_Order
    {
        $items = [];

        foreach ($states as $state) {
            $rows = ['pixonaut_field_front_0' => (object) ['key' => 'Name', 'value' => 'Alex', 'display_key' => 'Name', 'display_value' => 'Alex']];

            if (null !== $state) {
                $rows[Integration::LINE_STATE_ROW] = (object) [
                    'key'           => 'Status',
                    'value'         => $state,
                    'display_key'   => 'Status',
                    'display_value' => $labels[$state] ?? self::STATE_LABELS[$state],
                ];
            }

            $item = Mockery::mock(WC_Order_Item_Product::class);
            $item->allows('get_formatted_meta_data')->andReturn($rows);
            $items[] = $item;
        }

        $order = Mockery::mock(WC_Order::class);
        $order->allows('get_status')->andReturn($status);
        $order->allows('get_items')->andReturn($items);

        return $order;
    }

    private function render(WC_Order $order): string
    {
        ob_start();
        (new OrderStatus())->render($order);

        return (string) ob_get_clean();
    }
}
