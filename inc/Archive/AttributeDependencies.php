<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Archive;

/**
 * Which product attribute applies only under some values of another, as
 * Pixonaut for WooCommerce reads it from the store's variations: a frame's
 * colour, say, only where the framing is a frame or a hanger. The theme never
 * guesses this from an attribute's or a value's name. Without the plugin, or
 * in a store where every attribute always applies, there is none.
 */
final class AttributeDependencies
{
    /**
     * The plugin's filter: each dependent attribute's taxonomy, with the
     * taxonomy of each attribute it depends on and the slugs of the values it
     * applies under, e.g. `['pa_frame-colour' => ['pa_framing' => ['frame']]]`.
     */
    public const FILTER = 'pixonaut_wc_storefront_attribute_dependencies';

    /**
     * How many levels down an attribute nests at most: a frame's colour
     * under the framing, and a finish under the colour. Each level renders
     * a dependent's values again under every value of its parent, so the
     * markup grows with each one.
     */
    public const MAX_DEPTH = 2;

    /** @var array<string, array<string, list<string>>> */
    private array $dependencies;

    public function __construct()
    {
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Pixonaut for WooCommerce's filter, which it answers.
        $this->dependencies = self::read(apply_filters(self::FILTER, []));
    }

    /** Whether the attribute shows only under values of another. */
    public function is_dependent(string $taxonomy): bool
    {
        return isset($this->dependencies[$taxonomy]);
    }

    /**
     * The attributes that apply under each of an attribute's values, by the
     * value's slug, in the order the plugin gave them. PHP keeps a slug that
     * is a whole number as an integer key.
     *
     * @return array<array-key, list<string>>
     */
    public function dependents_under(string $parent_taxonomy): array
    {
        $under = [];

        foreach ($this->dependencies as $dependent => $parents) {
            if ($dependent === $parent_taxonomy) {
                continue;
            }

            foreach ($parents[$parent_taxonomy] ?? [] as $value) {
                $under[$value][] = $dependent;
            }
        }

        return $under;
    }

    /**
     * The dependent attributes that show nested, each under the values of
     * its parent wherever that shows, at most `MAX_DEPTH` levels down.
     *
     * Every attribute that depends on nothing shows as a group of its own,
     * and so does one no such group leads to (its parent missing from the
     * store, a circle, or too deep), in the store's order: the first of a
     * circle keeps a group, the rest nest under it. So each attribute shows
     * as a group or nested, never neither, and never both.
     *
     * @param list<string> $taxonomies The taxonomies of the store's product attributes, in its order.
     *
     * @return list<string>
     */
    public function nested(array $taxonomies): array
    {
        $groups  = array_values(array_filter($taxonomies, fn (string $taxonomy): bool => ! $this->is_dependent($taxonomy)));
        $reached = [];

        foreach ($groups as $group) {
            $this->reach($group, $taxonomies, $groups, $reached);
        }

        foreach ($taxonomies as $taxonomy) {
            if (! \in_array($taxonomy, $groups, true) && ! \in_array($taxonomy, $reached, true)) {
                $groups[] = $taxonomy;
                $this->reach($taxonomy, $taxonomies, $groups, $reached);
            }
        }

        return $reached;
    }

    /**
     * Adds what a group leads to, breadth first so each attribute is
     * placed as high as it can go.
     *
     * @param list<string> $taxonomies
     * @param list<string> $groups
     * @param list<string> $reached
     */
    private function reach(string $group, array $taxonomies, array $groups, array &$reached): void
    {
        $next = [[$group, 0]];

        while ([] !== $next) {
            [$parent_taxonomy, $depth] = array_shift($next);

            if ($depth >= self::MAX_DEPTH) {
                continue;
            }

            foreach ($this->dependents_under($parent_taxonomy) as $dependents) {
                foreach ($dependents as $dependent) {
                    if (\in_array($dependent, $taxonomies, true) && ! \in_array($dependent, $groups, true) && ! \in_array($dependent, $reached, true)) {
                        $reached[] = $dependent;
                        $next[]    = [$dependent, $depth + 1];
                    }
                }
            }
        }
    }

    /**
     * The filter's answer, keeping only what has the documented shape.
     *
     * @return array<string, array<string, list<string>>>
     */
    private static function read(mixed $answer): array
    {
        if (! \is_array($answer)) {
            return [];
        }

        $dependencies = [];

        foreach ($answer as $dependent => $parents) {
            if (! \is_string($dependent) || '' === $dependent || ! \is_array($parents)) {
                continue;
            }

            foreach ($parents as $parent_taxonomy => $values) {
                if (! \is_string($parent_taxonomy) || '' === $parent_taxonomy || ! \is_array($values)) {
                    continue;
                }

                $slugs = array_values(array_filter($values, static fn (mixed $value): bool => \is_string($value) && '' !== $value));

                if ([] !== $slugs) {
                    $dependencies[$dependent][$parent_taxonomy] = $slugs;
                }
            }
        }

        return $dependencies;
    }
}
