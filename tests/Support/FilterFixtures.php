<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Support;

use Automattic\WooCommerce\Internal\ProductFilters\CacheController;
use RuntimeException;
use WC_Product_Attribute;
use WC_Product_Variable;
use WC_Product_Variation;
use WP_Error;
use WP_Term;

/**
 * A category to filter, for the suites that open an archive's filters: two
 * prints sold in variations of the store's own product attributes, and an
 * attribute no product has. Idempotent, and removed again by `forget()`, so
 * no other test's lists or filters show any of it.
 */
final class FilterFixtures
{
    public const CATEGORY_SLUG = 'filtered-prints';

    private const CATEGORY_NAME = 'Filtered prints';

    private const CATEGORY_TAXONOMY = 'product_cat';

    /** Sold framed and unframed. */
    public const FRAMED_PRODUCT = 'Print sold framed';

    /** Sold unframed only. */
    public const UNFRAMED_PRODUCT = 'Print sold unframed';

    public const FRAMING = 'Framing';

    public const FRAMING_SLUG = 'framing';

    public const NO_FRAME = 'No frame';

    public const HANGER = 'Hanger';

    public const HANGER_SLUG = 'hanger';

    public const FRAME = 'Frame';

    public const FRAME_SLUG = 'frame';

    public const FRAME_COLOUR = 'Frame colour';

    public const OAK = 'Oak';

    public const BLACK = 'Black';

    /** An attribute with a value, which no product has. */
    public const UNUSED_ATTRIBUTE = 'Unused finish';

    private const UNUSED_VALUE = 'Glossy';

    public const UNFRAMED_PRICE = '100';

    public const FRAMED_PRICE = '300';

    /** A price between the two, which only the unframed variations are under. */
    public const PRICE_BETWEEN = '200';

    /** Each attribute's values, in the order the store gave them. */
    private const ATTRIBUTES = [
        self::FRAMING => [self::HANGER, self::NO_FRAME, self::FRAME],
        self::FRAME_COLOUR => [self::OAK, self::BLACK],
        self::UNUSED_ATTRIBUTE => [self::UNUSED_VALUE],
    ];

    /**
     * Each product's variations: the framing, the frame's colour (none
     * without a frame or a hanger) and the price.
     */
    private const PRODUCTS = [
        self::FRAMED_PRODUCT => [
            [self::NO_FRAME, '', self::UNFRAMED_PRICE],
            [self::FRAME, self::OAK, self::FRAMED_PRICE],
            [self::FRAME, self::BLACK, self::FRAMED_PRICE],
            [self::HANGER, self::BLACK, self::FRAMED_PRICE],
        ],
        self::UNFRAMED_PRODUCT => [
            [self::NO_FRAME, '', self::UNFRAMED_PRICE],
        ],
    ];

    /** The term meta WooCommerce orders an attribute's values by. */
    private const TERM_ORDER_META = 'order';

    public static function prepare(): void
    {
        $taxonomies = [];

        foreach (self::ATTRIBUTES as $label => $values) {
            $taxonomies[$label] = self::attribute($label, $values);
        }

        $category = self::category();

        foreach (self::PRODUCTS as $name => $variations) {
            self::product($name, $category, $variations, $taxonomies);
        }

        self::forget_filter_counts();
    }

    public static function forget(): void
    {
        foreach (array_keys(self::PRODUCTS) as $name) {
            $existing = get_page_by_path(sanitize_title($name), OBJECT, 'product');

            if (null === $existing) {
                continue;
            }

            $product = wc_get_product($existing->ID);

            if ($product instanceof WC_Product_Variable) {
                $product->delete(true);
            }
        }

        foreach (array_keys(self::ATTRIBUTES) as $label) {
            $id = wc_attribute_taxonomy_id_by_name(wc_sanitize_taxonomy_name($label));

            if ($id > 0) {
                wc_delete_attribute($id);
            }
        }

        $term = get_term_by('slug', self::CATEGORY_SLUG, self::CATEGORY_TAXONOMY);

        if ($term instanceof WP_Term) {
            wp_delete_term($term->term_id, self::CATEGORY_TAXONOMY);
        }

        self::forget_filter_counts();
    }

    /**
     * Drops the filters' cached counts, as saving a product does on a real
     * store. WooCommerce keeps them by the query, not by the products, and
     * hooks their invalidation to a product's save only when a request
     * starts with a cache already there. The integration suite is one
     * request that starts on an empty one, so without this a test reads the
     * counts of an earlier test's terms, which `forget()` has deleted, and
     * the attribute shows no values.
     */
    private static function forget_filter_counts(): void
    {
        wc_get_container()->get(CacheController::class)->invalidate_filter_data_cache();
    }

