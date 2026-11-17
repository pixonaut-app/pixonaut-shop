<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pixonaut\Shop\Header\SearchSuggestions;
use Pixonaut\Shop\Tests\Support\SplitCardFixtures;
use WP_Query;

/**
 * The header search's suggestions, answered by the results page's own
 * search: what they count and list is what the page lists, a product the
 * page lists once per card (`tests/e2e/fake-split-cards.php`) once per
 * card, each card with its own name and link.
 */
final class SearchSuggestionsTest extends TestCase
{
    private const UNMATCHED_QUERY = 'Nothingmatchesthis';

    /** WooCommerce's results count, which the results page prints above its products. */
    private const RESULTS_COUNT = '/<p class="woocommerce-result-count"[^>]*>(.*?)<\/p>/s';

    /** The count's wording when the search found one product, which names no number. */
    private const SINGLE_RESULT = 'single result';

    protected function setUp(): void
    {
        parent::setUp();
        SplitCardFixtures::make();
    }

    protected function tearDown(): void
    {
        SplitCardFixtures::forget();
        // The main query is the one object a request has; empty it again.
        $GLOBALS['wp_query']->init();
        wp_reset_postdata();
        parent::tearDown();
    }

    public function testAProductListedOncePerCardIsSuggestedAndCountedPerCard(): void
    {
        $found = SearchSuggestions::of($this->search(SplitCardFixtures::QUERY));

        self::assertSame(\count(SplitCardFixtures::CARDS) + 1, $found['total']);

        $cards = array_values(array_filter(
            $found['suggestions'],
            static fn (array $suggestion): bool => str_starts_with($suggestion['name'], SplitCardFixtures::SPLIT_NAME)
        ));
        self::assertCount(\count(SplitCardFixtures::CARDS), $cards);

        foreach (SplitCardFixtures::CARDS as $index => $value) {
            self::assertSame(SplitCardFixtures::SPLIT_NAME . ' – ' . $value, $cards[$index]['name']);
            self::assertStringContainsString('fake_card=' . $value, $cards[$index]['url']);
            self::assertStringContainsString('149', $cards[$index]['price']);
        }
    }

    public function testAProductListedOnceIsSuggestedAsItIs(): void
    {
        $found = SearchSuggestions::of($this->search(SplitCardFixtures::PLAIN_NAME));

        self::assertSame(1, $found['total']);
        self::assertSame(SplitCardFixtures::PLAIN_NAME, $found['suggestions'][0]['name']);
        self::assertStringNotContainsString('fake_card', $found['suggestions'][0]['url']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function queries(): iterable
    {
        yield 'split and plain products' => [SplitCardFixtures::QUERY];
        yield 'the split product alone' => [SplitCardFixtures::SPLIT_NAME];
        yield 'the plain product alone' => [SplitCardFixtures::PLAIN_NAME];
        yield 'nothing' => [self::UNMATCHED_QUERY];
    }

    #[DataProvider('queries')]
    public function testTheSuggestionsCountWhatTheResultsPageCounts(string $search): void
    {
        $found = SearchSuggestions::of($this->search($search));

        self::assertSame($this->results_page_count($search), $found['total']);
        self::assertCount(min($found['total'], SearchSuggestions::LIMIT), $found['suggestions']);
    }

    public function testTheSearchAsksForTheSuggestionsByAPublicQueryVariable(): void
    {
        self::assertContains(SearchSuggestions::QUERY_VAR, apply_filters('query_vars', []));
    }

    /** The main query a visitor's product search makes, as the results page and the suggestions read it. */
    private function search(string $search): WP_Query
    {
        $main_query = $GLOBALS['wp_query'];
        self::assertInstanceOf(WP_Query::class, $main_query);
        $main_query->query(['s' => $search, 'post_type' => 'product']);
        self::assertTrue(is_search());

        return $main_query;
    }

    /** The count the results page prints for a search; 0 when it prints none, as for no match. */
    private function results_page_count(string $search): int
    {
        $this->search($search);
        $template = resolve_block_template('search', apply_filters('search_template_hierarchy', ['search']), '');
        self::assertNotNull($template);
        // WooCommerce sets up its loop once a request, from the main query.
        wc_reset_loop();
        $page = do_blocks($template->content);

        if (1 !== preg_match(self::RESULTS_COUNT, $page, $count)) {
            return 0;
        }

        $text = wp_strip_all_tags($count[1]);

        if (str_contains($text, self::SINGLE_RESULT)) {
            return 1;
        }

        self::assertGreaterThan(0, preg_match_all('/\d+/', $text, $numbers), "The results count names no number: {$text}");

        // "Showing all 3 results", or "Showing 1–12 of 30 results": the last number is the count.
        return (int) end($numbers[0]);
    }
}
