<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Archive;

use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use Pixonaut\Shop\Archive\ShownWhenEmpty;
use Pixonaut\Shop\Tests\TestCase;

#[CoversClass(ShownWhenEmpty::class)]
final class ShownWhenEmptyTest extends TestCase
{
    /** The group as it renders with products in its collection. */
    private const POPULAR = '<div class="pixonaut-shop-archive-popular"><h2>Popular</h2>'
        . '<div class="wp-block-woocommerce-product-collection">Cards</div></div>';

    /** The group as it renders when its collection found nothing, which WooCommerce prints as nothing. */
    private const POPULAR_WITHOUT_PRODUCTS = '<div class="pixonaut-shop-archive-popular"><h2>Popular</h2></div>';

    protected function tearDown(): void
    {
        unset($GLOBALS['wp_query']);
        parent::tearDown();
    }

    public function testRegistersBeforeAndAfterAGroupRenders(): void
    {
        (new ShownWhenEmpty())->register();

        self::assertNotFalse(has_filter('pre_render_block', 'Pixonaut\Shop\Archive\ShownWhenEmpty->skip()'));
        self::assertNotFalse(has_filter('render_block_core/group', 'Pixonaut\Shop\Archive\ShownWhenEmpty->render()'));
    }

    public function testRendersThePopularProductsOnAnArchiveWithoutProducts(): void
    {
        $this->main_query_found(0);
        $popular = new ShownWhenEmpty();

        self::assertNull($popular->skip(null, $this->popular_group()));
        self::assertSame(self::POPULAR, $popular->render(self::POPULAR, $this->popular_group()));
    }

    public function testSkipsThemBeforeTheyRenderOnAnArchiveWithProducts(): void
    {
        $this->main_query_found(1);

        self::assertSame('', (new ShownWhenEmpty())->skip(null, $this->popular_group()));
    }

    public function testDropsThemWhenThereIsNoProductToRecommend(): void
    {
        $this->main_query_found(0);

        self::assertSame('', (new ShownWhenEmpty())->render(self::POPULAR_WITHOUT_PRODUCTS, $this->popular_group()));
    }

    public function testRendersASearchsNoResultsOnlyWhenItFoundNothing(): void
    {
        $no_results = ['blockName' => 'core/group', 'attrs' => ['className' => ShownWhenEmpty::NO_RESULTS_CLASS]];
        $markup     = '<div class="' . ShownWhenEmpty::NO_RESULTS_CLASS . '">Nothing</div>';
        $shown      = new ShownWhenEmpty();

        $this->main_query_found(1);
        self::assertSame('', $shown->skip(null, $no_results));

        $this->main_query_found(0);
        self::assertNull($shown->skip(null, $no_results));
        self::assertSame($markup, $shown->render($markup, $no_results), 'It holds no product collection, and needs none.');
    }

    public function testLeavesEveryOtherBlockAlone(): void
    {
        $this->main_query_found(1);
        $group   = '<div class="wp-block-group">Group</div>';
        $popular = new ShownWhenEmpty();

        self::assertNull($popular->skip(null, ['blockName' => 'core/group', 'attrs' => ['className' => 'is-style-panel']]));
        self::assertNull($popular->skip(null, ['blockName' => 'core/paragraph', 'attrs' => ['className' => ShownWhenEmpty::POPULAR_CLASS]]));
        self::assertSame($group, $popular->render($group, ['attrs' => []]));
    }

    /**
     * @return array{blockName: string, attrs: array{className: string}}
     */
    private function popular_group(): array
    {
        return ['blockName' => 'core/group', 'attrs' => ['className' => 'alignwide ' . ShownWhenEmpty::POPULAR_CLASS]];
    }

    private function main_query_found(int $found_posts): void
    {
        $query              = Mockery::mock('WP_Query');
        $query->found_posts = $found_posts;
        // No WordPress in a unit test: this stands in for the request's main query.
        $GLOBALS['wp_query'] = $query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
    }
}
