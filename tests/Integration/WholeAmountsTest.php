<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Prices in whole amounts where they are whole, with the store's decimals
 * where they are not.
 */
final class WholeAmountsTest extends TestCase
{
    private const DECIMALS = 'woocommerce_price_num_decimals';

    private const DECIMAL_SEPARATOR = 'woocommerce_price_decimal_sep';

    private mixed $decimals;

    private mixed $separator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->decimals = get_option(self::DECIMALS);
        $this->separator = get_option(self::DECIMAL_SEPARATOR);
        update_option(self::DECIMALS, 2);
        update_option(self::DECIMAL_SEPARATOR, ',');
    }

    protected function tearDown(): void
    {
        update_option(self::DECIMALS, $this->decimals);
        update_option(self::DECIMAL_SEPARATOR, $this->separator);
        parent::tearDown();
    }

    public function testAWholeAmountShowsNoDecimals(): void
    {
        self::assertStringNotContainsString(',00', wc_price(389));
        self::assertMatchesRegularExpression('/389(?!,)/', wp_strip_all_tags(wc_price(389)));
    }

    public function testAnAmountWithAFractionKeepsTheStoresDecimals(): void
    {
        self::assertStringContainsString('389,50', wp_strip_all_tags(wc_price(389.5)));
    }
}
