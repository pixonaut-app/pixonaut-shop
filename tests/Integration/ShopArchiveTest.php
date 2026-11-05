<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WC_Product_Simple;
use WP_Query;
use WP_Term;

/**
 * A category archive as WordPress renders it with the theme's shop template:
 * the category's products in the grid with their count and sorting, or,
 * when it has none, the way on — the product search, the shop and the
 * popular products.
 */
final class ShopArchiveTest extends TestCase
{
    private const CATEGORY = 'product_cat';

    private const TAG = 'product_tag';

    private const FULL_CATEGORY = 'Archive category';

    private const EMPTY_CATEGORY = 'Archive empty category';

    private const DESCRIPTION = 'What this category is for.';

    private const PRODUCT = 'Archive product';

    private const OUT_OF_STOCK_PRODUCT = 'Archive sold-out product';

    private const PRICE = '100';

    /** The class WooCommerce's product-button block and its classic button print. */
    private const ADD_TO_CART = 'add_to_cart_button';

    private const RESULTS_COUNT = 'woocommerce-result-count';

    private const SORTING = 'woocommerce-ordering';

    private const PRODUCT_SEARCH = 'wc-block-product-search';

    private const NO_RESULTS = 'wp-block-woocommerce-product-collection-no-results';

    private const POPULAR_HEADING = 'Popular right now';

    private const BACK_TO_THE_SHOP = 'Back to the shop';

    /** WooCommerce's Product Catalog setting, which sizes the archive's page. */
    private const ROWS_OPTION = 'woocommerce_catalog_rows';

    private const COLUMNS_OPTION = 'woocommerce_catalog_columns';

    private const PAGED_PRODUCTS = ['Archive product A', 'Archive product B', 'Archive product C'];

    /** @var array<string, mixed> The Product Catalog setting as it was before a test changed it. */
    private array $catalogue_options = [];

    /** @var list<int> */
    private array $product_ids = [];

    /** @var list<int> */
    private array $term_ids = [];

    protected function tearDown(): void
    {
        foreach ($this->product_ids as $id) {
            wp_delete_post($id, true);
        }

        foreach ($this->term_ids as $id) {
            wp_delete_term($id, self::CATEGORY);
        }

        foreach ($this->catalogue_options as $option => $value) {
            update_option($option, $value);
        }
        $this->catalogue_options = [];

        // The main query is the one object a request has; empty it again.
        $GLOBALS['wp_query']->init();
        wp_reset_postdata();
        parent::tearDown();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function productTaxonomies(): iterable
    {
        yield 'a category' => [self::CATEGORY];
        yield 'a tag' => [self::TAG];
    }

    #[DataProvider('productTaxonomies')]
    public function testATaxonomyArchiveFallsBackToTheShopTemplate(string $taxonomy): void
    {
        $hierarchy = apply_filters('taxonomy_template_hierarchy', ["taxonomy-{$taxonomy}", 'taxonomy']);
        $template  = resolve_block_template('taxonomy', $hierarchy, '');

        self::assertNotNull($template);
        self::assertSame(get_stylesheet() . '//archive-product', $template->id);
    }

    public function testACategoryWithProductsShowsThemWithTheirCountAndSorting(): void
    {
        $category = $this->category(self::FULL_CATEGORY, self::DESCRIPTION);
        $this->product(self::PRODUCT, $category);

        $archive = $this->render($category);

        self::assertStringContainsString(self::FULL_CATEGORY, $archive);
        self::assertStringContainsString(self::DESCRIPTION, $archive);
        self::assertStringContainsString(self::PRODUCT, $archive);
        self::assertStringContainsString(self::RESULTS_COUNT, $archive);
        self::assertStringContainsString(self::SORTING, $archive);
        self::assertStringNotContainsString(self::ADD_TO_CART, $archive);
        self::assertStringNotContainsString(self::NO_RESULTS, $archive);
        self::assertStringNotContainsString(self::POPULAR_HEADING, $archive);
    }

    public function testAnEmptyCategoryOffersTheSearchTheShopAndThePopularProducts(): void
    {
        $this->product(self::PRODUCT, $this->category(self::FULL_CATEGORY));

        $archive = $this->render($this->category(self::EMPTY_CATEGORY));

        self::assertStringContainsString(self::EMPTY_CATEGORY, $archive);
        self::assertStringContainsString(self::NO_RESULTS, $archive);
        self::assertStringContainsString(self::PRODUCT_SEARCH, $archive);
        self::assertMatchesRegularExpression(
            '/<a [^>]*href="' . preg_quote((string) get_post_type_archive_link('product'), '/') . '"[^>]*>' . self::BACK_TO_THE_SHOP . '</',
            $archive
        );
        self::assertStringContainsString(self::POPULAR_HEADING, $archive);
        self::assertStringContainsString(self::PRODUCT, $archive, 'The popular products show no product.');
        self::assertStringNotContainsString(self::ADD_TO_CART, $archive);
    }

    public function testTheThemeStartsWooCommercesProductCatalogAtThreeRowsOfFour(): void
    {
        self::assertSame(['default_rows' => 3, 'default_columns' => 4], wc_get_theme_support('product_grid'));
    }

    public function testAPageShowsAsManyProductsAsWooCommercesProductCatalogSays(): void
    {
        foreach ([self::ROWS_OPTION => 1, self::COLUMNS_OPTION => 2] as $option => $value) {
            $this->catalogue_options[$option] = get_option($option);
            update_option($option, $value);
        }
        $category = $this->category(self::FULL_CATEGORY);
        foreach (self::PAGED_PRODUCTS as $name) {
            $this->product($name, $category);
        }

        $archive = $this->render($category);

        self::assertSame(
            2,
            \count(array_filter(self::PAGED_PRODUCTS, static fn (string $name): bool => str_contains($archive, $name)))
        );
    }

    public function testThePopularProductsLeaveOutWhatIsOutOfStock(): void
    {
        $this->product(self::OUT_OF_STOCK_PRODUCT, $this->category(self::FULL_CATEGORY), false);

        $archive = $this->render($this->category(self::EMPTY_CATEGORY));

        self::assertStringNotContainsString(self::OUT_OF_STOCK_PRODUCT, $archive);
    }

    private function category(string $name, string $description = ''): int
    {
        $term = wp_insert_term($name, self::CATEGORY, ['description' => $description]);
        self::assertIsArray($term);
        $this->term_ids[] = (int) $term['term_id'];

        return (int) $term['term_id'];
    }

    private function product(string $name, int $category, bool $in_stock = true): void
    {
        $product = new WC_Product_Simple();
        $product->set_name($name);
        $product->set_stock_status($in_stock ? 'instock' : 'outofstock');
        $product->set_regular_price(self::PRICE);
        $product->set_category_ids([$category]);
        $product->set_status('publish');
        $this->product_ids[] = $product->save();
    }

    /**
     * The category's archive as a visitor's request renders it: its main
     * query, and the template WordPress picks for it.
     */
    private function render(int $category): string
    {
        $term = get_term($category, self::CATEGORY);
        self::assertInstanceOf(WP_Term::class, $term);

        $main_query = $GLOBALS['wp_query'];
        self::assertInstanceOf(WP_Query::class, $main_query);
        $main_query->query([self::CATEGORY => $term->slug, 'post_type' => 'product']);

        $hierarchy = apply_filters('taxonomy_template_hierarchy', ["taxonomy-{$term->taxonomy}-{$term->slug}", "taxonomy-{$term->taxonomy}", 'taxonomy']);
        $template  = resolve_block_template('taxonomy', $hierarchy, '');
        self::assertNotNull($template);

        return do_blocks($template->content);
    }
}
