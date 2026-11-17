<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Archive;

use Pixonaut\Shop\Assets\BuiltScript;
use WC_Product;

/**
 * The swatches on an archive's card of a design shown in several of the
 * chosen values of one attribute (Frame: hanger, none and frame): Pixonaut
 * for WooCommerce lists the design once and names, through
 * `pixonaut_wc_storefront_card_swatches`, each of its cards the archive
 * merged, the card's own first (`current`), with the values it is shown for. They follow the card's picture, one button each, the
 * shown one pressed; choosing another shows the design in that value, its
 * picture and the link the card opens, in place (`src/archive/card-swatches.ts`).
 * The archive filtered in the browser draws the same (`src/archive/client/cards.ts`).
 */
final class CardSwatches
{
    public const FILTER = 'pixonaut_wc_storefront_card_swatches';

    public const GROUP_CLASS = 'pixonaut-shop-card-swatches';

    public const SWATCH_CLASS = 'pixonaut-shop-card-swatch';

    private const IMAGE_BLOCK = 'woocommerce/product-image';

    /** Between the labels of a swatch's values, as the archive in the browser joins them. */
    private const LABEL_SEPARATOR = ' / ';

    public const SCRIPT_HANDLE = 'pixonaut-shop-card-swatches';

    public const SCRIPT = 'card-swatches';

    /** After the card's second picture (`Product\SecondPicture`), which goes inside the block. */
    private const AFTER_SECOND_PICTURE = 20;

    public function register(): void
    {
        add_filter('render_block_' . self::IMAGE_BLOCK, [$this, 'render'], self::AFTER_SECOND_PICTURE, 3);
    }

    /**
     * @param array<array-key, mixed> $block
     */
    public function render(string $block_content, array $block, ?object $instance = null): string
    {
        $context = $instance->context ?? null;

        if ('' === $block_content || ! \is_array($context) || ! isset($context['queryId'], $context['postId'])) {
            return $block_content;
        }

        $product = wc_get_product($context['postId']);
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Pixonaut for WooCommerce's own filter, read as its storefront names it.
        $swatches = $product instanceof WC_Product ? self::swatches(apply_filters(self::FILTER, [], $product)) : [];

        if (\count($swatches) < 2 || ! $product instanceof WC_Product) {
            return $block_content;
        }

        BuiltScript::enqueue(self::SCRIPT_HANDLE, self::SCRIPT);

        return $block_content . self::markup($swatches, $product->get_name());
    }

    /** The words that name a card's swatches, `%s` for the design. */
    public static function group_label(): string
    {
        /* translators: %s: a product's name. */
        return __('Versions of %s', 'pixonaut-shop');
    }

    /**
     * The swatches the plugin names that carry what a button needs.
     *
     * @return list<array{label: string, url: string, image: array{src: string, srcset: string, width: int, height: int}, current: bool}>
     */
    private static function swatches(mixed $named): array
    {
        $swatches = [];

        foreach (\is_array($named) ? $named : [] as $swatch) {
            $image = \is_array($swatch) && \is_array($swatch['image'] ?? null) ? $swatch['image'] : null;

            $label = \is_array($swatch) ? self::label($swatch['values'] ?? null) : '';

            if (null === $image || '' === $label || ! \is_string($swatch['url'] ?? null) || ! \is_string($image['src'] ?? null)) {
                continue;
            }

            $swatches[] = [
                'label' => $label,
                'url' => $swatch['url'],
                'image' => [
                    'src' => $image['src'],
                    'srcset' => \is_string($image['srcset'] ?? null) ? $image['srcset'] : '',
                    'width' => is_numeric($image['width'] ?? null) ? (int) $image['width'] : 0,
                    'height' => is_numeric($image['height'] ?? null) ? (int) $image['height'] : 0,
                ],
                'current' => true === ($swatch['current'] ?? false),
            ];
        }

        return $swatches;
    }

    /** A swatch's label: the labels of the values its card is shown for, "Frame / Oak". */
    private static function label(mixed $values): string
    {
        $labels = [];

        foreach (\is_array($values) ? $values : [] as $value) {
            if (\is_array($value) && \is_string($value['label'] ?? null) && '' !== $value['label']) {
                $labels[] = $value['label'];
            }
        }

        return implode(self::LABEL_SEPARATOR, $labels);
    }

    /**
     * @param list<array{label: string, url: string, image: array{src: string, srcset: string, width: int, height: int}, current: bool}> $swatches
     */
    private static function markup(array $swatches, string $name): string
    {
        $buttons = '';

        foreach ($swatches as $swatch) {
            $buttons .= \sprintf(
                '<button type="button" class="%1$s" aria-pressed="%2$s" data-pixonaut-shop-link="%3$s" data-pixonaut-shop-picture="%4$s">%5$s</button>',
                esc_attr(self::SWATCH_CLASS),
                $swatch['current'] ? 'true' : 'false',
                esc_url($swatch['url']),
                esc_attr((string) wp_json_encode($swatch['image'])),
                esc_html($swatch['label'])
            );
        }

        return \sprintf(
            '<div class="%1$s" role="group" aria-label="%2$s">%3$s</div>',
            esc_attr(self::GROUP_CLASS),
            esc_attr(\sprintf(self::group_label(), $name)),
            $buttons
        );
    }
}
