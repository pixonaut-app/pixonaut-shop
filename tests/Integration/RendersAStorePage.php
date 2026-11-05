<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use WP_Block_Template;
use WP_Post;
use WP_Query;

/**
 * Renders one of WooCommerce's store pages (its Cart or Checkout page) as a
 * visitor's request does: its main query, and the template WordPress picks
 * for it rendered in the loop, as WordPress renders a singular page.
 */
trait RendersAStorePage
{
    /**
     * Empties the main query again, as it is the one object a request has,
     * and the global post it set: WooCommerce picks its own page templates
     * by that post.
     */
    private function reset_main_query(): void
    {
        $GLOBALS['wp_query']->init();
        // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- a test resets what its request set.
        $GLOBALS['post'] = null;
    }

    /** The store's page WooCommerce knows by this name: `cart` or `checkout`. */
    private function store_page(string $name): WP_Post
    {
        $page = get_post(wc_get_page_id($name));
        self::assertInstanceOf(WP_Post::class, $page, "The tests site has no {$name} page.");

        return $page;
    }

    /** The template WordPress picks for a page. */
    private function page_template(WP_Post $page): WP_Block_Template
    {
        $hierarchy = apply_filters('page_template_hierarchy', ["page-{$page->post_name}", "page-{$page->ID}", 'page']);
        $template  = resolve_block_template('page', $hierarchy, '');
        self::assertNotNull($template);

        return $template;
    }

    private function render_page(WP_Post $page): string
    {
        global $_wp_current_template_content, $_wp_current_template_id;

        $main_query = $GLOBALS['wp_query'];
        self::assertInstanceOf(WP_Query::class, $main_query);
        $main_query->query(['page_id' => $page->ID]);

        $template = $this->page_template($page);
        $_wp_current_template_content = $template->content;
        $_wp_current_template_id = $template->id;

        return get_the_block_template_html();
    }
}
