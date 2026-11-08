<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Header;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Pixonaut\Shop\Header\HeaderVariant;
use Pixonaut\Shop\Tests\TestCase;

#[CoversClass(HeaderVariant::class)]
final class HeaderVariantTest extends TestCase
{
    public function testHeaderVariantDefaultsToSimple(): void
    {
        Functions\expect('get_option')->once()->with('pixonaut_shop_header')->andReturn(false);

        self::assertSame('simple', HeaderVariant::current());
    }

    public function testASimpleHeaderIsSimple(): void
    {
        Functions\when('get_option')->justReturn('simple');

        self::assertSame(HeaderVariant::SIMPLE, HeaderVariant::current());
    }

    /** The mega menu's header is not built yet, so a store that asks for it gets the simple one. */
    public function testAMegaHeaderIsSimpleUntilTheMegaHeaderExists(): void
    {
        Functions\when('get_option')->justReturn('mega');

        self::assertSame(HeaderVariant::SIMPLE, HeaderVariant::current());
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function unknownVariants(): iterable
    {
        yield 'another name' => ['wide'];
        yield 'an empty string' => [''];
        yield 'a number' => [1];
        yield 'a list' => [['mega']];
    }

    #[DataProvider('unknownVariants')]
    public function testAnUnknownVariantIsSimple(mixed $value): void
    {
        Functions\when('get_option')->justReturn($value);

        self::assertSame(HeaderVariant::SIMPLE, HeaderVariant::current());
    }
}
