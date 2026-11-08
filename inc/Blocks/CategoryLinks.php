<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Blocks;

use Pixonaut\Shop\Archive\CategoryImage;
use WP_Term;

/**
 * What the group tiles and the category carousel share: their two
 * attributes, the heading above the categories, and each category as a link
 * holding its picture and its name.
 */
final class CategoryLinks
{
    /**
     * The attributes with anything that is not text left unchosen: `parent`,
     * the slug of the category whose children show, and `heading`.
     *
     * @param array<string, mixed> $attributes
     *
     * @return array{parent: string, heading: string}
     */
    public static function attributes(array $attributes): array
    {
        $parent  = $attributes['parent'] ?? '';
        $heading = $attributes['heading'] ?? '';

        return [
            'parent'  => \is_string($parent) ? trim($parent) : '',
            'heading' => \is_string($heading) ? trim($heading) : '',
        ];
    }

    /**
     * A section heading, or an empty string for no text.
     *
     * @param string $id An ID for what the heading names to point at; none when empty.
     */
    public static function heading(string $text, string $block_class, string $id = ''): string
    {
        if ('' === $text) {
            return '';
        }

        return \sprintf(
            '<h2 class="wp-block-heading %s__heading"%s>%s</h2>',
            $block_class,
            '' === $id ? '' : ' id="' . esc_attr($id) . '"',
            esc_html($text)
        );
    }

    /**
     * Each category as a list item: a link to it with its picture (empty on
     * the ground when it has none) and its name.
     *
     * @param list<WP_Term> $categories
     * @param string $sizes The pictures' `sizes`: how wide the block's stylesheet draws them.
     */
    public static function items(array $categories, string $block_class, string $sizes): string
    {
        $items = '';

        foreach ($categories as $category) {
            $link = get_term_link($category);

            if (! \is_string($link)) {
                continue;
            }

            $items .= \sprintf(
                '<li class="%1$s__item"><a class="%1$s__link" href="%2$s">'
                . '<span class="%1$s__picture">%3$s</span>'
                . '<span class="%1$s__name">%4$s</span>'
                . '</a></li>',
                $block_class,
                esc_url($link),
                CategoryImage::html($category->term_id, [
                    'loading'  => 'lazy',
                    'decoding' => 'async',
                    'sizes'    => $sizes,
                ]),
                esc_html($category->name)
            );
        }

        return $items;
    }
}
