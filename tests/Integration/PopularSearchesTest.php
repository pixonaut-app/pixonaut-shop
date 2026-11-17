<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Pixonaut\Shop\Search\PopularSearches;
use Pixonaut\Shop\Tests\Support\SplitCardFixtures;
use WP_Query;

/**
 * The searches shoppers make most, counted from the results page: a
 * shopper's search that found products counts, made twice it is suggested,
 * and it is forgotten once it has not been made for a while.
 */
final class PopularSearchesTest extends TestCase
{
    /** A moment to count searches at, in seconds since the epoch. */
    private const NOW = 1_800_000_000;

    private const UNMATCHED_QUERY = 'Nothingmatchesthis';

    protected function setUp(): void
    {
        parent::setUp();
        delete_option(PopularSearches::OPTION);
        SplitCardFixtures::make();
    }

    protected function tearDown(): void
    {
        SplitCardFixtures::forget();
        delete_option(PopularSearches::OPTION);
        wp_set_current_user(0);
        $GLOBALS['wp_query']->init();
        wp_reset_postdata();
        parent::tearDown();
    }

    public function testASearchMadeTwiceIsSuggestedAsLastTyped(): void
    {
        PopularSearches::count('lantern', self::NOW);
        self::assertSame([], PopularSearches::shown(self::NOW));

        PopularSearches::count('  Lantern ', self::NOW);
        self::assertSame(['Lantern'], PopularSearches::shown(self::NOW));
    }

    public function testTheMostMadeComeFirstAndOnlyAsManyAsShown(): void
    {
        $made = PopularSearches::SHOWN + 2;

        for ($search = 1; $search <= $made; ++$search) {
            for ($time = 0; $time <= $search; ++$time) {
                PopularSearches::count('search ' . $search, self::NOW);
            }
        }

        $shown = PopularSearches::shown(self::NOW);

        self::assertCount(PopularSearches::SHOWN, $shown);
        self::assertSame('search ' . $made, $shown[0]);
    }

    public function testASearchNotMadeForAWhileIsForgotten(): void
    {
        PopularSearches::count('lantern', self::NOW);
        PopularSearches::count('lantern', self::NOW);

        self::assertSame([], PopularSearches::shown(self::NOW + PopularSearches::KEPT_FOR));
    }

    public function testTooShortOrTooLongIsNotCounted(): void
    {
        foreach (['l', str_repeat('lantern ', PopularSearches::MAX_LENGTH)] as $search) {
            PopularSearches::count($search, self::NOW);
            PopularSearches::count($search, self::NOW);
        }

        self::assertSame([], PopularSearches::shown(self::NOW));
    }

    public function testTheResultsPageCountsAShoppersSearchThatFoundProducts(): void
    {
        $this->results(SplitCardFixtures::QUERY);
        $this->results(SplitCardFixtures::QUERY);

        self::assertSame([SplitCardFixtures::QUERY], PopularSearches::shown(time()));
    }

    public function testASearchThatFoundNothingIsNotCounted(): void
    {
        $this->results(self::UNMATCHED_QUERY);
        $this->results(self::UNMATCHED_QUERY);

        self::assertSame([], PopularSearches::shown(time()));
    }

    public function testTheStoresStaffAreNotCounted(): void
    {
        $manager = self::factory_user('shop_manager');
        wp_set_current_user($manager);

        $this->results(SplitCardFixtures::QUERY);
        $this->results(SplitCardFixtures::QUERY);

        self::assertSame([], PopularSearches::shown(time()));

        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($manager);
    }

    public function testALaterPageOfResultsIsNotCountedAgain(): void
    {
        $this->results(SplitCardFixtures::QUERY, 2);
        $this->results(SplitCardFixtures::QUERY, 2);

        self::assertSame([], PopularSearches::shown(time()));
    }

    /** Runs the results page's main query for a search, and what the theme does before the page renders. */
    private function results(string $search, int $page = 1): void
    {
        $query = $GLOBALS['wp_query'];
        self::assertInstanceOf(WP_Query::class, $query);
        $query->query(['s' => $search, 'post_type' => 'product', 'paged' => $page]);

        (new PopularSearches())->count_request();
    }

    private static function factory_user(string $role): int
    {
        $login = 'popular-searches-' . $role;
        $existing = get_user_by('login', $login);

        if (false !== $existing) {
            return $existing->ID;
        }

        $id = wp_insert_user(['user_login' => $login, 'user_pass' => wp_generate_password(), 'role' => $role]);
        self::assertIsInt($id);

        return $id;
    }
}
