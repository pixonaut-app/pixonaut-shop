<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Archive;

/**
 * WooCommerce's attribute filter for one of the store's product attributes,
 * as the theme adds it to the filters beside an archive's grid
 * (`ProductFilters`): a group with the attribute's name as its heading, or,
 * nested under a value of another attribute (`NestedAttributeFilters`), the
 * list of values alone.
 */
/**
 * @phpstan-type ParsedBlock array{blockName: string|null, attrs: array<array-key, mixed>, innerBlocks: array<array-key, mixed>, innerHTML: string, innerContent: array<array-key, mixed>}
 */
final class AttributeFilterBlock
{
    public const NAME = 'woocommerce/product-filter-attribute';

    /**
     * Marks a filter the theme added, the only ones it nests other attributes
     * in: a filter a store placed itself stays as the store placed it.
     */
    public const ADDED = 'pixonautShopAdded';

    /**
     * An attribute's group as WooCommerce's editor saves it: the attribute's
     * name as a heading over a list of checkboxes, with how many products
     * each value has.
     */
    private const GROUP_MARKUP = <<<'HTML'
        <!-- wp:woocommerce/product-filter-attribute {"attributeId":%1$d,"showCounts":true,"sortOrder":"%2$s","pixonautShopAdded":true} -->
        <div class="wp-block-woocommerce-product-filter-attribute"><!-- wp:heading {"level":3} -->
        <h3 class="wp-block-heading">%3$s</h3>
        <!-- /wp:heading -->

        <!-- wp:woocommerce/product-filter-checkbox-list -->
        <div class="wp-block-woocommerce-product-filter-checkbox-list wc-block-product-filter-checkbox-list"></div>
        <!-- /wp:woocommerce/product-filter-checkbox-list --></div>
        <!-- /wp:woocommerce/product-filter-attribute -->
        HTML;

    /** The same without its heading: the value it is nested under names it. */
    private const LIST_MARKUP = <<<'HTML'
        <!-- wp:woocommerce/product-filter-attribute {"attributeId":%1$d,"showCounts":true,"sortOrder":"%2$s","pixonautShopAdded":true} -->
        <div class="wp-block-woocommerce-product-filter-attribute"><!-- wp:woocommerce/product-filter-checkbox-list -->
        <div class="wp-block-woocommerce-product-filter-checkbox-list wc-block-product-filter-checkbox-list"></div>
        <!-- /wp:woocommerce/product-filter-checkbox-list --></div>
        <!-- /wp:woocommerce/product-filter-attribute -->
        HTML;

    /**
     * How WooCommerce's attribute filter sorts values, by the order the store
     * gave the attribute: its own order, by name, or by name as a number.
     */
    private const SORT_ORDERS = [
        'menu_order' => 'menu_order-asc',
        'name' => 'name-asc',
        'name_num' => 'name_num-asc',
        'id' => 'id-asc',
    ];

    private const DEFAULT_SORT_ORDER = 'name-asc';

    /**
     * The attribute's group, with its name as the heading.
     *
     * @return ParsedBlock|null
     */
    public static function group(object $attribute): ?array
    {
        return self::parsed(self::GROUP_MARKUP, $attribute);
    }

    /**
     * The attribute's values without a heading.
     *
     * @return ParsedBlock|null
     */
    public static function values(object $attribute): ?array
    {
        return self::parsed(self::LIST_MARKUP, $attribute);
    }

    /**
     * The store's product attributes, as `wc_get_attribute_taxonomies()`
     * lists them, in its order.
     *
     * @return list<object>
     */
    public static function store_attributes(): array
    {
        return array_values(array_filter(
            wc_get_attribute_taxonomies(),
            static fn (mixed $attribute): bool => \is_object($attribute) && isset($attribute->attribute_id, $attribute->attribute_label)
        ));
    }

    /**
     * The taxonomies of the store's product attributes, in its order.
     *
     * @return list<string>
     */
    public static function store_taxonomies(): array
    {
        return array_map(self::taxonomy_of(...), self::store_attributes());
    }

    /** The store's product attribute with this taxonomy, as `wc_get_attribute_taxonomies()` lists it. */
    public static function attribute_of(string $taxonomy): ?object
    {
        foreach (self::store_attributes() as $attribute) {
            if (self::taxonomy_of($attribute) === $taxonomy) {
                return $attribute;
            }
        }

        return null;
    }

    /** The taxonomy of a product attribute as `wc_get_attribute_taxonomies()` lists it. */
    public static function taxonomy_of(object $attribute): string
    {
        $name = $attribute->attribute_name ?? null;

        return \is_string($name) && '' !== $name ? wc_attribute_taxonomy_name($name) : '';
    }

    /** The attribute's name, as the store gave it. */
    public static function label_of(object $attribute): string
    {
        $label = $attribute->attribute_label ?? null;

        return \is_string($label) ? $label : '';
    }

    /**
     * @return ParsedBlock|null
     */
    private static function parsed(string $markup, object $attribute): ?array
    {
        if (! isset($attribute->attribute_id) || ! is_numeric($attribute->attribute_id)) {
            return null;
        }

        $order = $attribute->attribute_orderby ?? null;

        foreach (parse_blocks(\sprintf(
            $markup,
            (int) $attribute->attribute_id,
            \is_string($order) && isset(self::SORT_ORDERS[$order]) ? self::SORT_ORDERS[$order] : self::DEFAULT_SORT_ORDER,
            esc_html(self::label_of($attribute))
        )) as $block) {
            if (self::NAME === $block['blockName']) {
                return $block;
            }
        }

        return null;
    }
}
