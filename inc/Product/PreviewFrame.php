<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Product;

use Pixonaut\Shop\Blocks\AspectRatios;

/**
 * The shape of the product page's preview frame, which a store picks in the
 * Site Editor as a style of the Product Image Gallery block: one per
 * `Blocks\AspectRatios` shape. The default style follows the shape of the
 * picture Pixonaut for WooCommerce's designer shows, and until it has drawn,
 * the shape the plugin opens the preview at (`src/styles/_product.css`).
 *
 * The plugin prints its preview in place of the gallery's whole output, the
 * block's style class with it, so a chosen shape is carried by a wrapper
 * printed around whatever the block rendered, the gallery or the preview.
 * Without a chosen style there is no wrapper, and each side's frame follows
 * its own picture. Each style also sets the shape on the block's own class,
 * which is what the Site Editor's placeholder gallery shows.
 */
final class PreviewFrame
{
    public const GALLERY_BLOCK = 'woocommerce/product-image-gallery';

    /** The class of the wrapper, which `src/styles/_product.css` sizes the frame by. */
    public const FRAME_CLASS = 'pixonaut-shop-product-frame';

    /** What a frame style's name starts with; the shape's slug follows. */
    private const STYLE_PREFIX = 'frame-';

    /** The prefix WordPress gives a block style's name in the block's class. */
    private const STYLE_CLASS_PREFIX = 'is-style-';

    /** After the plugin prints its preview in the gallery's place, at the default priority. */
    private const AFTER_THE_PLUGIN = 20;

    public function __construct(private readonly AspectRatios $ratios)
    {
    }

    public function register(): void
    {
        add_action('init', [$this, 'register_styles']);
        add_filter('render_block_' . self::GALLERY_BLOCK, [$this, 'render'], self::AFTER_THE_PLUGIN, 2);
    }

    public function register_styles(): void
    {
        foreach ($this->ratios->presets() as $preset) {
            $name   = self::STYLE_PREFIX . $preset['slug'];
            $aspect = AspectRatios::aspect($preset['ratio']);
            register_block_style(self::GALLERY_BLOCK, [
                'name'         => $name,
                'label'        => $preset['name'],
                'inline_style' => null === $aspect ? '' : \sprintf(
                    '.wp-block-woocommerce-product-image-gallery.%1$s%2$s{--ps-frame-aspect:%3$s}',
                    self::STYLE_CLASS_PREFIX,
                    $name,
                    AspectRatios::css_number($aspect)
                ),
            ]);
        }
    }

    /**
     * @param array<array-key, mixed> $block
     */
    public function render(string $block_content, array $block): string
    {
        $aspect = '' === $block_content ? null : $this->chosen_aspect($block);
        if (null === $aspect) {
            return $block_content;
        }

        return \sprintf(
            '<div class="%1$s" style="--ps-frame-aspect: %2$s">%3$s</div>',
            esc_attr(self::FRAME_CLASS),
            esc_attr(AspectRatios::css_number($aspect)),
            $block_content
        );
    }

    /**
     * @param array<array-key, mixed> $block
     */
    private function chosen_aspect(array $block): ?float
    {
        $attributes = $block['attrs'] ?? [];
        $class_name = \is_array($attributes) ? ($attributes['className'] ?? '') : '';
        $classes    = \is_string($class_name) ? explode(' ', $class_name) : [];

        foreach ($this->ratios->presets() as $preset) {
            if (\in_array(self::STYLE_CLASS_PREFIX . self::STYLE_PREFIX . $preset['slug'], $classes, true)) {
                return AspectRatios::aspect($preset['ratio']);
            }
        }

        return null;
    }
}
