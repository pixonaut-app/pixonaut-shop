<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Search;

use WP_Query;
use WP_REST_Request;
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
 * asked for an order, and still by SKU.
 *
 * A query that narrows the columns WordPress searches keeps to them, and is
 * then not matched by SKU either.
 */
final class ProductSearch
{
    /** The post type the search looks among: the store's products. */
    private const PRODUCT = 'product';

    /**
     * The post types a Store API listing looks among when it is narrowed by
     * SKU or slug, which may name a variation.
     */
    private const PRODUCT_TYPES = [self::PRODUCT, 'product_variation'];

    /** The Store API's product listing, whose search this reads as WordPress's. */
    private const STORE_API_PRODUCTS_ROUTE = '/wc/store/v1/products';

    /** The listing's parameter for its order. */
    private const ORDER_PARAM = 'orderby';

    /** The Store API's own search text, which its query holds apart from WordPress's `s`. */
    private const STORE_API_SEARCH = 'search';

    /** How the Store API orders a listing by default, or when asked for by date: newest first. */
    private const STORE_API_DATE_ORDER = 'date ID';

    /** How WordPress splits a search into terms, as `WP_Query::parse_search()` does. */
    private const TERM_SEPARATORS = '/[\t ",+]+/';

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

    /**
     * Whether the Store API request being served lists products without
     * asking for an order, so its search may be ordered by relevance.
     */
    private bool $store_api_order_free = false;

    public function register(): void
    {
        add_filter('rest_request_before_callbacks', [$this, 'note_store_api_order'], 10, 3);
        add_filter('rest_request_after_callbacks', [$this, 'forget_store_api_order']);
        add_action('pre_get_posts', [$this, 'search_store_api_as_wordpress']);
        add_filter('posts_search', [$this, 'where'], 10, 2);
        add_filter('posts_search_orderby', [$this, 'order'], 10, 2);
    }

    /**
     * Notes whether a Store API product listing asks for an order: its
     * query cannot tell, since WooCommerce turns both the default and an
     * order by date into the same `date ID`.
     *
     * @param WP_REST_Request<array<string, mixed>> $request
     */
    public function note_store_api_order(mixed $response, mixed $handler, WP_REST_Request $request): mixed
    {
        $this->store_api_order_free = self::STORE_API_PRODUCTS_ROUTE === $request->get_route()
            && ! \array_key_exists(self::ORDER_PARAM, $request->get_query_params())
            && ! \array_key_exists(self::ORDER_PARAM, $request->get_body_params())
            && ! \array_key_exists(self::ORDER_PARAM, $request->get_json_params() ?? []);

        return $response;
    }

    public function forget_store_api_order(mixed $response): mixed
    {
        $this->store_api_order_free = false;

        return $response;
    }

    /**
     * The Store API's product search as a WordPress search, so it matches
     * as the results page does.
     */
    public function search_store_api_as_wordpress(WP_Query $query): void
    {
        $search = $query->get(self::STORE_API_SEARCH);

        if (! \is_string($search) || '' === trim($search) || '' !== $query->get('s') || ! self::looks_among_products($query)) {
            return;
        }

        $query->set('s', $search);
        $query->set(self::STORE_API_SEARCH, '');

        // Relevance needs a term to match: WordPress cannot rank a search that only excludes.
        if ($this->store_api_order_free && self::STORE_API_DATE_ORDER === $query->get('orderby') && self::has_positive_term($search)) {
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

        $columns = self::columns($query);
        $parts   = [];

        foreach ($terms['excluded'] as $term) {
            $parts[] = $this->none_of($term, $columns);
        }

        $matches = array_map(fn (string $term): string => $this->any_column($term, $columns, true), $terms['positive']);

        if ([] !== $matches) {
            $match   = implode(' AND ', $matches);
            $text    = $query->get('s');
            $sku     = self::COLUMNS === $columns ? $this->sku(\is_string($text) ? $text : '') : '';
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

        if (null === $terms) {
            return $search_orderby;
        }

        // WordPress ranks by the terms to match; with none, its ranking is not valid SQL.
        if ([] === $terms['positive'] || '' === trim($search_orderby)) {
            return '';
        }

        $rank = \sprintf(
            '(CASE WHEN %s THEN %d WHEN %s THEN %d WHEN %s THEN %d ELSE %d END)',
            $this->every($terms['positive'], [self::TITLE], false),
            self::RANK_EXACT_IN_NAME,
            $this->every($terms['positive'], self::columns($query), false),
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

        if (is_admin() || ! self::looks_among_products($query) || '' === $query->get('s') || ! \is_array($terms)
            || ! empty($query->get('exact')) || ! empty($query->get('sentence'))) {
            return null;
        }

        $prefix = self::exclusion_prefix();
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
     * Whether the query looks among products: the store's products alone,
     * or, narrowed by SKU or slug, its products and their variations.
     */
    private static function looks_among_products(WP_Query $query): bool
    {
        $post_type = $query->get('post_type');

        if (self::PRODUCT === $post_type) {
            return true;
        }

        if (! \is_array($post_type) || ! \in_array(self::PRODUCT, $post_type, true)) {
            return false;
        }

        foreach ($post_type as $type) {
            if (! \in_array($type, self::PRODUCT_TYPES, true)) {
                return false;
            }
        }

        return true;
    }

    /** Whether a search holds a term that is not excluded, as WordPress splits it into terms. */
    private static function has_positive_term(string $search): bool
    {
        $prefix = self::exclusion_prefix();

        $terms  = preg_split(self::TERM_SEPARATORS, $search, -1, \PREG_SPLIT_NO_EMPTY);

        foreach (false === $terms ? [] : $terms as $term) {
            if ('' === $prefix || ! str_starts_with($term, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /** The prefix that excludes a term from a search, as WordPress filters it; empty when exclusions are off. */
    private static function exclusion_prefix(): string
    {
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own filter, read as its search reads it.
        $prefix = apply_filters('wp_query_search_exclusion_prefix', self::DEFAULT_EXCLUSION_PREFIX);

        return \is_string($prefix) ? $prefix : '';
    }

    /**
     * The columns the query searches, as `WP_Query::parse_search()` works
     * them out: its own, as WordPress filters them, of those it supports;
     * all of them when none is left.
     *
     * @return list<string>
     */
    private static function columns(WP_Query $query): array
    {
        $asked   = $query->get('search_columns');
        $asked   = [] !== $asked && '' !== $asked && null !== $asked ? (array) $asked : self::COLUMNS;
        $text    = $query->get('s');
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own filter, read as its search reads it.
        $columns = (array) apply_filters('post_search_columns', $asked, \is_string($text) ? $text : '', $query);
        $columns = array_values(array_filter(self::COLUMNS, static fn (string $column): bool => \in_array($column, $columns, true)));

        return [] === $columns ? self::COLUMNS : $columns;
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

    /**
     * The excluded term in none of the columns, as WordPress excludes it.
     *
     * @param list<string> $columns
     */
    private function none_of(string $term, array $columns): string
    {
        $db    = self::db();
        $like  = '%' . $db->esc_like($term) . '%';
        $parts = array_map(
            static fn (string $column): string => (string) $db->prepare('%i.%i NOT LIKE %s', $db->posts, $column, $like),
            $columns
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
