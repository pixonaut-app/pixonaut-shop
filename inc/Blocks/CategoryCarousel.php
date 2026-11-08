<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Blocks;

/**
 * The category carousel block: an optional heading, then one scrolling row
 * of soft-shaped category pictures with their names, the children of a chosen
 * category or, without one, the top-level categories, all with products.
 *
 * The row scrolls by itself, with scroll snapping. The previous and next
 * buttons are printed hidden; the block's view script shows them while the
 * row overflows, so without the script there is no button that does
 * nothing.
 *
 * `src/blocks/category-carousel/render.php` renders it from here; its
 * attributes are declared in that block's block.json.
 */
final class CategoryCarousel
{
    private const BLOCK_CLASS = 'wp-block-pixonaut-shop-category-carousel';

    /** The prefix of each row's ID, which its buttons name in `aria-controls`. */
    private const LIST_ID_PREFIX = 'pixonaut-shop-category-carousel-';

    /** Added to the row's ID for its heading's, which names the row. */
    private const HEADING_ID_SUFFIX = '-heading';

    /**
     * How wide the stylesheet draws a picture: the round's
     * `--pixonaut-shop-carousel-picture`, smaller below WordPress's 782px
     * breakpoint.
     */
    private const PICTURE_SIZES = '(max-width: 781px) 88px, 120px';

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
     * The block's markup, or an empty string when the chosen category is
     * not the store's or no category to show has products.
     *
     * @param array<string, mixed> $attributes
     */
    public function render(array $attributes): string
    {
        $attributes = $this->attributes($attributes);
        $parent     = CategoryList::TOP_LEVEL;

        if ('' !== $attributes['parent']) {
            $term = CategoryList::by_slug($attributes['parent']);

            if (null === $term) {
                return '';
            }

            $parent = $term->term_id;
        }

        $categories = CategoryList::children($parent);

        if ([] === $categories) {
            return '';
        }

        $id         = wp_unique_id(self::LIST_ID_PREFIX);
        $heading_id = $id . self::HEADING_ID_SUFFIX;
        // The row is named by its heading, or plainly as categories without one.
        $name = '' === $attributes['heading']
            ? ' aria-label="' . esc_attr__('Categories', 'pixonaut-shop') . '"'
            : ' aria-labelledby="' . esc_attr($heading_id) . '"';

        return \sprintf(
            '<div %1$s>%2$s<div class="%3$s__frame">'
            . '<ul class="%3$s__list" id="%4$s"%8$s>%5$s</ul>%6$s%7$s'
            . '</div></div>',
            get_block_wrapper_attributes(),
            CategoryLinks::heading($attributes['heading'], self::BLOCK_CLASS, $heading_id),
            self::BLOCK_CLASS,
            esc_attr($id),
            CategoryLinks::items($categories, self::BLOCK_CLASS, self::PICTURE_SIZES),
            $this->arrow('is-previous', $id, __('Previous categories', 'pixonaut-shop')),
            $this->arrow('is-next', $id, __('Next categories', 'pixonaut-shop')),
            $name
        );
    }

    private function arrow(string $direction, string $list_id, string $name): string
    {
        return \sprintf(
            '<button type="button" class="%1$s__arrow %2$s" aria-controls="%3$s" aria-label="%4$s" hidden>%5$s</button>',
            self::BLOCK_CLASS,
            $direction,
            esc_attr($list_id),
            esc_attr($name),
            Icons::svg('chevron')
        );
    }
}
