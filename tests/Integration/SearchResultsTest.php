<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use PHPUnit\Framework\TestCase;
use WC_Product_Simple;
use WP_Block_Patterns_Registry;
use WP_Block_Template;
use WP_Query;

/**
 * A search as WordPress renders it with the theme's templates: a product
 * search's results with their count and sorting, or, when nothing matched,
 * a notice that names the query, the store's categories and the popular
 * products; and a search of the whole site, which lists its pages too.
 */
final class SearchResultsTest extends TestCase
{
    private const CATEGORY = 'product_cat';

    private const CATEGORY_NAME = 'Search category';

    private const PRODUCT = 'Searchable mug';

    private const MATCHING_QUERY = 'Searchable';

    private const UNMATCHED_QUERY = 'Nothingmatchesthis';

    private const PAGE = 'Searchable page';

    private const PRICE = '100';

    /** The class WooCommerce's product-button block and its classic button print. */
    private const ADD_TO_CART = 'add_to_cart_button';

    private const RESULTS_COUNT = 'woocommerce-result-count';

    private const SORTING = 'woocommerce-ordering';

    /** The sorting WooCommerce offers only on a search: by how well a product matches. */
    private const RELEVANCE_ORDER = '<option value="relevance"';

    private const SEARCH_FIELD = 'wp-block-search__input';

    private const NO_RESULTS = 'pixonaut-shop-search-no-results';

    /** The product categories block's wrapper in its buttons layout, its classes in any order. */
    private const CATEGORY_BUTTONS = '/<div class="(?=[^"]*\\bis-buttons\\b)(?=[^"]*\\bwp-block-pixonaut-shop-product-categories\\b)[^"]*">/';

    private const SEARCH_RESULTS = 'Search results';

    private const POPULAR_HEADING = 'Popular right now';

    /** The pattern that prints the query, which reads it when WordPress includes its file. */
    private const NO_RESULTS_PATTERN = 'pixonaut-shop/search-no-results';

    /** @var list<int> */
    private array $post_ids = [];

    private int $term_id = 0;

    protected function setUp(): void
    {
        parent::setUp();

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

    protected function tearDown(): void
    {
        foreach ($this->post_ids as $id) {
            wp_delete_post($id, true);
        }

        wp_delete_term($this->term_id, self::CATEGORY);

        // The main query is the one object a request has; empty it again.
        $GLOBALS['wp_query']->init();
        wp_reset_postdata();
        parent::tearDown();
    }

    public function testAProductSearchUsesTheProductSearchTemplate(): void
    {
        self::assertSame(get_stylesheet() . '//product-search-results', $this->template(self::MATCHING_QUERY, 'product')->id);
    }

    public function testASiteSearchUsesTheSearchTemplate(): void
    {
        self::assertSame(get_stylesheet() . '//search', $this->template(self::MATCHING_QUERY)->id);
    }

    public function testAProductSearchShowsWhatMatchedWithItsCountAndSorting(): void
    {
        $results = $this->render(self::MATCHING_QUERY, 'product');

        self::assertStringContainsString(self::SEARCH_RESULTS, $results);
        self::assertMatchesRegularExpression('/class="' . self::SEARCH_FIELD . '"[^>]*value="' . self::MATCHING_QUERY . '"/', $results);
        self::assertStringContainsString(self::PRODUCT, $results);
        self::assertStringContainsString(self::RESULTS_COUNT, $results);
        self::assertStringContainsString(self::SORTING, $results);
        self::assertStringContainsString(self::RELEVANCE_ORDER, $results);
        self::assertStringNotContainsString(self::ADD_TO_CART, $results);
        self::assertStringNotContainsString(self::NO_RESULTS, $results);
        self::assertStringNotContainsString(self::POPULAR_HEADING, $results);
    }

    public function testAProductSearchWithoutMatchesNamesTheQueryAndOffersCategoriesAndPopularProducts(): void
    {
        $results = $this->render(self::UNMATCHED_QUERY, 'product');

        self::assertStringContainsString(self::NO_RESULTS, $results);
        self::assertStringContainsString('No results for “' . self::UNMATCHED_QUERY . '”', $results);
        self::assertMatchesRegularExpression(self::CATEGORY_BUTTONS, $results);
        self::assertStringContainsString(self::CATEGORY_NAME, $results);
        self::assertStringContainsString(self::POPULAR_HEADING, $results);
        self::assertStringContainsString(self::PRODUCT, $results, 'The popular products show no product.');
        self::assertStringNotContainsString(self::ADD_TO_CART, $results);
    }

    /**
     * The Site Editor saves a template with its patterns' blocks in place,
     * printed for the request that opened it, which searched for nothing.
     */
    public function testATemplateSavedInTheSiteEditorNamesEachSearchsOwnQuery(): void
    {
        $saved = $this->saved_from_the_site_editor('product-search-results');

        foreach ([self::UNMATCHED_QUERY, 'Another unmatched query'] as $search) {
            $this->template($search, 'product');
            wc_reset_loop();

            self::assertStringContainsString('No results for “' . $search . '”', do_blocks($saved));
        }
    }

    public function testASiteSearchListsTheMatchingPages(): void
    {
        $this->post_ids[] = (int) wp_insert_post(['post_title' => self::PAGE, 'post_type' => 'page', 'post_status' => 'publish']);

        $results = $this->render(self::MATCHING_QUERY);

        self::assertStringContainsString(self::SEARCH_RESULTS, $results);
        self::assertStringContainsString(self::PAGE, $results);
        self::assertStringNotContainsString(self::NO_RESULTS, $results);
    }

    public function testASiteSearchWithoutMatchesNamesTheQuery(): void
    {
        $results = $this->render(self::UNMATCHED_QUERY);

        self::assertStringContainsString('No results for “' . self::UNMATCHED_QUERY . '”', $results);
    }

    /**
     * The template WordPress picks for a search, with the main query a
     * visitor's request would make for it.
     */
    private function template(string $search, string $post_type = ''): WP_Block_Template
    {
        $main_query = $GLOBALS['wp_query'];
        self::assertInstanceOf(WP_Query::class, $main_query);
        $main_query->query(array_filter(['s' => $search, 'post_type' => $post_type]));
        self::assertTrue(is_search());

        $template = resolve_block_template('search', apply_filters('search_template_hierarchy', ['search']), '');
        self::assertNotNull($template);

        return $template;
    }

    private function render(string $search, string $post_type = ''): string
    {
        $template = $this->template($search, $post_type);
        $this->include_no_results_afresh();
        // WooCommerce sets up its loop once a request, from the main query.
        wc_reset_loop();

        return do_blocks($template->content);
    }

    /** A theme template's content as the Site Editor saves it, its patterns expanded. */
    private function saved_from_the_site_editor(string $slug): string
    {
        $GLOBALS['wp_query']->init();
        $this->include_no_results_afresh();
        $template = get_block_template(get_stylesheet() . '//' . $slug);
        self::assertNotNull($template);

        return serialize_blocks(resolve_pattern_blocks(parse_blocks($template->content)));
    }

    /**
     * WordPress includes a pattern's file once a request and keeps what it
     * printed; every search here stands for a request of its own.
     */
    private function include_no_results_afresh(): void
    {
        $registry   = WP_Block_Patterns_Registry::get_instance();
        $properties = $registry->get_registered(self::NO_RESULTS_PATTERN);
        self::assertIsArray($properties);

        unset($properties['content']);
        $properties['filePath'] = get_theme_file_path('patterns/search-no-results.php');
        $registry->unregister(self::NO_RESULTS_PATTERN);
        $registry->register(self::NO_RESULTS_PATTERN, $properties);
    }
}
