<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Archive;

use WP_Block;

/**
 * Nests an attribute that applies only under some values of another
 * (`AttributeDependencies`) under the chosen one of those values in the
 * filters beside an archive's grid: a frame's colour under "Frame" once
 * "Frame" is ticked, rather than as a group of its own beside "No frame". It
 * shows once, under its name, however many of the values it applies under
 * are chosen, and not at all while none is (`placed_after()`). Its values, their counts and their chips are
 * WooCommerce's own filter for the attribute, so ticking one narrows the grid
 * like any other value.
 *
 * WooCommerce draws a list's values again from its script once the page has
 * started, which would drop anything added inside them. A list with values
 * nested in it is therefore left as the server rendered it: every tick loads
 * the page again anyway (`patterns/no-products.php`). One long enough to fold
 * some values behind "Show more" needs that script, so there the dependent
 * attributes keep groups of their own, after it.
 */
final class NestedAttributeFilters
{
    /** The group a nested attribute's name and values sit in. */
    public const NESTED_CLASS = 'pixonaut-shop-filter-nested';

    private const LIST_CLASS = 'wc-block-product-filter-checkbox-list';

    private const ITEM_CLASS = 'wc-block-product-filter-checkbox-list__item';

    private const INPUT_CLASS = 'wc-block-product-filter-checkbox-list__input';

    /** WooCommerce's button that unfolds a list's values past its first ones. */
    private const SHOW_MORE_CLASS = 'wc-block-product-filter-checkbox-list__show-more';

    /** WooCommerce's class on a filter without any value to show. */
    private const HIDDEN_CLASS = 'wc-block-product-filter--hidden';

    /** The directives that draw a list's values again from its script. */
    private const EACH_CHILD = 'data-wp-each-child';

    private const EACH = 'data-wp-each--item';

    private const EACH_KEY = 'data-wp-each-key';

    private const CONTEXT = 'data-wp-context';

    /**
     * What the Interactivity API tells an element apart by when the router
     * swaps the drawer in place. An element it keeps keeps its context, so a
     * value ticked in place would be drawn from the context it had before,
     * unticked: a key of its own for each value, chosen or not, has a value
     * whose choice changed drawn anew from the new page.
     */
    private const KEY = 'data-wp-key';

    private const KEY_PREFIX = 'pixonaut-shop-value-';

    /**
     * On a value, the dependent attributes it opens; on a nested group, the
     * attribute it is. Unticking a value clears the values chosen of what it
     * alone opened (`src/archive/dependent-values.ts`).
     */
    public const OPENS = 'data-pixonaut-shop-opens';

    public const NESTED_ATTRIBUTE = 'data-pixonaut-shop-attribute';

    private const CHOSEN_KEY_SUFFIX = '-chosen';

    /** The flags WooCommerce encodes an Interactivity API context with. */
    private const CONTEXT_FLAGS = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;

    /**
     * The attributes being nested right now, outermost first, so one the
     * plugin made depend on itself in a circle stops at its first turn.
     *
     * @var list<string>
     */
    private array $rendering = [];

    public function register(): void
    {
        add_filter('render_block_' . AttributeFilterBlock::NAME, [$this, 'nest'], 10, 3);
    }

    /**
     * @param array<array-key, mixed> $parsed_block
     */
    public function nest(string $block_content, array $parsed_block, ?WP_Block $instance = null): string
    {
        $attributes = \is_array($parsed_block['attrs'] ?? null) ? $parsed_block['attrs'] : [];

        if (true !== ($attributes[AttributeFilterBlock::ADDED] ?? null) || ! $instance instanceof WP_Block || $this->shows_nothing($block_content)) {
            return $block_content;
        }

        $id       = $attributes['attributeId'] ?? null;
        $taxonomy = is_numeric($id) ? wc_attribute_taxonomy_name_by_id((int) $id) : '';

        if ('' === $taxonomy || \in_array($taxonomy, $this->rendering, true) || \count($this->rendering) >= AttributeDependencies::MAX_DEPTH) {
            return $block_content;
        }

        $under = $this->placed_under($taxonomy);

        if ([] === $under) {
            return $block_content;
        }

        $this->rendering[] = $taxonomy;

        try {
            return $this->folds_values($block_content)
                ? $block_content . $this->groups(array_values(array_unique(array_merge(...array_values($under)))), $instance, $taxonomy)
                : $this->with_nested($block_content, $taxonomy, $under, $instance);
        } finally {
            array_pop($this->rendering);
        }
    }

