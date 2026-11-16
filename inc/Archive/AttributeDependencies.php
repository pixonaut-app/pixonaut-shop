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
     * The dependent attributes that show nested: those under a value of an
     * attribute that shows as a group of its own, or under one nested there
     * in turn. Any other keeps its own group, so it never disappears.
     *
     * @param list<string> $taxonomies The taxonomies of the store's product attributes.
     *
     * @return list<string>
     */
    public function nested(array $taxonomies): array
    {
        $reached = [];
        $next    = array_values(array_filter($taxonomies, fn (string $taxonomy): bool => ! $this->is_dependent($taxonomy)));

        while ([] !== $next) {
            $parent_taxonomy = array_shift($next);

            foreach ($this->dependents_under($parent_taxonomy) as $dependents) {
                foreach ($dependents as $dependent) {
                    if (\in_array($dependent, $taxonomies, true) && ! \in_array($dependent, $reached, true)) {
                        $reached[] = $dependent;
                        $next[]    = $dependent;
                    }
                }
            }
        }

        return $reached;
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
