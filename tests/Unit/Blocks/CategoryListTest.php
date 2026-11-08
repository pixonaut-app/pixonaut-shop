<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Blocks;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use Pixonaut\Shop\Blocks\CategoryList;
use Pixonaut\Shop\Tests\TestCase;

#[CoversClass(CategoryList::class)]
final class CategoryListTest extends TestCase
{
    /** The category WooCommerce files a product under when it has none. */
    private const DEFAULT_CATEGORY = 15;

    private const PARENT = 40;

    public function testQueriesTheChildrenOfACategoryInTheStoresOwnOrder(): void
    {
        self::assertSame(
            [
                'taxonomy'   => 'product_cat',
                'parent'     => self::PARENT,
                'hide_empty' => false,
                'exclude'    => [self::DEFAULT_CATEGORY],
                'orderby'    => 'menu_order',
                'order'      => 'ASC',
            ],
            CategoryList::query_args(self::PARENT, self::DEFAULT_CATEGORY)
        );
    }

    public function testNoSlugIsNoCategory(): void
    {
        Functions\expect('get_term_by')->never();

        self::assertNull(CategoryList::by_slug(''));
    }

    public function testASlugTheStoreHasNotIsNoCategory(): void
    {
        Functions\when('get_term_by')->justReturn(false);

        self::assertNull(CategoryList::by_slug('no-such-category'));
    }

    public function testWithoutADefaultCategoryOptionThereIsNone(): void
    {
        Functions\when('get_option')->justReturn(false);

        self::assertSame(0, CategoryList::default_category());
    }
}
