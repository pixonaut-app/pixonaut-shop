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
 * attachment's ID. The heading and the button's label hold more than
 * spaces, and the button's address is one WordPress links to. Anything else
 * is not a hero, and the pattern keeps its placeholders.
 */
final class Hero
{
    /** The option a store's hero is saved in. */
    public const OPTION = 'pixonaut_shop_hero';

    /** The attachment size the picture is shown at, which core/media-text names in its image's class. */
    public const IMAGE_SIZE = 'full';

    public const HEADING_KEY = 'heading';

    public const TEXT_KEY = 'text';

    public const BUTTON_LABEL_KEY = 'button_label';

    public const BUTTON_URL_KEY = 'button_url';

    public const IMAGE_ID_KEY = 'image_id';

    /** The keys of the option that hold text. */
    public const TEXT_KEYS = [self::HEADING_KEY, self::TEXT_KEY, self::BUTTON_LABEL_KEY, self::BUTTON_URL_KEY];

    /** The keys of the option without whose text the hero shows nothing worth showing. */
    public const REQUIRED_TEXT_KEYS = [self::HEADING_KEY, self::BUTTON_LABEL_KEY];

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

        foreach (self::REQUIRED_TEXT_KEYS as $key) {
            if (! \is_string($option[$key]) || '' === trim($option[$key])) {
                return null;
            }
        }

        // An address WordPress would not link to, a `javascript:` one among them, is none.
        if ('' === esc_url_raw($option[self::BUTTON_URL_KEY])) {
            return null;
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
            $option[self::HEADING_KEY],
            $option[self::TEXT_KEY],
            $option[self::BUTTON_LABEL_KEY],
            $option[self::BUTTON_URL_KEY],
            $image_id,
            $image_url,
            \is_string($alt) ? $alt : '',
        );
    }
}
