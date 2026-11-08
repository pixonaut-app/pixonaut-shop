<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Blocks;

/**
 * The group tiles block: a heading, then the children of one product
 * category with products, as picture tiles linking to each. A broad store
 * files the same products under several groups (an occasion, who a gift is
 * for), and places one block per group on its front page.
 *
 * `src/blocks/group-tiles/render.php` renders it from here; its attributes
 * are declared in that block's block.json.
 */
final class GroupTiles
{
    private const BLOCK_CLASS = 'wp-block-pixonaut-shop-group-tiles';

    /**
     * How wide the stylesheet draws a tile's picture: half the screen on
     * phones (two to a row below WordPress's 782px breakpoint), else a
     * column of at least 11rem, about 200px at the 1240px wide size.
     */
    private const PICTURE_SIZES = '(max-width: 781px) 50vw, 200px';

    /**
     * @param array<string, mixed> $attributes
     *
     * @return array{parent: string, heading: string}
     */
    public function attributes(array $attributes): array
    {
        return CategoryLinks::attributes($attributes);
    }

    /**
     * The block's markup, or an empty string when no category is chosen, the
     * store has none by that slug, or none of its children has products.
     * The heading is the category's name unless another is chosen.
     *
     * @param array<string, mixed> $attributes
     */
    public function render(array $attributes): string
    {
        $attributes = $this->attributes($attributes);
        $parent     = CategoryList::by_slug($attributes['parent']);

        if (null === $parent) {
            return '';
        }

        $children = CategoryList::children($parent->term_id);

        if ([] === $children) {
            return '';
        }

        return \sprintf(
            '<div %1$s>%2$s<ul class="%3$s__items">%4$s</ul></div>',
            get_block_wrapper_attributes(),
            CategoryLinks::heading('' === $attributes['heading'] ? $parent->name : $attributes['heading'], self::BLOCK_CLASS),
            self::BLOCK_CLASS,
            CategoryLinks::items($children, self::BLOCK_CLASS, self::PICTURE_SIZES)
        );
    }
}
