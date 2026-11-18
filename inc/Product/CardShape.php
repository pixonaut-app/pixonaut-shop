<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Product;

use Pixonaut\Shop\Assets\AssetManager;
use Pixonaut\Shop\Blocks\AspectRatios;
use WP_Block_Patterns_Registry;
use WP_Block_Template;

/**
 * The shape of a product card's picture, the one place it is set: the store
 * picks it on the Product Image block of the shop's cards in the Site Editor
 * (one of the `Blocks\AspectRatios` shapes), with how the picture fills it
 * (the block's Scale). Every other place that shows a product's picture as a
 * card or a thumbnail reads it from `ASPECT_PROPERTY` and `FIT_PROPERTY`, so
 * a print is never cropped differently from its card.
 *
 * Until the store picks a shape, neither property is printed and each
 * picture keeps its own shape. The stylesheet shapes a picture only under
 * `BODY_CLASS`: an `aspect-ratio` read from a property that is not set would
 * drop the room a picture's width and height hold open while it loads.
 */
final class CardShape
{
    /** The shape's width per unit of height. */
    public const ASPECT_PROPERTY = '--pixonaut-shop-card-aspect';

    /** How the picture fills the shape, as `object-fit` takes it. */
    public const FIT_PROPERTY = '--pixonaut-shop-card-fit';

    /** On the body of every page while the store's cards have a shape. */
    public const BODY_CLASS = 'pixonaut-shop-card-shaped';

    /** The size WooCommerce's Product Image block prints a card's picture at, in the picture's own shape. */
    public const PICTURE_SIZE = 'large';

    /**
     * A thumbnail's size: WordPress's medium keeps the picture's own shape,
     * where WooCommerce's thumbnail sizes are cropped square unless the store
     * changes them.
     */
    public const THUMBNAIL_SIZE = 'medium';

    /** The template whose cards the store shapes: the shop, which the category archives fall back to. */
    private const TEMPLATE = 'archive-product';

    private const CARDS_BLOCK = 'woocommerce/product-template';

    private const PICTURE_BLOCK = 'woocommerce/product-image';

    private const PATTERN_BLOCK = 'core/pattern';

    /** The Product Image block's Scale choices, `object-fit` values all. */
    private const FITS = ['cover', 'contain', 'fill'];

    /** The Product Image block's Scale until the store changes it. */
    private const DEFAULT_FIT = 'cover';

    /** After `AssetManager` enqueues the stylesheet the properties are added to, at the default priority. */
    private const AFTER_THE_STYLESHEET = 20;

    /** @var array{aspect: float, fit: string}|false|null The shape, false for none, null until read. */
    private array|false|null $shape = null;

    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'print_properties'], self::AFTER_THE_STYLESHEET);
        add_filter('body_class', [$this, 'body_class']);
        add_filter('woocommerce_gallery_thumbnail_size', [$this, 'gallery_thumbnail_size']);
    }

    public function print_properties(): void
    {
        $shape = $this->shape();
        if (null === $shape) {
            return;
        }

        wp_add_inline_style(AssetManager::HANDLE, \sprintf(
            ':root{%1$s:%2$s;%3$s:%4$s}',
            self::ASPECT_PROPERTY,
            AspectRatios::css_number($shape['aspect']),
            self::FIT_PROPERTY,
            $shape['fit']
        ));
    }

    /**
     * @param array<array-key, mixed> $classes
     * @return array<array-key, mixed>
     */
    public function body_class(array $classes): array
    {
        if (null !== $this->shape()) {
            $classes[] = self::BODY_CLASS;
        }

        return $classes;
    }

    /**
     * The product page gallery's thumbnails in their pictures' own shape,
     * which the stylesheet then gives the card's.
     */
    public function gallery_thumbnail_size(): string
    {
        return self::THUMBNAIL_SIZE;
    }

    /**
     * The shape of the shop's cards' pictures, or null while they keep each
     * picture's own.
     *
     * @return array{aspect: float, fit: string}|null
     */
    public function shape(): ?array
    {
        if (null === $this->shape) {
            $template    = get_block_template(get_stylesheet() . '//' . self::TEMPLATE);
            $this->shape = ($template instanceof WP_Block_Template && \is_string($template->content)
                ? self::of_blocks(parse_blocks($template->content), self::registered_pattern(...))
                : null) ?? false;
        }

        return false === $this->shape ? null : $this->shape;
    }

    /**
     * The shape of the first card's picture in these blocks, reading the
     * blocks of each pattern they insert; null when that picture keeps its
     * own shape, or there is no card.
     *
     * @param array<array-key, mixed>   $blocks  Parsed blocks.
     * @param callable(string): array<array-key, mixed> $pattern A registered pattern's parsed blocks by its slug.
     * @return array{aspect: float, fit: string}|null
     */
    public static function of_blocks(array $blocks, callable $pattern): ?array
    {
        $picture = self::first_card_picture($blocks, $pattern, false, []);

        return null === $picture ? null : self::of_picture($picture);
    }

    /**
     * The attributes of the first Product Image block inside a card.
     *
     * @param array<array-key, mixed>   $blocks
     * @param callable(string): array<array-key, mixed> $pattern
     * @param list<string>              $reading The patterns being read, so a pattern that inserts itself ends.
     * @return array<array-key, mixed>|null
     */
    private static function first_card_picture(array $blocks, callable $pattern, bool $in_card, array $reading): ?array
    {
        foreach ($blocks as $block) {
            if (! \is_array($block)) {
                continue;
            }

            $name       = $block['blockName'] ?? null;
            $attributes = \is_array($block['attrs'] ?? null) ? $block['attrs'] : [];

            if ($in_card && self::PICTURE_BLOCK === $name) {
                return $attributes;
            }

            $inner = \is_array($block['innerBlocks'] ?? null) ? $block['innerBlocks'] : [];
            $slug  = $attributes['slug'] ?? null;
            if (self::PATTERN_BLOCK === $name && \is_string($slug) && ! \in_array($slug, $reading, true)) {
                $inner     = $pattern($slug);
                $reading[] = $slug;
            }

            $found = self::first_card_picture($inner, $pattern, $in_card || self::CARDS_BLOCK === $name, $reading);
            if (null !== $found) {
                return $found;
            }
        }

        return null;
    }

    /**
     * The shape a Product Image block gives its picture, read as WooCommerce
     * reads it: the block's dimensions first, then its own setting.
     *
     * @param array<array-key, mixed> $attributes
     * @return array{aspect: float, fit: string}|null
     */
    private static function of_picture(array $attributes): ?array
    {
        $style      = $attributes['style'] ?? null;
        $dimensions = \is_array($style) && \is_array($style['dimensions'] ?? null) ? $style['dimensions'] : [];
        $ratio      = $dimensions['aspectRatio'] ?? $attributes['aspectRatio'] ?? null;
        $aspect     = \is_string($ratio) ? AspectRatios::aspect($ratio) : null;

        if (null === $aspect) {
            return null;
        }

        $scale = $attributes['scale'] ?? null;

        return [
            'aspect' => $aspect,
            'fit'    => \in_array($scale, self::FITS, true) ? $scale : self::DEFAULT_FIT,
        ];
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function registered_pattern(string $slug): array
    {
        $pattern = WP_Block_Patterns_Registry::get_instance()->get_registered($slug);
        $content = \is_array($pattern) ? ($pattern['content'] ?? null) : null;

        return \is_string($content) ? parse_blocks($content) : [];
    }
}
