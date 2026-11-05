<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use PHPUnit\Framework\TestCase;
use WP_Block;
use WP_Query;

/**
 * A page as WordPress renders it with the theme's page template: the
 * breadcrumbs, then one panel with its title, the excerpt as its lede when
 * the page has one, and its content.
 */
final class ContentPageTest extends TestCase
{
    private const TITLE = 'Content page title';

    private const EXCERPT = 'What this page is about, in a sentence.';

    private const CONTENT = 'The opening words of this page.';

    /** The class core's excerpt block prints around a post's excerpt. */
    private const EXCERPT_BLOCK = 'wp-block-post-excerpt';

    private const BREADCRUMBS = 'woocommerce-breadcrumb';

    private const PANEL = 'is-style-panel';

    /** @var list<int> */
    private array $page_ids = [];

    protected function tearDown(): void
    {
        foreach ($this->page_ids as $id) {
            wp_delete_post($id, true);
        }

        // The main query is the one object a request has; empty it again.
        $GLOBALS['wp_query']->init();
        wp_reset_postdata();
        parent::tearDown();
    }

    public function testPagesHaveAnExcerpt(): void
    {
        self::assertTrue(post_type_supports('page', 'excerpt'));
    }

    public function testAPageWithAnExcerptShowsItAsTheLede(): void
    {
        $page = $this->render($this->page(self::EXCERPT));

        self::assertStringContainsString(self::BREADCRUMBS, $page);
        self::assertMatchesRegularExpression(
            '/class="[^"]*' . self::PANEL . '[^"]*".*' . self::TITLE . '.*' . self::EXCERPT_BLOCK . '[^>]*>.*'
            . self::EXCERPT . '.*' . self::CONTENT . '/s',
            $page
        );
    }

    public function testAPageWithoutAnExcerptShowsNoLede(): void
    {
        $page = $this->render($this->page(''));

        self::assertStringContainsString(self::TITLE, $page);
        self::assertStringContainsString(self::CONTENT, $page);
        self::assertStringNotContainsString(self::EXCERPT_BLOCK, $page);
    }

    public function testTheExcerptBlockKeepsTheExcerptOfThePostItsContextNames(): void
    {
        $this->make_global($this->page(''));

        self::assertStringContainsString(self::EXCERPT, $this->render_excerpt($this->page(self::EXCERPT)));
    }

    public function testTheExcerptBlockLeavesOutAnUnwrittenExcerptOfThePostItsContextNames(): void
    {
        $this->make_global($this->page(self::EXCERPT));

        self::assertSame('', $this->render_excerpt($this->page('')));
    }

    private function page(string $excerpt): int
    {
        $id = wp_insert_post([
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_title'   => self::TITLE,
            'post_excerpt' => $excerpt,
            'post_content' => '<!-- wp:paragraph --><p>' . self::CONTENT . '</p><!-- /wp:paragraph -->',
        ]);
        self::assertIsInt($id);
        $this->page_ids[] = $id;

        return $id;
    }

    /**
     * The page as a visitor's request renders it: its main query, and the
     * template WordPress picks for it.
     */
    private function render(int $id): string
    {
        $main_query = $GLOBALS['wp_query'];
        self::assertInstanceOf(WP_Query::class, $main_query);
        $main_query->query(['page_id' => $id]);
        $main_query->the_post();

        $template = resolve_block_template('page', ['page'], '');
        self::assertNotNull($template);
        self::assertSame(get_stylesheet() . '//page', $template->id);

        return do_blocks($template->content);
    }

    /** The post outside the block's context, as a nested loop can leave it. */
    private function make_global(int $id): void
    {
        $main_query = $GLOBALS['wp_query'];
        self::assertInstanceOf(WP_Query::class, $main_query);
        $main_query->query(['page_id' => $id]);
        $main_query->the_post();
    }

    /** The excerpt block on its own, for the post its context names. */
    private function render_excerpt(int $id): string
    {
        $parsed = parse_blocks('<!-- wp:post-excerpt /-->')[0];

        return (new WP_Block($parsed, ['postId' => $id, 'postType' => 'page']))->render();
    }
}
