<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use PHPUnit\Framework\TestCase;
use WP_Query;

/**
 * A blog post as WordPress renders it with the theme's single template: its
 * title, date, content, categories and tags in a panel, then its comments
 * and the form to leave one, and the links to the posts beside it.
 */
final class BlogPostTest extends TestCase
{
    private const TITLE = 'Blog post title';

    private const CONTENT = 'The whole of this post, not only its opening words.';

    private const COMMENT = 'A reader wrote this comment.';

    private const PANEL = 'is-style-panel';

    /** @var list<int> */
    private array $post_ids = [];

    protected function tearDown(): void
    {
        foreach ($this->post_ids as $id) {
            wp_delete_post($id, true);
        }

        // The main query is the one object a request has; empty it again.
        $GLOBALS['wp_query']->init();
        wp_reset_postdata();
        parent::tearDown();
    }

    public function testAPostShowsItsTitleAndWholeContentInAPanel(): void
    {
        $post = $this->render($this->post());

        self::assertMatchesRegularExpression(
            '/class="[^"]*' . self::PANEL . '[^"]*".*' . self::TITLE . '.*wp-block-post-date.*' . self::CONTENT . '/s',
            $post
        );
    }

    public function testAPostShowsItsCategoriesAndTags(): void
    {
        $id = $this->post();
        wp_set_post_tags($id, ['a-tag-of-this-post']);

        $post = $this->render($id);

        self::assertStringContainsString('taxonomy-category', $post);
        self::assertStringContainsString('a-tag-of-this-post', $post);
    }

    public function testAPostShowsItsCommentsAndTheFormToLeaveOne(): void
    {
        $id = $this->post();
        wp_insert_comment([
            'comment_post_ID'  => $id,
            'comment_content'  => self::COMMENT,
            'comment_approved' => 1,
        ]);

        $post = $this->render($id);

        self::assertStringContainsString(self::COMMENT, $post);
        self::assertStringContainsString('id="commentform"', $post);
    }

    public function testThePostsListShowsEachPostsDate(): void
    {
        $this->post();

        $main_query = $GLOBALS['wp_query'];
        self::assertInstanceOf(WP_Query::class, $main_query);
        $main_query->query(['post_type' => 'post']);

        $template = resolve_block_template('home', ['home', 'index'], '');
        self::assertNotNull($template);

        $list = do_blocks($template->content);

        self::assertStringContainsString(self::TITLE, $list);
        self::assertStringContainsString('wp-block-post-date', $list);
    }

    private function post(): int
    {
        $id = wp_insert_post([
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'post_title'     => self::TITLE,
            'comment_status' => 'open',
            'post_content'   => '<!-- wp:paragraph --><p>' . self::CONTENT . '</p><!-- /wp:paragraph -->',
        ]);
        self::assertIsInt($id);
        $this->post_ids[] = $id;

        return $id;
    }

    /**
     * The post as a visitor's request renders it: its main query, and the
     * template WordPress picks for it.
     */
    private function render(int $id): string
    {
        $main_query = $GLOBALS['wp_query'];
        self::assertInstanceOf(WP_Query::class, $main_query);
        $main_query->query(['p' => $id]);
        $main_query->the_post();

        $template = resolve_block_template('single', ['single-post', 'single'], '');
        self::assertNotNull($template);
        self::assertSame(get_stylesheet() . '//single', $template->id);

        return do_blocks($template->content);
    }
}
