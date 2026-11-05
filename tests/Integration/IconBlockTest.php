<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use PHPUnit\Framework\TestCase;
use WP_Block_Patterns_Registry;

/**
 * The icon block as WordPress renders it, and the promises pattern built
 * from it.
 */
final class IconBlockTest extends TestCase
{
    private const PROMISES = 'pixonaut-shop/promises';

    /** The four promises the pattern starts a store with. */
    private const PROMISE_COUNT = 4;

    public function testRendersTheChosenIconInACircle(): void
    {
        $icon = do_blocks('<!-- wp:pixonaut-shop/icon {"icon":"truck","circle":true} /-->');

        self::assertMatchesRegularExpression(
            '/\A<span class="has-circle wp-block-pixonaut-shop-icon"><svg [^>]+>.+<\/svg><\/span>\z/s',
            $icon
        );
    }

    public function testRendersAPlainIconWithoutACircle(): void
    {
        $icon = do_blocks('<!-- wp:pixonaut-shop/icon /-->');

        self::assertStringStartsWith('<span class="wp-block-pixonaut-shop-icon"><svg ', $icon);
    }

    public function testThePromisesPatternRendersFourIconsWithAHeadingAndASentenceEach(): void
    {
        $pattern = WP_Block_Patterns_Registry::get_instance()->get_registered(self::PROMISES);
        self::assertIsArray($pattern, self::PROMISES . ' is not registered.');

        $promises = do_blocks($pattern['content']);

        self::assertSame(self::PROMISE_COUNT, substr_count($promises, 'has-circle wp-block-pixonaut-shop-icon'));
        self::assertSame(self::PROMISE_COUNT, substr_count($promises, 'A short promise</h3>'));
        self::assertSame(self::PROMISE_COUNT, substr_count($promises, 'One sentence about it.</p>'));
    }
}