    /**
     * The attributes that nest under each of the attribute's values: those
     * `AttributeDependencies::nested()` places nested, the same ones the
     * panel leaves without a group of their own.
     *
     * @return array<array-key, list<string>>
     */
    private function placed_under(string $taxonomy): array
    {
        $dependencies = new AttributeDependencies();
        $under        = $dependencies->dependents_under($taxonomy);

        if ([] === $under) {
            return [];
        }

        $nested = $dependencies->nested(AttributeFilterBlock::store_taxonomies());
        $placed = [];

        foreach ($under as $value => $dependents) {
            $kept = array_values(array_intersect($dependents, $nested));

            if ([] !== $kept) {
                $placed[$value] = $kept;
            }
        }

        return $placed;
    }

    /**
     * The filter's own markup with each dependent attribute after the label
     * of the value it shows under, and its values left as the server
     * rendered them.
     *
     * @param array<array-key, list<string>> $under
     */
    private function with_nested(string $block_content, string $taxonomy, array $under, WP_Block $instance): string
    {
        $after   = $this->placed_after($this->listed_values($block_content), $under, $instance, $taxonomy);
        $markup  = new FilterListMarkup($block_content);
        $pending = null;

        while ($markup->next_tag(['tag_closers' => 'visit'])) {
            $tag = $markup->get_tag();

            if ($markup->is_tag_closer()) {
                if ('LABEL' === $tag && null !== $pending) {
                    $markup->add_after_current_tag($pending);
                    $pending = null;
                }

                continue;
            }

            if ('DIV' === $tag && $markup->has_class(self::ITEM_CLASS)) {
                $markup->remove_attribute(self::EACH_CHILD);
                $value = $this->item_value($markup->get_attribute(self::CONTEXT));
                $markup->set_attribute(self::KEY, $this->item_key($markup->get_attribute(self::CONTEXT)));

                if ([] !== ($under[$value] ?? [])) {
                    $markup->set_attribute(self::OPENS, implode(' ', $under[$value]));
                }
            } elseif ('TEMPLATE' === $tag && null !== $markup->get_attribute(self::EACH)) {
                $markup->remove_attribute(self::EACH);
                $markup->remove_attribute(self::EACH_KEY);
            } elseif ('INPUT' === $tag && $markup->has_class(self::INPUT_CLASS)) {
                $value   = $markup->get_attribute('value');
                $pending = \is_string($value) ? ($after[$value] ?? null) : null;
            }
        }

        return $markup->with_additions();
    }

    /**
     * A value's key, from the context WooCommerce gave its item: the value,
     * and whether it is chosen.
     */
    private function item_key(mixed $context): string
    {
        return self::KEY_PREFIX . $this->item_value($context) . (true === ($this->item($context)['selected'] ?? null) ? self::CHOSEN_KEY_SUFFIX : '');
    }

    private function item_value(mixed $context): string
    {
        $value = $this->item($context)['value'] ?? null;

        return \is_scalar($value) ? (string) $value : '';
    }

    /**
     * @return array<array-key, mixed>
     */
    private function item(mixed $context): array
    {
        $data = \is_string($context) ? json_decode($context, true) : null;

        return \is_array($data) && \is_array($data['item'] ?? null) ? $data['item'] : [];
    }

    /**
     * The list's values in its order, each with whether it is chosen.
     *
     * @return list<array{string, bool}>
     */
    private function listed_values(string $block_content): array
    {
        $markup = new FilterListMarkup($block_content);
        $values = [];

        while ($markup->next_tag(['tag_name' => 'INPUT', 'class_name' => self::INPUT_CLASS])) {
            $value = $markup->get_attribute('value');

            if (\is_string($value) && '' !== $value) {
                $values[] = [$value, null !== $markup->get_attribute('checked')];
            }
        }

        return $values;
    }

    /**
     * Each dependent attribute's values, by the value they show after. A
     * dependent shows once, however many of the values it applies under: after
     * the chosen one, or after the last chosen when several are. With none of
     * them chosen it does not show, unless one of its own values is chosen
     * (from the address), which then shows after the last value it applies
     * under so it can be cleared.
     *
     * @param list<array{string, bool}>      $values
     * @param array<array-key, list<string>> $under
     *
     * @return array<string, string>
     */
    private function placed_after(array $values, array $under, WP_Block $instance, string $taxonomy): array
    {
        $after = [];

        foreach (array_values(array_unique(array_merge(...array_values($under)))) as $dependent) {
            $parents = [];
            $chosen  = [];

            foreach ($values as [$value, $is_chosen]) {
                if (\in_array($dependent, $under[$value] ?? [], true)) {
                    $parents[] = $value;

                    if ($is_chosen) {
                        $chosen[] = $value;
                    }
                }
            }

            if ([] === $parents) {
                continue;
            }

            $nested = $this->nested($dependent, $instance, $taxonomy);

            if (null === $nested || ([] === $chosen && ! $this->has_a_chosen_value($nested))) {
                continue;
            }

            $anchors        = [] === $chosen ? $parents : $chosen;
            $anchor         = $anchors[array_key_last($anchors)];
            $after[$anchor] = ($after[$anchor] ?? '') . $nested;
        }

        return $after;
    }

