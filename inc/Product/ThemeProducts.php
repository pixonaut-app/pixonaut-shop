<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Product;

use WP_HTML_Tag_Processor;
use WP_Post;

/**
 * The product page's "More in the theme" section
 * (`patterns/theme-products.php`): other products that share the product's
 * tag, in any category. A store's product tags are the themes its designs
 * come in, as its categories are what they are for.
 *
 * A product with several tags is shown with the first by name, then by ID,
 * so the section is the same on every visit. The section renders only on a
 * product's page, for a product with a tag at least `MINIMUM_OTHERS` other
 * products a customer can buy share; otherwise it renders nothing, heading
 * included.
 *
 * Its product collection is the pattern's own, which the store edits in the
 * Site Editor. While the section renders, this narrows that collection's
 * query to the tag, without the product itself, writes the tag's name into
 * the heading and points the "See all" link at the tag's archive.
 */
final class ThemeProducts
{
    /** The class of the group `patterns/theme-products.php` prints. */
    public const CLASS_NAME = 'pixonaut-shop-theme-products';

    /** The class of the section's heading, which names the tag. */
    public const TITLE_CLASS = 'pixonaut-shop-theme-products-title';

    /** The class of the paragraph holding the link to the tag's archive. */
    public const LINK_CLASS = 'pixonaut-shop-view-all';

    /** How many products the section shows. */
    public const COUNT = 4;

    /** The fewest other products with the tag the section is shown for. */
    public const MINIMUM_OTHERS = 2;

    /** The stock statuses a customer can buy in, as the related products show. */
    public const STOCK_STATUSES = PopularProducts::STOCK_STATUSES;

    /** The product collection's order: the most sold first. */
    public const COLLECTION_ORDER = PopularProducts::COLLECTION_ORDER;

    /** WooCommerce's product tags. */
    public const TAXONOMY = 'product_tag';

    /** What the pattern's heading names in place of a tag, for the Site Editor. */
    public const PLACEHOLDER = '…';

    private const GROUP_BLOCK = 'core/group';

    /** A post ID no post has, for a `post__in` that must list nothing, as WooCommerce writes one. */
    private const NO_PRODUCT = 0;

    private const PRODUCT_POST_TYPE = 'product';

    private const PUBLISHED = 'publish';

    /** Products shown in the shop and its search, as a product collection lists them. */
    private const CATALOGUE = 'catalog';

    /**
     * A product card as WooCommerce's product template prints it: the
     * store's own settings on the collection may show fewer than were
     * counted before it rendered.
     */
    private const CARD = '#<li\b[^>]*\bclass="(?:[^"]*\s)?wc-block-product(?:\s[^"]*)?"#';

    /** The product and tag the section was found for, before it renders. */
    private ?ThemeProductsShown $found = null;

    /** The product and tag of the section rendering now. */
    private ?ThemeProductsShown $rendering = null;

    public function register(): void
    {
        add_filter('pre_render_block', [$this, 'skip'], 10, 2);
        add_filter('render_block_data', [$this, 'start'], 10, 1);
        add_filter('query_loop_block_query_vars', [$this, 'narrow'], 20, 2);
        add_filter('render_block_core/group', [$this, 'render'], 10, 2);
    }

    /**
     * The heading's text for a tag's name.
     */
    public static function title(string $tag_name): string
    {
        /* translators: %s: the name of a product tag, such as a design's theme. */
        return \sprintf(__('More in the theme %s', 'pixonaut-shop'), $tag_name);
    }

    /**
     * A product's first tag, as `get_the_terms()` lists its tags: by name,
     * then by ID for two of the same name.
     *
     * @param array<array-key, mixed> $tags
     */
    public static function first_tag(int $product_id, array $tags): ?ThemeProductsShown
    {
        $shown = [];

        foreach ($tags as $tag) {
            if (\is_object($tag) && isset($tag->term_id, $tag->name) && is_numeric($tag->term_id) && \is_string($tag->name)) {
                $shown[] = new ThemeProductsShown($product_id, (int) $tag->term_id, $tag->name);
            }
        }

        usort($shown, [self::class, 'by_name_then_id']);

        return $shown[0] ?? null;
    }

    /**
     * Orders two tags by name, ignoring case, then by ID.
     */
    private static function by_name_then_id(ThemeProductsShown $a, ThemeProductsShown $b): int
    {
        $by_name = strnatcasecmp($a->tag_name, $b->tag_name);

        return 0 !== $by_name ? $by_name : $a->tag_id <=> $b->tag_id;
    }

    /**
     * Leaves the section out before it renders when there is nothing to
     * show, so its collection's query never runs.
     *
     * @param array<array-key, mixed> $parsed_block
     */
    public function skip(?string $pre_render, array $parsed_block): ?string
    {
        if (null !== $pre_render || ! $this->is_section($parsed_block)) {
            return $pre_render;
        }

        $this->found = $this->find();

        return null === $this->found ? '' : null;
    }

    /**
     * Starts narrowing product collections to the tag as the section starts
     * rendering.
     *
     * @param array<array-key, mixed> $parsed_block
     *
     * @return array<array-key, mixed>
     */
    public function start(array $parsed_block): array
    {
        if ($this->is_section($parsed_block)) {
            $this->rendering = $this->found;
            $this->found = null;
        }

        return $parsed_block;
    }

