<?php

declare(strict_types=1);

namespace Pixonaut\Shop\FrontPage;

/**
 * A store's own hero, saved in the `pixonaut_shop_hero` option by a tool that
 * sets up the store: a heading, a sentence, one button and a picture from the
 * media library. `patterns/hero.php` shows it in place of its placeholders.
 *
 * The option is an array with the string keys `heading`, `text`,
 * `button_label` and `button_url`, and the int key `image_id`, an image
 * attachment's ID. Anything else is not a hero, and the pattern keeps its
 * placeholders.
 */
final class Hero
{
    /** The option a store's hero is saved in. */
    public const OPTION = 'pixonaut_shop_hero';

    /** The attachment size the picture is shown at, which core/media-text names in its image's class. */
    public const IMAGE_SIZE = 'full';

    /** The keys of the option that hold text. */
    private const TEXT_KEYS = ['heading', 'text', 'button_label', 'button_url'];

    private const IMAGE_ID_KEY = 'image_id';

    /** Where WordPress keeps an attachment's alt text. */
    private const ALT_META = '_wp_attachment_image_alt';

    private function __construct(
        public readonly string $heading,
        public readonly string $text,
        public readonly string $button_label,
        public readonly string $button_url,
        public readonly int $image_id,
        public readonly string $image_url,
        public readonly string $image_alt,
    ) {
    }

    /** The store's hero, or null while the option is unset or not a hero. */
    public static function from_option(): ?self
    {
        $option = get_option(self::OPTION);

        if (! \is_array($option)) {
            return null;
        }

        foreach (self::TEXT_KEYS as $key) {
            if (! \is_string($option[$key] ?? null)) {
                return null;
            }
        }

        $image_id = $option[self::IMAGE_ID_KEY] ?? null;

        if (! \is_int($image_id) || ! wp_attachment_is_image($image_id)) {
            return null;
        }

        $image_url = wp_get_attachment_image_url($image_id, self::IMAGE_SIZE);

        if (! \is_string($image_url)) {
            return null;
        }

        $alt = get_post_meta($image_id, self::ALT_META, true);

        return new self(
            $option['heading'],
            $option['text'],
            $option['button_label'],
            $option['button_url'],
            $image_id,
            $image_url,
            \is_string($alt) ? $alt : '',
        );
    }
}
