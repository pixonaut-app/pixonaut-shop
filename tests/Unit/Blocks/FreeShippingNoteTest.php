<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Blocks;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Pixonaut\Shop\Blocks\FreeShippingNote;
use Pixonaut\Shop\Tests\TestCase;

#[CoversClass(FreeShippingNote::class)]
final class FreeShippingNoteTest extends TestCase
{
    /** A free-shipping method's minimum order amount, in the store's currency. */
    private const MINIMUM = 499.0;

    /** WooCommerce's default number of decimals in a price. */
    private const DECIMALS = 2;

    public function testExtendsTheStoreApiCartOnInit(): void
    {
        $note = new FreeShippingNote();
        $note->register();

        self::assertNotFalse(has_action('init', [$note, 'extend_store_api']));
    }

    public function testSaysHowMuchIsLeftBelowTheMinimum(): void
    {
        self::assertSame(250.0, (new FreeShippingNote())->remaining(249.0, self::MINIMUM, 'min_amount', false));
    }

    /**
     * @return iterable<string, array{float}>
     */
    public static function totalsAtOrAboveTheMinimum(): iterable
    {
        yield 'at the minimum' => [self::MINIMUM];
        yield 'above the minimum' => [self::MINIMUM + 1];
    }

    #[DataProvider('totalsAtOrAboveTheMinimum')]
    public function testSaysShippingIsFreeFromTheMinimum(float $total): void
    {
        self::assertSame(0.0, (new FreeShippingNote())->remaining($total, self::MINIMUM, 'min_amount', false));
    }

    public function testAFreeShippingCouponMakesShippingFreeWhenEitherWillDo(): void
    {
        self::assertSame(0.0, (new FreeShippingNote())->remaining(100.0, self::MINIMUM, 'either', true));
    }

    public function testCountsTheMinimumWhenEitherWillDoWithoutACoupon(): void
    {
        self::assertSame(399.0, (new FreeShippingNote())->remaining(100.0, self::MINIMUM, 'either', false));
    }

    public function testCountsTheMinimumOnceTheRequiredCouponIsApplied(): void
    {
        self::assertSame(399.0, (new FreeShippingNote())->remaining(100.0, self::MINIMUM, 'both', true));
    }

    /**
     * @return iterable<string, array{float, string, bool}>
     */
    public static function methodsWithNoMinimumToReach(): iterable
    {
        yield 'a coupon is required and not applied' => [self::MINIMUM, 'both', false];
        yield 'only a coupon gives free shipping' => [self::MINIMUM, 'coupon', true];
        yield 'shipping is free without conditions' => [self::MINIMUM, '', false];
        yield 'the minimum is zero' => [0.0, 'min_amount', false];
        yield 'an unknown requirement' => [self::MINIMUM, 'membership', false];
    }

    #[DataProvider('methodsWithNoMinimumToReach')]
    public function testSaysNothingWithoutAMinimumTheCartCanReach(float $minimum, string $requires, bool $has_coupon): void
    {
        self::assertNull((new FreeShippingNote())->remaining(100.0, $minimum, $requires, $has_coupon));
    }

    public function testComparesTheSubtotalAfterDiscounts(): void
    {
        self::assertSame(
            450.0,
            (new FreeShippingNote())->cart_total(500.0, 50.0, 12.5, false, false, self::DECIMALS)
        );
    }

    public function testTakesTheDiscountsTaxOffWhenPricesIncludeTax(): void
    {
        self::assertSame(
            437.5,
            (new FreeShippingNote())->cart_total(500.0, 50.0, 12.5, true, false, self::DECIMALS)
        );
    }

    public function testComparesTheSubtotalBeforeDiscountsWhenTheMethodIgnoresThem(): void
    {
        self::assertSame(
            500.0,
            (new FreeShippingNote())->cart_total(500.0, 50.0, 12.5, true, true, self::DECIMALS)
        );
    }

    public function testRoundsTheTotalToThePricesDecimals(): void
    {
        self::assertSame(
            498.99,
            (new FreeShippingNote())->cart_total(498.987, 0.0, 0.0, false, false, self::DECIMALS)
        );
    }

    public function testNamesTheAmountLeft(): void
    {
        Functions\stubTranslationFunctions();

        self::assertSame(
            'Add 250 kr more for free shipping',
            (new FreeShippingNote())->message(250.0, '250 kr')
        );
    }

    public function testSaysShippingIsFreeWithNothingLeft(): void
    {
        Functions\stubTranslationFunctions();

        self::assertSame('You get free shipping', (new FreeShippingNote())->message(0.0, '0 kr'));
    }
}
