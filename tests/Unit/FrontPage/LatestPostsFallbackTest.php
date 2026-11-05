<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\FrontPage;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use Pixonaut\Shop\FrontPage\LatestPostsFallback;
use Pixonaut\Shop\Tests\TestCase;

#[CoversClass(LatestPostsFallback::class)]
final class LatestPostsFallbackTest extends TestCase
{
    /** The templates WordPress looks for on the front page, before any filter. */
    private const HIERARCHY = ['front-page.php'];

    public function testRegistersOnTheFrontPagesTemplateHierarchy(): void
    {
        (new LatestPostsFallback())->register();

        self::assertNotFalse(
            has_filter('frontpage_template_hierarchy', 'Pixonaut\Shop\FrontPage\LatestPostsFallback->hierarchy()')
        );
    }

    public function testKeepsTheFrontPageTemplateForAStaticFrontPage(): void
    {
        Functions\when('get_option')->justReturn('page');

        self::assertSame(self::HIERARCHY, (new LatestPostsFallback())->hierarchy(self::HIERARCHY));
    }

    public function testLeavesTheLatestPostsToThePostsList(): void
    {
        Functions\when('get_option')->justReturn('posts');

        self::assertSame([], (new LatestPostsFallback())->hierarchy(self::HIERARCHY));
    }
}