    /** The ID of the category's term. */
    public static function category(): int
    {
        $term = get_term_by('slug', self::CATEGORY_SLUG, self::CATEGORY_TAXONOMY);

        if ($term instanceof WP_Term) {
            return $term->term_id;
        }

        $created = wp_insert_term(self::CATEGORY_NAME, self::CATEGORY_TAXONOMY, ['slug' => self::CATEGORY_SLUG]);

        if ($created instanceof WP_Error) {
            throw new RuntimeException($created->get_error_message());
        }

        return (int) $created['term_id'];
    }

    /**
     * One of the store's product attributes with its values, in their own
     * order. Returns its taxonomy.
     *
     * @param list<string> $values
     */
    private static function attribute(string $label, array $values): string
    {
        $slug     = wc_sanitize_taxonomy_name($label);
        $taxonomy = wc_attribute_taxonomy_name($slug);

        if (0 === wc_attribute_taxonomy_id_by_name($slug)) {
            $created = wc_create_attribute(['name' => $label, 'slug' => $slug, 'type' => 'select', 'order_by' => 'menu_order']);

            if ($created instanceof WP_Error) {
                throw new RuntimeException($created->get_error_message());
            }
        }

        // WooCommerce registers an attribute's taxonomy on the next request's `init`.
        if (! taxonomy_exists($taxonomy)) {
            register_taxonomy($taxonomy, ['product']);
        }

        foreach ($values as $position => $value) {
            $term = get_term_by('name', $value, $taxonomy);

            if (! $term instanceof WP_Term) {
                $created = wp_insert_term($value, $taxonomy);

                if ($created instanceof WP_Error) {
                    throw new RuntimeException($created->get_error_message());
                }

                $term = get_term((int) $created['term_id'], $taxonomy);
            }

            if ($term instanceof WP_Term) {
                update_term_meta($term->term_id, self::TERM_ORDER_META, $position);
            }
        }

        return $taxonomy;
    }

    /**
     * @param list<array{string, string, string}> $variations
     * @param array<string, string>               $taxonomies Each attribute's taxonomy, by its label.
     */
    private static function product(string $name, int $category, array $variations, array $taxonomies): void
    {
        if (null !== get_page_by_path(sanitize_title($name), OBJECT, 'product')) {
            return;
        }

        $sold = [
            self::FRAMING => array_values(array_unique(array_column($variations, 0))),
            self::FRAME_COLOUR => array_values(array_unique(array_filter(array_column($variations, 1)))),
        ];

        $attributes = [];

        foreach ($sold as $label => $values) {
            if ([] === $values) {
                continue;
            }

            $attribute = new WC_Product_Attribute();
            $attribute->set_id(wc_attribute_taxonomy_id_by_name(wc_sanitize_taxonomy_name($label)));
            $attribute->set_name($taxonomies[$label]);
            $attribute->set_options(array_map(static fn (string $value): int => self::term($value, $taxonomies[$label])->term_id, $values));
            $attribute->set_visible(true);
            $attribute->set_variation(true);
            $attributes[] = $attribute;
        }

        $product = new WC_Product_Variable();
        $product->set_name($name);
        $product->set_slug(sanitize_title($name));
        $product->set_category_ids([$category]);
        $product->set_attributes($attributes);
        $product->set_status('publish');
        $product->save();

        foreach ($variations as [$framing, $colour, $price]) {
            $variation = new WC_Product_Variation();
            $variation->set_parent_id($product->get_id());
            $variation->set_attributes(array_filter([
                $taxonomies[self::FRAMING] => self::term($framing, $taxonomies[self::FRAMING])->slug,
                $taxonomies[self::FRAME_COLOUR] => '' === $colour ? '' : self::term($colour, $taxonomies[self::FRAME_COLOUR])->slug,
            ]));
            $variation->set_regular_price($price);
            $variation->set_status('publish');
            $variation->save();
        }

        WC_Product_Variable::sync($product->get_id());
    }

    private static function term(string $name, string $taxonomy): WP_Term
    {
        $term = get_term_by('name', $name, $taxonomy);

        if (! $term instanceof WP_Term) {
            throw new RuntimeException("The fixture has no {$name} in {$taxonomy}.");
        }

        return $term;
    }
}
