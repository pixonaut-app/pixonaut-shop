<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Blocks;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Pixonaut\Shop\Blocks\ProductCategories;
use Pixonaut\Shop\Tests\TestCase;

#[CoversClass(ProductCategories::class)]
final class ProductCategoriesTest extends TestCase
{
    /** The category WooCommerce files a product under when it has none. */
    private const DEFAULT_CATEGORY = 15;

    public function testShowsFourTilesByProductCountByDefault(): void
    {
        self::assertSame(
            ['layout' => 'tiles', 'number' => 4, 'orderBy' => 'count'],
            (new ProductCategories())->attributes([])
        );
    }

    public function testShowsSixButtonsByDefault(): void
    {
        self::assertSame(
            ['layout' => 'buttons', 'number' => 6, 'orderBy' => 'count'],
            (new ProductCategories())->attributes(['layout' => 'buttons'])
        );
    }

    public function testKeepsTheStoresChoices(): void
    {
        self::assertSame(
            ['layout' => 'buttons', 'number' => 3, 'orderBy' => 'order'],
            (new ProductCategories())->attributes(['layout' => 'buttons', 'number' => 3, 'orderBy' => 'order'])
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidAttributes(): iterable
    {
        yield 'an unknown layout' => [['layout' => 'carousel']];
        yield 'an unknown order' => [['orderBy' => 'price']];
        yield 'no categories' => [['number' => 0]];
        yield 'a negative number' => [['number' => -2]];
        yield 'a number that is not one' => [['number' => 'many']];
        yield 'a layout that is not a string' => [['layout' => ['tiles']]];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    #[DataProvider('invalidAttributes')]
    public function testFallsBackToTheDefaultForAnInvalidChoice(array $attributes): void
    {
        self::assertSame(
            ['layout' => 'tiles', 'number' => 4, 'orderBy' => 'count'],
            (new ProductCategories())->attributes($attributes)
        );
    }

    public function testQueriesEveryTopLevelCategoryInTheStoresOwnOrder(): void
    {
        $categories = new ProductCategories();

        self::assertSame(
            [
                'taxonomy'   => 'product_cat',
                'parent'     => 0,
                'hide_empty' => false,
                'exclude'    => [self::DEFAULT_CATEGORY],
                'orderby'    => 'menu_order',
                'order'      => 'ASC',
            ],
            $categories->query_args($categories->attributes([]), self::DEFAULT_CATEGORY)
        );
    }

    public function testQueriesTheSameCategoriesWhicheverOrderIsChosen(): void
    {
        $categories = new ProductCategories();

        self::assertSame(
            $categories->query_args($categories->attributes(['orderBy' => 'count']), self::DEFAULT_CATEGORY),
            $categories->query_args(
                $categories->attributes(['layout' => 'buttons', 'orderBy' => 'order']),
                self::DEFAULT_CATEGORY
            )
        );
    }

    public function testExcludesNothingWithoutADefaultCategory(): void
    {
        $categories = new ProductCategories();

        self::assertSame([], $categories->query_args($categories->attributes([]), 0)['exclude']);
    }
}
