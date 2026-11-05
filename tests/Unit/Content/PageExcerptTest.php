<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Content;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use Pixonaut\Shop\Content\PageExcerpt;
use Pixonaut\Shop\Tests\TestCase;

#[CoversClass(PageExcerpt::class)]
final class PageExcerptTest extends TestCase
{
    private const EXCERPT = '<div class="wp-block-post-excerpt"><p class="wp-block-post-excerpt__excerpt">What this page is for.</p></div>';

    /** The post the block's context names, unlike the global post. */
    private const CONTEXT_POST_ID = 42;

    public function testRegistersTheSupportAndTheExcerptsRender(): void
    {
        (new PageExcerpt())->register();

        self::assertNotFalse(has_action('init', 'Pixonaut\Shop\Content\PageExcerpt->add_support()'));
        self::assertNotFalse(
            has_filter('render_block_core/post-excerpt', 'Pixonaut\Shop\Content\PageExcerpt->leave_out_unwritten()')
        );
    }

    public function testGivesPagesAnExcerpt(): void
    {
        Functions\expect('add_post_type_support')->once()->with('page', 'excerpt');

        // Monkey\tearDown() verifies the expectation above.
        $this->expectNotToPerformAssertions();

        (new PageExcerpt())->add_support();
    }

    public function testPrintsAPagesExcerptItsOwnerWrote(): void
    {
        Functions\when('get_post_type')->justReturn('page');
        Functions\when('has_excerpt')->justReturn(true);

        self::assertSame(self::EXCERPT, (new PageExcerpt())->leave_out_unwritten(self::EXCERPT));
    }

    public function testPrintsNothingForAPageWithoutOne(): void
    {
        Functions\when('get_post_type')->justReturn('page');
        Functions\when('has_excerpt')->justReturn(false);

        self::assertSame('', (new PageExcerpt())->leave_out_unwritten(self::EXCERPT));
    }

    public function testLeavesOtherPostTypesExcerptsAsTheyAre(): void
    {
        Functions\when('get_post_type')->justReturn('post');
        Functions\when('has_excerpt')->justReturn(false);

        self::assertSame(self::EXCERPT, (new PageExcerpt())->leave_out_unwritten(self::EXCERPT));
    }

    public function testDecidesForThePostTheBlocksContextNames(): void
    {
        Functions\expect('get_post_type')->once()->with(self::CONTEXT_POST_ID)->andReturn('page');
        Functions\expect('has_excerpt')->once()->with(self::CONTEXT_POST_ID)->andReturn(false);

        self::assertSame('', (new PageExcerpt())->leave_out_unwritten(self::EXCERPT, [], self::block(self::CONTEXT_POST_ID)));
    }

    public function testKeepsTheExcerptOfAnotherPostTheContextNames(): void
    {
        Functions\expect('get_post_type')->once()->with(self::CONTEXT_POST_ID)->andReturn('post');
        Functions\when('has_excerpt')->justReturn(false);

        self::assertSame(
            self::EXCERPT,
            (new PageExcerpt())->leave_out_unwritten(self::EXCERPT, [], self::block(self::CONTEXT_POST_ID))
        );
    }

    public function testFallsBackToTheGlobalPostWithoutAPostInTheContext(): void
    {
        // get_post() reads 0 as the global post.
        Functions\expect('get_post_type')->once()->with(0)->andReturn('page');
        Functions\expect('has_excerpt')->once()->with(0)->andReturn(false);

        self::assertSame('', (new PageExcerpt())->leave_out_unwritten(self::EXCERPT, [], self::block(null)));
    }

    /**
     * A stand-in for the `WP_Block` WordPress passes, which the unit suite
     * has no class for: only its context matters here.
     */
    private static function block(?int $post_id): object
    {
        return (object) ['context' => null === $post_id ? [] : ['postId' => $post_id]];
    }
}
