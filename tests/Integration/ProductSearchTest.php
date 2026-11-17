<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WC_Product_Simple;
use WP_Post;
use WP_Query;
use WP_REST_Request;

/**
 * A product search in a Swedish store, on the results page and in the
 * header's suggestions from the Store API alike: a word finds a product
 * that holds another form of it, at the start of a word in its name or
 * description, after the products that hold it as typed.
 *
 * The site's own locale is set, as in BlockTranslationsTest, since the tests
 * site has no Swedish core pack. Each search is limited to this test's
 * products, so others on the tests site do not change the order.
 */
final class ProductSearchTest extends TestCase
{
    private const SWEDISH = 'sv_SE';

    private const PANDOR = 'Pandor – personlig födelsetavla';

    private const UGGLOR = 'Ugglor i skogen';

    private const KANINER = 'Kaniner på äng';

    /** Holds the typed word as it is, so it ranks first. */
    private const PANDA_MUG = 'Panda mugg';

    /** Holds the stem only in its description. */
    private const POSTER = 'Barnrumsposter';

    private const POSTER_DESCRIPTION = 'Med två pandor som leker.';

    /** Holds the stem in the middle of a word only, which is not a match. */
    private const EXPANDER = 'Expanderbar ram';

    private const SKU = 'PXS-38-SKU';

    private const PRICE = '100';

    /** The Store API's product listing, which the header's suggestions read. */
    private const STORE_API_PRODUCTS = '/wc/store/v1/products';

    /** @var callable(): string */
    private $swedish;

    /** @var array<string, int> Product IDs by name. */
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->swedish = static fn (): string => self::SWEDISH;
        add_filter('locale', $this->swedish);

        $this->product(self::PANDOR);
        $this->product(self::UGGLOR);
        $this->product(self::KANINER);
        $this->product(self::PANDA_MUG);
        $this->product(self::POSTER, self::POSTER_DESCRIPTION);
        $this->product(self::EXPANDER, '', self::SKU);
    }

    protected function tearDown(): void
    {
        foreach ($this->ids as $id) {
            wp_delete_post($id, true);
        }

        remove_filter('locale', $this->swedish);
        parent::tearDown();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function otherForms(): iterable
    {
        yield 'panda' => ['panda', self::PANDOR];
        yield 'uggla' => ['uggla', self::UGGLOR];
        yield 'kanin' => ['kanin', self::KANINER];
        yield 'a plural typed for it' => ['ugglorna', self::UGGLOR];
    }

    #[DataProvider('otherForms')]
    public function testTheResultsPageFindsAProductByAnotherFormOfItsName(string $search, string $product): void
    {
        self::assertContains($product, $this->results_page($search));
    }

    #[DataProvider('otherForms')]
    public function testTheSuggestionsFindAProductByAnotherFormOfItsName(string $search, string $product): void
    {
        self::assertContains($product, $this->suggestions($search));
    }

    public function testRanksTheWordAsTypedAboveItsStemAndANameAboveADescription(): void
    {
        $expected = [self::PANDA_MUG, self::PANDOR, self::POSTER];

        self::assertSame($expected, $this->results_page('panda'));
        self::assertSame($expected, $this->suggestions('panda'));
    }

    public function testFindsATypedPrefixAsTyped(): void
    {
        self::assertSame([self::UGGLOR], $this->suggestions('uggl'));
        self::assertSame([self::UGGLOR], $this->results_page('uggl'));
    }

    public function testFindsEveryWordOfTheSearch(): void
    {
        self::assertSame([self::PANDOR], $this->suggestions('panda födelsetavla'));
        self::assertSame([self::PANDOR], $this->results_page('panda födelsetavla'));
    }

    public function testLeavesOutAProductWhereTheStemIsInsideAWord(): void
    {
        self::assertNotContains(self::EXPANDER, $this->suggestions('panda'));
        self::assertNotContains(self::EXPANDER, $this->results_page('panda'));
    }

    public function testLeavesOutAnExcludedWord(): void
    {
        self::assertSame([self::PANDA_MUG, self::POSTER], $this->results_page('panda -födelsetavla'));
    }

    public function testTheSuggestionsStillFindAProductBySku(): void
    {
        self::assertSame([self::EXPANDER], $this->suggestions(self::SKU));
    }

    public function testTheSuggestionsKeepAnOrderTheRequestAsksFor(): void
    {
        self::assertSame(
            [self::POSTER, self::PANDA_MUG, self::PANDOR],
            $this->suggestions('panda', ['orderby' => 'title', 'order' => 'asc'])
        );
    }

    public function testASearchOfOtherContentMatchesAsWordPressDoes(): void
    {
        global $wpdb;

        $stem_at_a_word_start = "LIKE '% pand%'";
        $request              = static fn (string $post_type): string => $wpdb->remove_placeholder_escape(
            (new WP_Query(['s' => 'panda', 'post_type' => $post_type, 'fields' => 'ids']))->request
        );

        self::assertStringContainsString($stem_at_a_word_start, $request('product'));
        self::assertStringNotContainsString($stem_at_a_word_start, $request('page'));
    }

    /**
     * The products' names a search lists on the results page, best first, as
     * WooCommerce orders a search there by default.
     *
     * @return list<string>
     */
    private function results_page(string $search): array
    {
        $query = new WP_Query([
            's'         => $search,
            'post_type' => 'product',
            'orderby'   => 'relevance',
            'post__in'  => array_values($this->ids),
        ]);

        return array_values(array_map(
            static fn (WP_Post|int $post): string => get_the_title($post),
            $query->posts
        ));
    }

    /**
     * The products' names the Store API lists for a search, as the header's
     * suggestions ask for them.
     *
     * @param array<string, string> $params
     *
     * @return list<string>
     */
    private function suggestions(string $search, array $params = []): array
    {
        $request = new WP_REST_Request('GET', self::STORE_API_PRODUCTS);
        $request->set_query_params(['search' => $search, 'include' => array_values($this->ids), 'catalog_visibility' => 'search'] + $params);

        $response = rest_do_request($request);
        self::assertSame(200, $response->get_status(), (string) wp_json_encode($response->get_data()));

        $data = $response->get_data();
        self::assertIsArray($data);

        return array_values(array_map(
            static fn (array $product): string => html_entity_decode((string) $product['name'], \ENT_QUOTES),
            $data
        ));
    }

    private function product(string $name, string $description = '', string $sku = ''): void
    {
        $product = new WC_Product_Simple();
        $product->set_name($name);
        $product->set_description($description);
        $product->set_regular_price(self::PRICE);
        $product->set_status('publish');

        if ('' !== $sku) {
            $product->set_sku($sku);
        }

        $this->ids[$name] = $product->save();
    }
}
