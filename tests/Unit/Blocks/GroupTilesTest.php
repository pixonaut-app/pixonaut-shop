<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Blocks;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Pixonaut\Shop\Blocks\GroupTiles;
use Pixonaut\Shop\Tests\TestCase;

#[CoversClass(GroupTiles::class)]
final class GroupTilesTest extends TestCase
{
    public function testHasNoCategoryOrHeadingUntilOneIsChosen(): void
    {
        self::assertSame(['parent' => '', 'heading' => ''], (new GroupTiles())->attributes([]));
    }

    public function testKeepsTheChoices(): void
    {
        self::assertSame(
            ['parent' => 'occasion', 'heading' => 'Tillfälle'],
            (new GroupTiles())->attributes(['parent' => 'occasion', 'heading' => 'Tillfälle'])
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidAttributes(): iterable
    {
        yield 'a category that is not a slug' => [['parent' => 12]];
        yield 'a heading that is not text' => [['heading' => ['Occasion']]];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    #[DataProvider('invalidAttributes')]
    public function testIgnoresAnInvalidChoice(array $attributes): void
    {
        self::assertSame(['parent' => '', 'heading' => ''], (new GroupTiles())->attributes($attributes));
    }

    public function testRendersNothingWithoutAChosenCategory(): void
    {
        Functions\expect('get_terms')->never();

        self::assertSame('', (new GroupTiles())->render([]));
    }
}
