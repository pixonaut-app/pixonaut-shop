<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Search;

use WP_Query;

/**
 * What shoppers search for most, counted from the store's own results page,
 * for the header search to suggest before a shopper types.
 *
 * A search counts when the results page shows its first page of products:
 * one that found nothing is never counted, so a name typed into a gift
 * shop's search, or a word that matches no product, never becomes a
 * suggestion. Nor is a search that excludes a word ("-word"), which finds
 * products without matching what was typed. The header's suggestions as a shopper types ask the same
 * page for JSON and stop before it is counted, and the store's own staff
 * are not counted, so a search is a shopper's.
 *
 * The counts age: a search not made for `KEPT_FOR` is forgotten, so a
 * season's searches give way to the next season's. More searches are
 * counted than are shown: the most made (`LEADING_POOL`), and beside them
 * the latest of the rest (`CANDIDATE_POOL`), so a new search always has
 * room to count up until it catches up with the most made.
 *
 * Two searches counted at once can lose one of the two counts.
 */
final class PopularSearches
{
    /** The option the counts are kept in: by normalised search, its words as last typed, its count and when it was last made. */
    public const OPTION = 'pixonaut_shop_popular_searches';

    /** How many searches the header suggests. */
    public const SHOWN = 6;

    /** How often a search must have been made to be suggested. */
    public const MIN_SEARCHES = 2;

    /** How many of the most made searches are kept; the least made, then the oldest, give way. */
    public const LEADING_POOL = 150;

    /** How many of the other searches are kept, still counting up; the oldest give way. */
    public const CANDIDATE_POOL = 50;

    /**
     * Before every key, so a key stays a string: PHP turns an array key that
     * reads as a whole number ("2026", a numeric SKU) into an integer.
     */
    private const KEY_PREFIX = 'search:';

    /** WordPress's prefix that excludes a word from a search, unless a filter changes it. */
    private const DEFAULT_EXCLUSION_PREFIX = '-';

    /** How many days a search is remembered after it was last made: a season. */
    public const KEPT_DAYS = 90;

    /** The seconds in a day, as WordPress's `DAY_IN_SECONDS`, which is defined only once WordPress has loaded. */
    private const SECONDS_PER_DAY = 86_400;

    /** How long a search is remembered after it was last made, in seconds. */
    public const KEPT_FOR = self::KEPT_DAYS * self::SECONDS_PER_DAY;

    /** The shortest search counted, in letters: as short as the header suggests from. */
    public const MIN_LENGTH = 2;

    /** The longest search counted, in letters: longer is a sentence, not a search to suggest. */
    public const MAX_LENGTH = 40;

    /** After the header's suggestions answer (and end) a search at priority 0. */
    private const AFTER_SUGGESTIONS = 10;

    /** The capability of the store's staff, whose searches are not counted. */
    private const STAFF = 'manage_woocommerce';

    /** The post type the search looks among: the store's products. */
    private const PRODUCT = 'product';

    /** The first page of results; a later page is the same search again. */
    private const FIRST_PAGE = 1;

    public function register(): void
    {
        add_action('template_redirect', [$this, 'count_request'], self::AFTER_SUGGESTIONS);
    }

    /** Counts the search the results page shows, when it is a shopper's and found products. */
    public function count_request(): void
    {
        $query = $GLOBALS['wp_query'] ?? null;

        if (! $query instanceof WP_Query || ! $query->is_main_query() || ! $query->is_search() || current_user_can(self::STAFF)) {
            return;
        }

        $page = $query->get('paged');
        $search = $query->get('s');

        if (self::PRODUCT !== $query->get('post_type') || (is_numeric($page) && (int) $page > self::FIRST_PAGE) || $query->found_posts < 1 || ! \is_string($search)) {
            return;
        }

        self::count($search, time());
    }

