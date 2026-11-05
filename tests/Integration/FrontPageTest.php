<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use PHPUnit\Framework\TestCase;
use WC_Product_Simple;
use WP_Block_Patterns_Registry;
use WP_Block_Template;
use WP_Query;

/**
 * The front page as WordPress renders it: a page the store made from the
 * front page pattern, set as its homepage, in the theme's front page
 * template; or, with the latest posts as the homepage, the posts list.
 */
final class FrontPageTest extends TestCase
{
    private const PATTERN = 'pixonaut-shop/front-page';

    private const CATEGORY = 'product_cat';

    private const CATEGORY_NAME = 'Front page category';

    private const PRODUCT = 'Front page product';

    private const PRICE = '100';

    /** The Reading settings that choose the homepage, and their values before a test. */
    private const READING_OPTIONS = ['show_on_front', 'page_on_front'];

    private const HERO = 'wp-block-media-text';

    private const CATEGORIES = 'wp-block-pixonaut-shop-product-categories';

    private const PRODUCT_COLLECTION = 'wp-block-woocommerce-product-collection';

    /** @var array<string, mixed> */
    private array $reading = [];

    /** @var list<int> */
    private array $post_ids = [];

    private int $term_id = 0;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::READING_OPTIONS as $option) {
            $this->reading[$option] = get_option($option);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->reading as $option => $value) {
            update_option($option, $value);
        }

        foreach ($this->post_ids as $id) {
            wp_delete_post($id, true);
        }

        if (0 !== $this->term_id) {
            wp_delete_term($this->term_id, self::CATEGORY);
        }

        // The main query, the template and WooCommerce's product loop are one
        // per request; empty them again.
        $GLOBALS['wp_query']->init();
        wp_reset_postdata();
        unset($GLOBALS['_wp_current_template_id'], $GLOBALS['_wp_current_template_content']);
        wc_reset_loop();
        parent::tearDown();
    }

    public function testAPageMadeFromThePatternShowsTheHeroTheCategoriesAndThePopularProducts(): void
    {
        $this->product_in_a_category();
        $page = $this->page_from_the_pattern();
        update_option('show_on_front', 'page');
        update_option('page_on_front', $page);

        $template = $this->front_page_template();
        self::assertSame(get_stylesheet() . '//front-page', $template->id);

        $html = $this->render($template, ['page_id' => $page]);

        self::assertStringContainsString(self::HERO, $html);
        self::assertStringContainsString(self::CATEGORIES, $html);
        self::assertStringContainsString(self::CATEGORY_NAME, $html);
        self::assertStringContainsString(self::PRODUCT_COLLECTION, $html);
        self::assertStringContainsString(self::PRODUCT, $html);
    }

    public function testTheLatestPostsAsTheHomepageShowThePostsList(): void
    {
        update_option('show_on_front', 'posts');

        self::assertSame(get_stylesheet() . '//index', $this->front_page_template()->id);
    }

    private function product_in_a_category(): void
    {
        $term = wp_insert_term(self::CATEGORY_NAME, self::CATEGORY);
        self::assertIsArray($term);
        $this->term_id = (int) $term['term_id'];

        $product = new WC_Product_Simple();
        $product->set_name(self::PRODUCT);
        $product->set_regular_price(self::PRICE);
        $product->set_category_ids([$this->term_id]);
        $product->set_status('publish');
        $this->post_ids[] = $product->save();
    }

    /** A published page whose content is the pattern's, as the editor inserts it. */
    private function page_from_the_pattern(): int
    {
        $pattern = WP_Block_Patterns_Registry::get_instance()->get_registered(self::PATTERN);
        self::assertIsArray($pattern, self::PATTERN . ' is not registered.');

        $page = wp_insert_post([
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_title'   => 'Home',
            'post_content' => wp_slash($pattern['content']),
        ]);
        self::assertIsInt($page);
        $this->post_ids[] = $page;

        return $page;
    }

    /** The template WordPress picks for the homepage, before the home and index ones. */
    private function front_page_template(): WP_Block_Template
    {
        $hierarchy = array_merge(
            apply_filters('frontpage_template_hierarchy', ['front-page']),
            ['home', 'index']
        );
        $template = resolve_block_template('front-page', $hierarchy, '');
        self::assertInstanceOf(WP_Block_Template::class, $template);

        return $template;
    }

    /**
     * The template as a visitor's request for the homepage renders it: its
     * main query, and the loop the template's content is rendered in.
     *
     * @param array<string, mixed> $query
     */
    private function render(WP_Block_Template $template, array $query): string
    {
        $main_query = $GLOBALS['wp_query'];
        self::assertInstanceOf(WP_Query::class, $main_query);
        $main_query->query($query);

        $GLOBALS['_wp_current_template_id']      = $template->id;
        $GLOBALS['_wp_current_template_content'] = $template->content;

        return get_the_block_template_html();
    }
}
