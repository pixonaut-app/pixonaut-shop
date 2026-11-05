<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Orders;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Pixonaut\Shop\Orders\OrderReceivedBand;
use Pixonaut\Shop\Tests\TestCase;

#[CoversClass(OrderReceivedBand::class)]
final class OrderReceivedBandTest extends TestCase
{
    /** The check badge as the order-confirmation template saves it. */
    private const BADGE = '<div class="wp-block-group pixonaut-shop-check-badge is-layout-flow"></div>';

    /** The pill as the order-confirmation template saves it: an empty paragraph with the Pill style and the marker. */
    private const PILL = '<p class="is-style-pill pixonaut-shop-order-number"></p>';

    /** WooCommerce's summary, as it renders only for whoever may see the order. */
    private const SUMMARY = '<div class="wc-block-order-confirmation-summary"><ul class="wc-block-order-confirmation-summary-list"><li class="wc-block-order-confirmation-summary-list-item">1042</li></ul></div>';

    protected function setUp(): void
    {
        parent::setUp();
        Functions\stubTranslationFunctions();
        Functions\when('esc_html')->alias(static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES));
    }

    public function testRegistersOnTheGroupsRender(): void
    {
        (new OrderReceivedBand())->register();

        self::assertNotFalse(
            has_filter('render_block_core/group', 'Pixonaut\Shop\Orders\OrderReceivedBand->render()')
        );
    }

    public function testPrintsTheOrderNumberInThePillWhereWooCommerceShowsTheSummary(): void
    {
        $band = self::band(['number' => '1042', 'status' => 'processing']);

        self::assertSame(
            self::BADGE . '<p class="is-style-pill pixonaut-shop-order-number">Order 1042 received</p>' . self::SUMMARY,
            $band->render(self::BADGE . self::PILL . self::SUMMARY)
        );
    }

    public function testEscapesTheOrderNumber(): void
    {
        $band = self::band(['number' => '<b>7</b> $1 \\1', 'status' => 'processing']);

        self::assertStringContainsString(
            '>Order &lt;b&gt;7&lt;/b&gt; $1 \\1 received</p>',
            $band->render(self::PILL . self::SUMMARY)
        );
    }

    public function testPrintsNoPillWithoutAnOrder(): void
    {
        self::assertSame(self::BADGE . self::SUMMARY, self::band(null)->render(self::BADGE . self::PILL . self::SUMMARY));
    }

    /**
     * WooCommerce prints no summary to a visitor who may not see the order,
     * whatever else wraps or replaces it.
     */
    public function testPrintsNoPillWhereWooCommerceShowsNoSummary(): void
    {
        $band = self::band(['number' => '1042', 'status' => 'processing']);
        $wrapped = '<div class="wc-block-order-confirmation-summary store-wrapper"></div>';

        self::assertSame(self::BADGE . $wrapped, $band->render(self::BADGE . self::PILL . $wrapped));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsuccessfulStatuses(): iterable
    {
        yield 'failed' => ['failed'];
        yield 'cancelled' => ['cancelled'];
        yield 'refunded' => ['refunded'];
    }

    #[DataProvider('unsuccessfulStatuses')]
    public function testShowsNeitherCheckNorPillForAnOrderThatDidNotGoThrough(string $status): void
    {
        $band = self::band(['number' => '1042', 'status' => $status]);

        self::assertSame(self::SUMMARY, $band->render(self::BADGE . self::PILL . self::SUMMARY));
    }

    public function testLeavesEveryOtherGroupAlone(): void
    {
        $band = self::band(null);
        $group = '<div class="wp-block-group"><p class="is-style-pill pixonaut-shop-order-number-x"></p>' . self::BADGE . '</div>';

        self::assertSame($group, $band->render($group));
    }

    /**
     * A band whose pill was already filled in, as the group around it
     * renders it again, is left as it is.
     */
    public function testLeavesAFilledPillAlone(): void
    {
        $filled = '<p class="is-style-pill pixonaut-shop-order-number">Order 1042 received</p>' . self::SUMMARY;

        self::assertSame($filled, self::band(null)->render($filled));
    }

    /**
     * @param array{number: string, status: string}|null $order
     */
    private static function band(?array $order): OrderReceivedBand
    {
        return new OrderReceivedBand(static fn (): ?array => $order);
    }
}