    /**
     * The dependent attribute's values under its name, or null when it has
     * none to show.
     */
    private function nested(string $dependent, WP_Block $instance, string $taxonomy): ?string
    {
        $attribute = AttributeFilterBlock::attribute_of($dependent);
        $block     = null === $attribute ? null : AttributeFilterBlock::values($attribute);

        if (null === $attribute || null === $block) {
            return null;
        }

        $values = (new WP_Block($block, $instance->context))->render();

        if ($this->shows_nothing($values)) {
            return null;
        }

        // WooCommerce's list names itself to a screen reader already.
        return \sprintf(
            '<div class="%1$s" %4$s="%5$s"><p class="%1$s__label" aria-hidden="true">%2$s</p>%3$s</div>',
            esc_attr(self::NESTED_CLASS),
            esc_html(AttributeFilterBlock::label_of($attribute)),
            $this->in_place($values, sanitize_title($taxonomy)),
            self::NESTED_ATTRIBUTE,
            esc_attr($dependent)
        );
    }

    /**
     * The dependent attributes' own groups, for a parent whose values
     * WooCommerce folds, with ids of their own: a dependent of two such
     * parents follows each.
     *
     * @param list<string> $dependents
     */
    private function groups(array $dependents, WP_Block $instance, string $parent_taxonomy): string
    {
        $groups = '';

        foreach ($dependents as $dependent) {
            $attribute = AttributeFilterBlock::attribute_of($dependent);
            $block     = null === $attribute ? null : AttributeFilterBlock::group($attribute);

            if (null !== $block) {
                $groups .= $this->in_place((new WP_Block($block, $instance->context))->render(), sanitize_title($parent_taxonomy));
            }
        }

        return $groups;
    }

    /**
     * The values with ids of their own: an attribute that applies under two
     * attributes shows under each, and a label must tick the checkbox beside
     * it, not its twin elsewhere.
     */
    private function in_place(string $values, string $place): string
    {
        $markup = new FilterListMarkup($values);

        while ($markup->next_tag()) {
            foreach (['id', 'for'] as $name) {
                $id = $markup->get_attribute($name);

                if (\is_string($id) && '' !== $id) {
                    $markup->set_attribute($name, $place . '-' . $id);
                }
            }

            $context = $markup->get_attribute(self::CONTEXT);

            if (\is_string($context)) {
                $markup->set_attribute(self::CONTEXT, $this->context_in_place($context, $place));
            }
        }

        return $markup->get_updated_html();
    }

    /**
     * A context's values with the ids `in_place()` gave their checkboxes, so
     * the ones WooCommerce's script draws keep them.
     */
    private function context_in_place(string $context, string $place): string
    {
        $data = json_decode($context, true);

        if (! \is_array($data)) {
            return $context;
        }

        if (\is_array($data['item'] ?? null)) {
            $data['item'] = $this->item_in_place($data['item'], $place);
        }

        if (\is_array($data['items'] ?? null)) {
            $data['items'] = array_map(fn (mixed $item): mixed => \is_array($item) ? $this->item_in_place($item, $place) : $item, $data['items']);
        }

        $encoded = wp_json_encode($data, self::CONTEXT_FLAGS);

        return false === $encoded ? $context : $encoded;
    }

    /**
     * @param array<array-key, mixed> $item
     *
     * @return array<array-key, mixed>
     */
    private function item_in_place(array $item, string $place): array
    {
        if (\is_string($item['id'] ?? null)) {
            $item['id'] = $place . '-' . $item['id'];
        }

        return $item;
    }

    private function has_a_chosen_value(string $values): bool
    {
        $markup = new FilterListMarkup($values);

        while ($markup->next_tag(['tag_name' => 'INPUT', 'class_name' => self::INPUT_CLASS])) {
            if (null !== $markup->get_attribute('checked')) {
                return true;
            }
        }

        return false;
    }

    /** Whether WooCommerce hid the filter, for want of a value any listed product has. */
    private function shows_nothing(string $block_content): bool
    {
        $markup = new FilterListMarkup($block_content);

        return ! $markup->next_tag() || $markup->has_class(self::HIDDEN_CLASS);
    }

    /** Whether WooCommerce folds some of the list's values behind "Show more". */
    private function folds_values(string $block_content): bool
    {
        $markup = new FilterListMarkup($block_content);

        while ($markup->next_tag(['class_name' => self::LIST_CLASS])) {
            if ($markup->next_tag(['class_name' => self::SHOW_MORE_CLASS])) {
                return true;
            }
        }

        return false;
    }
}
