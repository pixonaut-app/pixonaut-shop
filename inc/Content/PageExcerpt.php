<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Content;

use WP_Block;

/**
 * A page's excerpt, which `templates/page.html` prints as the lede under its
 * title. WordPress gives pages none, so the editor shows no field for it, and
 * the excerpt block would fill an empty one with the page's opening words; a
 * page whose owner wrote none prints no lede.
 */
final class PageExcerpt
{
    private const POST_TYPE = 'page';

    /** The post ID `get_post()` reads as the global post. */
    private const GLOBAL_POST = 0;

    public function register(): void
    {
        add_action('init', [$this, 'add_support']);
        add_filter('render_block_core/post-excerpt', [$this, 'leave_out_unwritten'], 10, 3);
    }

    public function add_support(): void
    {
        add_post_type_support(self::POST_TYPE, 'excerpt');
    }

    /**
     * The excerpt block's markup for the post it renders, or nothing for a
     * page without an excerpt of its own.
     *
     * The block renders the post its context names, which inside a nested
     * Query Loop need not be the global post, so the decision follows the
     * context and falls back to the global post only when it names none.
     *
     * @param array<mixed>  $parsed_block
     * @param WP_Block|null $block Typed as an object so the unit suite,
     *                             which has no `WP_Block`, can pass a stand-in.
     */
    public function leave_out_unwritten(string $block_content, array $parsed_block = [], ?object $block = null): string
    {
        $post_id = self::context_post_id($block);

        if (self::POST_TYPE === get_post_type($post_id) && !has_excerpt($post_id)) {
            return '';
        }

        return $block_content;
    }

    /**
     * The ID of the post the block's context names, or the global post's
     * when it names none.
     *
     * @param WP_Block|null $block
     */
    private static function context_post_id(?object $block): int
    {
        $context = $block->context ?? [];
        $post_id = \is_array($context) ? ($context['postId'] ?? null) : null;

        return is_numeric($post_id) ? (int) $post_id : self::GLOBAL_POST;
    }
}
