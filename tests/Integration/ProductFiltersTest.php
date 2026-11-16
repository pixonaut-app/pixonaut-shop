<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use Automattic\WooCommerce\Internal\ProductFilters\Params;
use PHPUnit\Framework\TestCase;
use Pixonaut\Shop\Archive\ProductFilters;
use Pixonaut\Shop\Tests\Support\FilterFixtures;
use ReflectionProperty;
use WC_Query;
use WP_Query;
use WP_Term;

/**
 * The filters beside an archive's product grid, as WordPress renders the
 * shop template for a category whose products are sold in variations of the
 * store's product attributes (`FilterFixtures`).
 */
final class ProductFiltersTest extends TestCase
{
    private const CATEGORY = 'product_cat';

    private const EMPTY_CATEGORY = 'Filters empty category';

    private const ATTRIBUTE_FILTER = 'wp-block-woocommerce-product-filter-attribute';

    private const PRICE_FILTER = 'wp-block-woocommerce-product-filter-price';

    private const CHIP = 'wc-block-product-filter-removable-chips__item';

    /** A chosen filter's chip, which WooCommerce's own template for one is not. */
    private const RENDERED_CHIP = '/' . self::CHIP . '"\s+data-wp-each-child/';

    /** WooCommerce's attribute filter, as a store places it in the Site Editor. */
    private const ATTRIBUTE_BLOCK = 'woocommerce/product-filter-attribute';

    private const FILTERS_BLOCK = 'woocommerce/product-filters';

    /** Where WooCommerce keeps the query arguments that are a filter's. */
    private const KEPT_ARGUMENTS = 'params';

    /** @var list<int> */
    private array $term_ids = [];

    /** @var array<string, mixed> */
    private array $request = [];

    protected function setUp(): void
    {
        parent::setUp();
        FilterFixtures::prepare();
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- kept to put back, never read.
        $this->request = ['get' => $_GET, 'uri' => $_SERVER['REQUEST_URI'] ?? null];
    }

    protected function tearDown(): void
    {
        FilterFixtures::forget();

        foreach ($this->term_ids as $id) {
            wp_delete_term($id, self::CATEGORY);
        }

        $_GET = $this->request['get'];

        if (null === $this->request['uri']) {
            unset($_SERVER['REQUEST_URI']);
        } else {
            $_SERVER['REQUEST_URI'] = $this->request['uri'];
        }

        // WooCommerce reads the chosen filters from the request once, then keeps them.
        $this->forget_chosen_filters();

        $GLOBALS['wp_query']->init();
        wp_reset_postdata();
        parent::tearDown();
    }

    public function testEveryAttributeTheProductsHaveIsAGroupOfItsValuesWithTheirCounts(): void
    {
        $archive = $this->render(FilterFixtures::category());

        self::assertSame(
            [FilterFixtures::FRAME_COLOUR, FilterFixtures::FRAMING, 'Price'],
            $this->group_headings($archive),
            'An attribute no product has shows no group, and the price comes last.'
        );
        self::assertSame(2, $this->count_of($archive, FilterFixtures::NO_FRAME));
        self::assertSame(1, $this->count_of($archive, FilterFixtures::FRAME));
        self::assertSame(1, $this->count_of($archive, FilterFixtures::OAK));
        self::assertStringContainsString(self::PRICE_FILTER, $archive);
        self::assertDoesNotMatchRegularExpression(self::RENDERED_CHIP, $archive);
    }

    public function testAChosenValueNarrowsTheGridAndShowsAsAChip(): void
    {
        $archive = $this->render(FilterFixtures::category(), ['filter_' . FilterFixtures::FRAMING_SLUG => FilterFixtures::FRAME_SLUG]);

        self::assertStringContainsString(FilterFixtures::FRAMED_PRODUCT, $archive);
        self::assertStringNotContainsString(FilterFixtures::UNFRAMED_PRODUCT, $archive);
        self::assertMatchesRegularExpression(
            '/' . self::CHIP . '.*?' . FilterFixtures::FRAMING . ': ' . FilterFixtures::FRAME . '/s',
            $archive
        );
        self::assertMatchesRegularExpression('/<input[^>]*value="' . FilterFixtures::FRAME_SLUG . '"[^>]*checked/s', $archive);
    }

    public function testAPriceNarrowsTheGrid(): void
    {
        $archive = $this->render(FilterFixtures::category(), ['min_price' => FilterFixtures::PRICE_BETWEEN]);

        self::assertStringContainsString(FilterFixtures::FRAMED_PRODUCT, $archive);
        self::assertStringNotContainsString(FilterFixtures::UNFRAMED_PRODUCT, $archive);
    }

    public function testAnArchiveWithoutProductsHasNoFilters(): void
    {
        $archive = $this->render($this->empty_category());

        self::assertStringNotContainsString(ProductFilters::PANEL_CLASS, $archive);
    }