    /** Counts one search, made at `$now`, and forgets what has aged. */
    public static function count(string $search, int $now): void
    {
        $words = self::words($search);

        if (null === $words) {
            return;
        }

        $key = self::key($words);
        $counts = self::kept(self::stored(), $now);
        $counts[$key] = [
            'words' => $words,
            'count' => ($counts[$key]['count'] ?? 0) + 1,
            'last'  => $now,
        ];

        update_option(self::OPTION, self::pooled($counts), true);
    }

    /**
     * The searches to suggest at `$now`, most made first: each made at least
     * `MIN_SEARCHES` times within `KEPT_FOR`, at most `SHOWN`.
     *
     * @return list<string>
     */
    public static function shown(int $now): array
    {
        $counts = array_filter(
            self::kept(self::stored(), $now),
            static fn (array $search): bool => $search['count'] >= self::MIN_SEARCHES
        );

        uasort($counts, [self::class, 'compare']);

        return array_values(array_map(
            static fn (array $search): string => $search['words'],
            \array_slice($counts, 0, self::SHOWN)
        ));
    }

    /**
     * A search's words as a shopper typed them, spaces tidied; null for one
     * too short or too long to count, or one that excludes a word.
     */
    private static function words(string $search): ?string
    {
        $words = trim((string) preg_replace('/\s+/u', ' ', wp_strip_all_tags($search)));
        $length = mb_strlen($words);
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own filter, read as its search reads it.
        $exclusion = apply_filters('wp_query_search_exclusion_prefix', self::DEFAULT_EXCLUSION_PREFIX);

        if (\is_string($exclusion) && '' !== $exclusion) {
            foreach (explode(' ', $words) as $word) {
                if (str_starts_with($word, $exclusion)) {
                    return null;
                }
            }
        }

        return $length >= self::MIN_LENGTH && $length <= self::MAX_LENGTH ? $words : null;
    }

    /** One key for every way of typing a search: in lower case. */
    private static function key(string $words): string
    {
        return self::KEY_PREFIX . mb_strtolower($words);
    }

    /**
     * The counts to keep: the most made, then the latest of the rest.
     *
     * @param array<string, array{words: string, count: int, last: int}> $counts
     *
     * @return array<string, array{words: string, count: int, last: int}>
     */
    private static function pooled(array $counts): array
    {
        uasort($counts, [self::class, 'compare']);
        $leading = \array_slice($counts, 0, self::LEADING_POOL, true);
        $candidates = \array_slice($counts, self::LEADING_POOL, null, true);

        uasort($candidates, static fn (array $one, array $other): int => $other['last'] <=> $one['last']);

        return $leading + \array_slice($candidates, 0, self::CANDIDATE_POOL, true);
    }

    /**
     * @param array<string, array{words: string, count: int, last: int}> $counts
     *
     * @return array<string, array{words: string, count: int, last: int}>
     */
    private static function kept(array $counts, int $now): array
    {
        return array_filter($counts, static fn (array $search): bool => $now - $search['last'] < self::KEPT_FOR);
    }

    /**
     * The counts as saved, each one checked, as the option can hold anything.
     *
     * @return array<string, array{words: string, count: int, last: int}>
     */
    private static function stored(): array
    {
        $saved = get_option(self::OPTION, []);
        $counts = [];

        foreach (\is_array($saved) ? $saved : [] as $key => $search) {
            if (\is_string($key) && \is_array($search) && \is_string($search['words'] ?? null) && \is_int($search['count'] ?? null) && \is_int($search['last'] ?? null)) {
                $counts[$key] = ['words' => $search['words'], 'count' => $search['count'], 'last' => $search['last']];
            }
        }

        return $counts;
    }

    /**
     * The more made first, then the more recent.
     *
     * @param array{count: int, last: int} $one
     * @param array{count: int, last: int} $other
     */
    private static function compare(array $one, array $other): int
    {
        return [$other['count'], $other['last']] <=> [$one['count'], $one['last']];
    }
}
