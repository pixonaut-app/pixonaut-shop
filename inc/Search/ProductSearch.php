<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Search;

use WP_Query;
use wpdb;

/**
 * A product search that finds a product by another form of a word in its
 * name or description: "panda" finds "Pandor", "uggla" finds "Ugglor".
 *
 * A word matches as WordPress matches it, anywhere in a product's name,
 * short description or description, or by its stem (`WordStem`) at the
 * start of a word there. Products that hold every word as typed come
 * first, those with it in the name before the rest; then those found by
 * stems, again by name first.
 *
 * Both of the store's searches go through here, so they find the same
 * products: the results page, which is a WordPress search of products, and
 * the header's suggestions, which come from WooCommerce's Store API. The
 * Store API matches its `search` against names and SKUs on its own; its
 * query searches as WordPress does instead, by relevance unless the request
 * asked for another order, and still by SKU.
 */
final class ProductSearch
{
    /** The post type the search looks among: the store's products. */
    private const PRODUCT = 'product';

    /** The Store API's own search text, which its query holds apart from WordPress's `s`. */
    private const STORE_API_SEARCH = 'search';

    /** How the Store API orders a listing by default: newest first. */
    private const STORE_API_DEFAULT_ORDER = 'date ID';

    /** WordPress's order of search results by how well they match. */
    private const RELEVANCE = 'relevance';

    /** The columns WordPress searches by default, and the product's name among them. */
    private const COLUMNS = ['post_title', 'post_excerpt', 'post_content'];

    private const TITLE = 'post_title';

    /**
     * What may come before a word in a name or description, besides its
     * start: a space, a line break, a hyphen, a slash, an opening
     * parenthesis, or the end of an HTML tag.
     */
    private const WORD_BREAKS = [' ', "\n", '-', '/', '(', '>'];

    /** WordPress's prefix that excludes a term from a search, by default. */
    private const DEFAULT_EXCLUSION_PREFIX = '-';

    /** WooCommerce's table of product data to sort and filter by, the SKU among it, without the prefix. */
    private const PRODUCT_LOOKUP_TABLE = 'wc_product_meta_lookup';

    /** The ranks of a match, best first. */
    private const RANK_EXACT_IN_NAME = 0;

    private const RANK_EXACT = 1;

    private const RANK_STEM_IN_NAME = 2;

    private const RANK_STEM = 3;

    public function register(): void
    {
        add_action('pre_get_posts', [$this, 'search_store_api_as_wordpress']);
        add_filter('posts_search', [$this, 'where'], 10, 2);
        add_filter('posts_search_orderby', [$this, 'order'], 10, 2);
    }

    /**
     * The Store API's product search as a WordPress search, so it matches
     * as the results page does.
     */
    public function search_store_api_as_wordpress(WP_Query $query): void
    {
        $search = $query->get(self::STORE_API_SEARCH);

        if (! \is_string($search) || '' === trim($search) || '' !== $query->get('s') || self::PRODUCT !== $query->get('post_type')) {
            return;
        }

        $query->set('s', $search);
        $query->set(self::STORE_API_SEARCH, '');

        if (self::STORE_API_DEFAULT_ORDER === $query->get('orderby')) {
            $query->set('orderby', self::RELEVANCE);
        }
    }

    /**
     * The search's WHERE part for a product search: every positive term as
     * typed or by its stem, no excluded term, or the whole search in a SKU.
     */
    public function where(string $search, WP_Query $query): string
    {
        $terms = $this->terms($query);

        if (null === $terms) {
            return $search;
        }

        $parts = [];

        foreach ($terms['excluded'] as $term) {
            $parts[] = $this->none_of($term);
        }

        $matches = array_map(fn (string $term): string => $this->any_column($term, self::COLUMNS, true), $terms['positive']);

        if ([] !== $matches) {
            $match   = implode(' AND ', $matches);
            $text    = $query->get('s');
            $sku     = $this->sku(\is_string($text) ? $text : '');
            $parts[] = '' === $sku ? "({$match})" : "(({$match}) OR {$sku})";
        }

        if ([] === $parts) {
            return $search;
        }

        $where = ' AND (' . implode(' AND ', $parts) . ') ';

        if (! is_user_logged_in()) {
            $where .= self::db()->prepare(' AND (%i.post_password = %s) ', self::db()->posts, '');
        }

        return $where;
    }

