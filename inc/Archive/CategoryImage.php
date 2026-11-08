<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Archive;

/**
 * A product category's picture, as the store chose it on the category's
 * edit screen, at the size of a product card's picture.
 */
final class CategoryImage
{
    /** The term meta WooCommerce keeps a category's image in, as an attachment ID. */
    public const THUMBNAIL_META = 'thumbnail_id';

    /** WooCommerce's image size for a product card's picture. */
    public const IMAGE_SIZE = 'woocommerce_thumbnail';

    /**
     * The category's `<img>`, with no alt text of its own, since the
     * category's name always shows beside it; an empty string when the
     * category has no picture.
     *
     * @param array<string, string> $attributes More attributes for the `<img>`.
     */
    public static function html(int $term_id, array $attributes = []): string
    {
        $image_id = get_term_meta($term_id, self::THUMBNAIL_META, true);

        if (! is_numeric($image_id) || (int) $image_id <= 0) {
            return '';
        }

        return wp_get_attachment_image((int) $image_id, self::IMAGE_SIZE, false, ['alt' => ''] + $attributes);
    }
}