    public function testAFilteredArchiveThatFoundNothingKeepsItsFilters(): void
    {
        $archive = $this->render($this->empty_category(), ['filter_' . FilterFixtures::FRAMING_SLUG => FilterFixtures::FRAME_SLUG]);

        self::assertStringContainsString(ProductFilters::PANEL_CLASS, $archive);
        self::assertMatchesRegularExpression(self::RENDERED_CHIP, $archive);
    }

    public function testTheDialogsButtonSaysWhatItShows(): void
    {
        $archive = $this->render(FilterFixtures::category());

        self::assertMatchesRegularExpression('/wc-block-product-filters__apply[^>]*>\s*<span>Show products<\/span>/s', $archive);
    }

    public function testAPanelWithTheStoresOwnAttributeFiltersKeepsExactlyThose(): void
    {
        $own   = ['blockName' => self::ATTRIBUTE_BLOCK, 'attrs' => ['attributeId' => 1], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []];
        $panel = [
            'blockName' => self::FILTERS_BLOCK,
            'attrs' => ['className' => ProductFilters::PANEL_CLASS],
            'innerBlocks' => [$own],
            'innerHTML' => '<div></div>',
            'innerContent' => ['<div>', null, '</div>'],
        ];

        self::assertSame($panel, (new ProductFilters())->add_attributes($panel));
    }

    public function testAPanelWithTheStoresOwnAttributeFilterInsideAGroupKeepsExactlyThat(): void
    {
        $own   = ['blockName' => self::ATTRIBUTE_BLOCK, 'attrs' => ['attributeId' => 1], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []];
        $group = ['blockName' => 'core/group', 'attrs' => [], 'innerBlocks' => [$own], 'innerHTML' => '<div></div>', 'innerContent' => ['<div>', null, '</div>']];
        $panel = [
            'blockName' => self::FILTERS_BLOCK,
            'attrs' => ['className' => ProductFilters::PANEL_CLASS],
            'innerBlocks' => [$group],
            'innerHTML' => '<div></div>',
            'innerContent' => ['<div>', null, '</div>'],
        ];

        self::assertSame($panel, (new ProductFilters())->add_attributes($panel));
    }

    public function testAnotherProductFiltersBlockGetsNoGroups(): void
    {
        $block = ['blockName' => self::FILTERS_BLOCK, 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '<div></div>', 'innerContent' => ['<div></div>']];

        self::assertSame($block, (new ProductFilters())->add_attributes($block));
    }

    /**
     * The groups' headings, in the order the panel shows them, without the
     * groups WooCommerce hides for having no value.
     *
     * @return list<string>
     */
    private function group_headings(string $archive): array
    {
        preg_match_all('/<div(?![^>]*\bhidden\b)[^>]*class="[^"]*wp-block-woocommerce-product-filter-(?:attribute|price)[^"]*"[^>]*>\s*<h3[^>]*>([^<]+)<\/h3>/s', $archive, $found);

        return $found[1];
    }

    /** How many products the filters say have this value. */
    private function count_of(string $archive, string $value): int
    {
        $found = preg_match(
            '/__text">\s*' . preg_quote($value, '/') . '\s*<\/span>\s*<span class="wc-block-product-filter-checkbox-list__count">\s*\(<span[^>]*>(\d+)</s',
            $archive,
            $count
        );
        self::assertSame(1, $found, "The filters do not list {$value} with a count.");

        return (int) $count[1];
    }

    private function empty_category(): int
    {
        $created = wp_insert_term(self::EMPTY_CATEGORY, self::CATEGORY);
        self::assertIsArray($created);
        $this->term_ids[] = (int) $created['term_id'];

        return (int) $created['term_id'];
    }

    /**
     * The category's archive as a visitor's request renders it, with the
     * request's filters in its address.
     *
     * @param array<string, string> $filters
     */
    private function render(int $category, array $filters = []): string
    {
        $term = get_term($category, self::CATEGORY);
        self::assertInstanceOf(WP_Term::class, $term);

        $_GET                   = $filters;
        $_SERVER['REQUEST_URI'] = add_query_arg($filters, '/product-category/' . $term->slug . '/');
        $this->forget_chosen_filters();

        $main_query = $GLOBALS['wp_query'];
        self::assertInstanceOf(WP_Query::class, $main_query);
        $main_query->query([self::CATEGORY => $term->slug, 'post_type' => 'product']);

        $hierarchy = apply_filters('taxonomy_template_hierarchy', ["taxonomy-{$term->taxonomy}-{$term->slug}", "taxonomy-{$term->taxonomy}", 'taxonomy']);
        $template  = resolve_block_template('taxonomy', $hierarchy, '');
        self::assertNotNull($template);

        return do_blocks($template->content);
    }

    /**
     * WooCommerce reads two things once and keeps them for the rest of the
     * request: the attributes the request chose, and which query arguments
     * are a filter's, one for each product attribute the store had then. A
     * test is a request of its own with attributes of its own, so both are
     * read again.
     */
    private function forget_chosen_filters(): void
    {
        WC_Query::reset_chosen_attributes();

        if (property_exists(Params::class, self::KEPT_ARGUMENTS)) {
            (new ReflectionProperty(Params::class, self::KEPT_ARGUMENTS))->setValue(null, []);
        }
    }
}
