<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Blocks;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use Pixonaut\Shop\Blocks\CategoryCarousel;
use Pixonaut\Shop\Tests\TestCase;

#[CoversClass(CategoryCarousel::class)]
final class CategoryCarouselTest extends TestCase
{
    public function testShowsTheTopLevelCategoriesWithoutAHeadingByDefault(): void
    {
        self::assertSame(['parent' => '', 'heading' => ''], (new CategoryCarousel())->attributes([]));
    }

    public function testKeepsTheChoices(): void
    {
        self::assertSame(
            ['parent' => 'for-whom', 'heading' => 'Till vem'],
            (new CategoryCarousel())->attributes(['parent' => 'for-whom', 'heading' => 'Till vem'])
        );
    }

    public function testRendersNothingForACategoryTheStoreHasNot(): void
    {
        Functions\when('get_term_by')->justReturn(false);
        Functions\expect('get_terms')->never();

        self::assertSame('', (new CategoryCarousel())->render(['parent' => 'no-such-category']));
    }
}