    /**
     * Narrows the section's product collection to the products with the tag,
     * without the product the page is for.
     *
     * @param array<array-key, mixed> $query
     *
     * @return array<array-key, mixed>
     */
    public function narrow(array $query, mixed $block = null): array
    {
        $context = \is_object($block) && isset($block->context) && \is_array($block->context) ? $block->context : [];
        $collection = \is_array($context['query'] ?? null) ? $context['query'] : [];

        if (null === $this->rendering || true !== ($collection['isProductCollectionBlock'] ?? null)) {
            return $query;
        }

        $tax_query = \is_array($query['tax_query'] ?? null) ? $query['tax_query'] : [];
        $tax_query[] = [
            'taxonomy' => self::TAXONOMY,
            'field'    => 'term_id',
            'terms'    => [$this->rendering->tag_id],
        ];

        $query['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- the tag the section is about.

        return $this->without_product($query, $this->rendering->product_id);
    }

    /**
     * A query without a product. WP_Query ignores `post__not_in` beside
     * `post__in`, which WooCommerce sets for a collection of products on
     * sale or picked by hand, so the product leaves that list instead.
     *
     * @param array<array-key, mixed> $query
     *
     * @return array<array-key, mixed>
     */
    private function without_product(array $query, int $product_id): array
    {
        $included = \is_array($query['post__in'] ?? null) ? $query['post__in'] : [];

        if ([] !== $included) {
            $kept = array_values(array_filter($included, static fn (mixed $id): bool => ! is_numeric($id) || (int) $id !== $product_id));
            // An empty post__in lists every product; a list of none lists nothing.
            $query['post__in'] = [] === $kept ? [self::NO_PRODUCT] : $kept;

            return $query;
        }

        $excluded = \is_array($query['post__not_in'] ?? null) ? $query['post__not_in'] : [];
        $excluded[] = $product_id;
        $query['post__not_in'] = $excluded; // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- the product the page is for.

        return $query;
    }

    /**
     * Names the tag in the heading and links its archive, or drops the
     * section when its collection shows fewer than `MINIMUM_OTHERS` cards
     * after all, as one the store narrowed in the Site Editor may.
     *
     * @param array<array-key, mixed> $block
     */
    public function render(string $block_content, array $block): string
    {
        if (! $this->is_section($block)) {
            return $block_content;
        }

        $shown = $this->rendering;
        $this->rendering = null;

        if (null === $shown || preg_match_all(self::CARD, $block_content) < self::MINIMUM_OTHERS) {
            return '';
        }

        $titled = preg_replace_callback(
            '#(<h([1-6])\b[^>]*\bclass="[^"]*\b' . self::TITLE_CLASS . '\b[^"]*"[^>]*>).*?(</h\2>)#s',
            static fn (array $heading): string => $heading[1] . esc_html(self::title($shown->tag_name)) . $heading[3],
            $block_content,
            1
        );

        return $this->link($titled ?? $block_content, $shown->tag_id);
    }

    private function link(string $html, int $tag_id): string
    {
        $url = get_term_link($tag_id, self::TAXONOMY);

        if (! \is_string($url)) {
            return $html;
        }

        $processor = new WP_HTML_Tag_Processor($html);

        if (! $processor->next_tag(['class_name' => self::LINK_CLASS])) {
            return $html;
        }

        $holder = $processor->get_tag();

        // The link inside the paragraph only: never a card's, after it.
        while ($processor->next_tag(['tag_closers' => 'visit'])) {
            if ($processor->is_tag_closer() && $holder === $processor->get_tag()) {
                break;
            }

            if (! $processor->is_tag_closer() && 'A' === $processor->get_tag()) {
                $processor->set_attribute('href', $url);
                break;
            }
        }

        return $processor->get_updated_html();
    }

    /**
     * The product the page is for and its first tag, when at least
     * `MINIMUM_OTHERS` other products a customer can buy share it.
     */
    private function find(): ?ThemeProductsShown
    {
        if (! \function_exists('wc_get_products')) {
            return null;
        }

        $product = get_queried_object();

        if (! $product instanceof WP_Post || self::PRODUCT_POST_TYPE !== $product->post_type) {
            return null;
        }

        $tags = get_the_terms($product->ID, self::TAXONOMY);
        $shown = \is_array($tags) ? self::first_tag($product->ID, $tags) : null;

        if (null === $shown) {
            return null;
        }

        $others = wc_get_products([
            'status'       => self::PUBLISHED,
            'visibility'   => self::CATALOGUE,
            'stock_status' => self::STOCK_STATUSES,
            'product_tag_id' => [$shown->tag_id],
            'exclude'      => [$product->ID],
            'limit'        => self::MINIMUM_OTHERS,
            'return'       => 'ids',
        ]);

        return \is_array($others) && \count($others) >= self::MINIMUM_OTHERS ? $shown : null;
    }

    /**
     * @param array<array-key, mixed> $block
     */
    private function is_section(array $block): bool
    {
        if (self::GROUP_BLOCK !== ($block['blockName'] ?? null)) {
            return false;
        }

        $attributes = $block['attrs'] ?? [];
        $class_name = \is_array($attributes) ? ($attributes['className'] ?? '') : '';

        return \is_string($class_name) && \in_array(self::CLASS_NAME, explode(' ', $class_name), true);
    }
}