    /**
     * Products with every term as typed first, then those found by stems;
     * each by name before the rest, then in WordPress's own order.
     */
    public function order(string $search_orderby, WP_Query $query): string
    {
        $terms = $this->terms($query);

        if ('' === trim($search_orderby) || null === $terms || [] === $terms['positive']) {
            return $search_orderby;
        }

        $rank = \sprintf(
            '(CASE WHEN %s THEN %d WHEN %s THEN %d WHEN %s THEN %d ELSE %d END)',
            $this->every($terms['positive'], [self::TITLE], false),
            self::RANK_EXACT_IN_NAME,
            $this->every($terms['positive'], self::COLUMNS, false),
            self::RANK_EXACT,
            $this->every($terms['positive'], [self::TITLE], true),
            self::RANK_STEM_IN_NAME,
            self::RANK_STEM
        );

        return $rank . ' ASC, ' . $search_orderby;
    }

    /**
     * A product search's terms as WordPress parsed them, the excluded ones
     * apart; null for any other query, or one that matches whole sentences
     * or exact values, which WordPress searches as it does.
     *
     * @return array{positive: list<string>, excluded: list<string>}|null
     */
    private function terms(WP_Query $query): ?array
    {
        $terms = $query->get('search_terms');

        if (is_admin() || self::PRODUCT !== $query->get('post_type') || '' === $query->get('s') || ! \is_array($terms)
            || ! empty($query->get('exact')) || ! empty($query->get('sentence'))) {
            return null;
        }

        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own filter, read as its search reads it.
        $prefix = apply_filters('wp_query_search_exclusion_prefix', self::DEFAULT_EXCLUSION_PREFIX);
        $prefix = \is_string($prefix) ? $prefix : '';
        $sorted = ['positive' => [], 'excluded' => []];

        foreach ($terms as $term) {
            if (! \is_string($term) || '' === $term) {
                continue;
            }

            if ('' !== $prefix && str_starts_with($term, $prefix)) {
                $sorted['excluded'][] = substr($term, \strlen($prefix));
            } else {
                $sorted['positive'][] = $term;
            }
        }

        return $sorted;
    }

    /**
     * Every term in one of the columns, as typed or, with `$stems`, by its stem.
     *
     * @param list<string> $terms
     * @param list<string> $columns
     */
    private function every(array $terms, array $columns, bool $stems): string
    {
        return '(' . implode(' AND ', array_map(fn (string $term): string => $this->any_column($term, $columns, $stems), $terms)) . ')';
    }

    /**
     * The term anywhere in one of the columns, or, with `$stems`, its stem
     * at the start of a word there.
     *
     * @param list<string> $columns
     */
    private function any_column(string $term, array $columns, bool $stems): string
    {
        $db       = self::db();
        $patterns = ['%' . $db->esc_like($term) . '%'];
        $stem     = (new WordStem(get_locale()))->of($term);

        if ($stems && $stem !== mb_strtolower($term)) {
            $patterns[] = $db->esc_like($stem) . '%';

            foreach (self::WORD_BREAKS as $break) {
                $patterns[] = '%' . $db->esc_like($break . $stem) . '%';
            }
        }

        $likes = [];

        foreach ($columns as $column) {
            foreach ($patterns as $pattern) {
                $likes[] = (string) $db->prepare('%i.%i LIKE %s', $db->posts, $column, $pattern);
            }
        }

        return '(' . implode(' OR ', $likes) . ')';
    }

    /** The excluded term in none of the columns, as WordPress excludes it. */
    private function none_of(string $term): string
    {
        $db    = self::db();
        $like  = '%' . $db->esc_like($term) . '%';
        $parts = array_map(
            static fn (string $column): string => (string) $db->prepare('%i.%i NOT LIKE %s', $db->posts, $column, $like),
            self::COLUMNS
        );

        return '(' . implode(' AND ', $parts) . ')';
    }

    /**
     * The whole search in a product's SKU, as the Store API matched it;
     * empty when the store does not use SKUs.
     */
    private function sku(string $search): string
    {
        if (! \function_exists('wc_product_sku_enabled') || ! wc_product_sku_enabled()) {
            return '';
        }

        $db = self::db();

        return (string) $db->prepare(
            '%i.ID IN (SELECT product_id FROM %i WHERE sku LIKE %s)',
            $db->posts,
            $db->prefix . self::PRODUCT_LOOKUP_TABLE,
            '%' . $db->esc_like(trim($search)) . '%'
        );
    }

    private static function db(): wpdb
    {
        global $wpdb;

        \assert($wpdb instanceof wpdb);

        return $wpdb;
    }
}
