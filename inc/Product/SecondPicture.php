<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Product;

use Pixonaut\Shop\Assets\BuiltScript;
use WC_Product;

/**
 * A product card's second picture, which `src/styles/_components.css` shows
 * in place of the first while the card is hovered or holds the focus.
 *
 * It is the product's next gallery picture: the one after the card's
 * picture when that is in the gallery itself, else the gallery's first. A
 * store chooses it by the order of the product's gallery, and a product
 * with no other picture keeps its card as it is. Both pictures are read
 * through WooCommerce, so whatever shows a product's card with another
 * picture, or another gallery, has its second picture follow.
 *
 * The picture is printed after the first inside WooCommerce's Product Image
 * block, in a query loop only: lazy, with its size, an empty alt text beside
 * the first one's, and over the first picture's box, so it never moves the
 * card. The first stays under it, and the theme's script
 * (`src/product/second-picture.ts`), loaded with the first card that has a
 * second picture, takes out one that fails to load, so the first is left in
 * view.
 */
final class SecondPicture
{
    public const IMAGE_BLOCK = 'woocommerce/product-image';

    /** The second picture's class, which `src/styles/_components.css` lays over the first. */
    public const CLASS_NAME = 'pixonaut-shop-second-picture';

    public const SCRIPT_HANDLE = 'pixonaut-shop-second-picture';

    /** The built script's name in `build/`. */
    private const SCRIPT = 'second-picture';

    /** The size WooCommerce's Product Image block prints its picture at. */
    private const IMAGE_SIZE = 'large';

    /** The block's attributes the second picture follows, and the CSS property each sets. */
    private const STYLE_ATTRIBUTES = ['width' => 'width', 'scale' => 'object-fit'];

    /** The block's first picture. */
    private const FIRST_PICTURE = '/<img\b[^>]*>/';

    public function register(): void
    {
        add_filter('render_block_' . self::IMAGE_BLOCK, [$this, 'render'], 10, 3);
    }

    /**
     * @param array<array-key, mixed> $block
     * @param object|null             $instance The `WP_Block`, typed as an object so the unit
     *                                          suite, which has none, can pass a stand-in.
     */
    public function render(string $block_content, array $block, ?object $instance = null): string
    {
        $context = $instance->context ?? null;

        // In a loop of products only, and never where the block shows a picture chosen for it.
        if ('' === $block_content || ! \is_array($context) || ! isset($context['queryId'], $context['postId']) || isset($context['imageId'])) {
            return $block_content;
        }

        $product = wc_get_product($context['postId']);
        $picture = $product instanceof WC_Product
            ? self::next((int) $product->get_image_id(), $product->get_gallery_image_ids())
            : 0;

        if (0 === $picture || ! wp_attachment_is_image($picture)) {
            return $block_content;
        }

        $style = self::style(\is_array($block['attrs'] ?? null) ? $block['attrs'] : []);
        $image = wp_get_attachment_image($picture, self::IMAGE_SIZE, false, [
            'class'   => self::CLASS_NAME,
            'alt'     => '',
            'loading' => 'lazy',
        ] + ('' === $style ? [] : ['style' => $style]));

        if ('' === $image) {
            return $block_content;
        }

        BuiltScript::enqueue(self::SCRIPT_HANDLE, self::SCRIPT);

        return (string) preg_replace_callback(
            self::FIRST_PICTURE,
            static fn (array $first): string => $first[0] . $image,
            $block_content,
            1
        );
    }

    /**
     * What the store set on the block for the card's picture and WooCommerce
     * prints on the first: its width, so the second covers the first alone
     * rather than the card's whole width, and its scale. The stylesheet's
     * own without them.
     *
     * @param array<array-key, mixed> $attributes The block's attributes.
     */
    private static function style(array $attributes): string
    {
        $style = '';

        foreach (self::STYLE_ATTRIBUTES as $attribute => $property) {
            $value = $attributes[$attribute] ?? null;

            if (\is_string($value) && '' !== $value) {
                $style .= $property . ':' . $value . ';';
            }
        }

        return $style;
    }

    /**
     * The gallery picture after the card's: the one that follows it in the
     * gallery, going round to the gallery's first, or the gallery's first
     * when the card's picture is not in it. 0 when the card has no picture,
     * or the gallery no other one.
     *
     * @param array<array-key, mixed> $gallery Attachment IDs, in the gallery's order.
     */
    public static function next(int $picture, array $gallery): int
    {
        if ($picture <= 0) {
            return 0;
        }

        $gallery  = array_values(array_filter(array_map(
            static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0,
            $gallery
        )));
        $position = array_search($picture, $gallery, true);
        $after    = false === $position ? $gallery : [...\array_slice($gallery, $position + 1), ...\array_slice($gallery, 0, $position)];

        foreach ($after as $candidate) {
            if ($candidate !== $picture) {
                return $candidate;
            }
        }

        return 0;
    }
}
