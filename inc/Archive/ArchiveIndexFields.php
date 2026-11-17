<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Archive;

use Pixonaut\Shop\Product\LowestPrice;
use Pixonaut\Shop\Product\SecondPicture;
use WC_Product;

/**
 * What the theme's cards show beyond what Pixonaut for WooCommerce puts in an
 * archive's index, added to each card and variation as the plugin builds it,
 * so the archive filtered in the browser (`src/archive/client/`) draws its
 * cards as the server does: the picture a card shows while it is hovered
 * (`Product\SecondPicture`) and, for a reduced variation, its lowest price in
 * the 30 days before (`Product\LowestPrice`).
 */
final class ArchiveIndexFields
{
    public const CARD_FILTER = 'pixonaut_wc_archive_index_card';

    public const VARIATION_FILTER = 'pixonaut_wc_archive_index_variation';

    /** The size the second picture is printed at on a card. */
    private const IMAGE_SIZE = 'large';

    public function __construct(private readonly LowestPrice $lowest_price)
    {
    }

    public function register(): void
    {
        add_filter(self::CARD_FILTER, [$this, 'card'], 10, 2);
        add_filter(self::VARIATION_FILTER, [$this, 'variation'], 10, 2);
    }

    /**
     * A card with its second picture, if its product has one.
     */
    public function card(mixed $card, mixed $product): mixed
    {
        if (! \is_array($card) || ! $product instanceof WC_Product) {
            return $card;
        }

        $picture = SecondPicture::next((int) $product->get_image_id(), $product->get_gallery_image_ids());
        $source  = 0 === $picture || ! wp_attachment_is_image($picture) ? false : wp_get_attachment_image_src($picture, self::IMAGE_SIZE);

        if (\is_array($source)) {
            $card['secondImage'] = [
                'src'    => (string) $source[0],
                'srcset' => (string) wp_get_attachment_image_srcset($picture, self::IMAGE_SIZE),
                'width'  => (int) $source[1],
                'height' => (int) $source[2],
            ];
        }

        return $card;
    }

    /**
     * A variation's row with its lowest price before a reduction, as the
     * shop displays it, while it is reduced.
     */
    public function variation(mixed $row, mixed $variation): mixed
    {
        if (! \is_array($row) || ! $variation instanceof WC_Product) {
            return $row;
        }

        $reference = $variation->is_on_sale() ? $this->lowest_price->reference($variation) : null;

        if (null !== $reference) {
            $row['lowestPrice'] = $reference;
        }

        return $row;
    }
}
