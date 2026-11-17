<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Pixonaut;

use Pixonaut\Shop\Campaign\CampaignBand;
use Pixonaut\Shop\FrontPage\Hero;
use Pixonaut\Shop\Header\HeaderVariant;
use Pixonaut\Shop\Product\VariablePrice;

/**
 * The settings a store makes in the theme's options, declared through
 * Pixonaut for WooCommerce's documented `pixonaut_wc_theme_settings` filter,
 * so the plugin's tools can read and change them as one list: each keyed by
 * its option, with a label, a description, a JSON Schema as WordPress's REST
 * API validates it, and the default the theme shows while the option is unset.
 *
 * Each schema accepts what the class that reads the option shows. Without the
 * plugin nothing applies the filter, and nothing here runs.
 */
final class ThemeSettings
{
    /** The plugin's filter, applied to an empty array, of the settings a theme declares. */
    public const FILTER = 'pixonaut_wc_theme_settings';

    /** Text with more than spaces in it, as JSON Schema's `pattern` matches anywhere in a string. */
    private const NOT_BLANK = '\S';

    /** The fewest characters a text that must not be empty holds. */
    private const NOT_EMPTY = 1;

    /** The lowest ID WordPress gives an attachment. */
    private const FIRST_ID = 1;

    public function register(): void
    {
        add_filter(self::FILTER, [$this, 'declare']);
    }

    /**
     * The settings others declared, with the theme's four after them.
     *
     * @param array<mixed> $settings
     * @return array<mixed>
     */
    public function declare(array $settings): array
    {
        $settings[HeaderVariant::OPTION] = [
            'label' => __('Header', 'pixonaut-shop'),
            'description' => __('Which header the store shows: "simple", one row with the logo, menu, search, account and cart, or "mega", whose menu button opens a panel of the store\'s categories.', 'pixonaut-shop'),
            'schema' => ['type' => 'string', 'enum' => HeaderVariant::VARIANTS],
            'default' => HeaderVariant::SIMPLE,
        ];
        $settings[VariablePrice::SETTING] = [
            'label' => VariablePrice::label(),
            'description' => __('How a product with variations shows its price until one is chosen: "from", "From" and its lowest price; "from_short", "Fr." and its lowest price; or "range", its lowest price to its highest.', 'pixonaut-shop'),
            'schema' => ['type' => 'string', 'enum' => VariablePrice::CHOICES],
            'default' => VariablePrice::DEFAULT,
        ];
        $settings[Hero::OPTION] = [
            'label' => __('Front page hero', 'pixonaut-shop'),
            'description' => __('The hero at the top of the front page: a heading, a sentence, a button\'s label and the address it links to, and the ID of a picture in the media library. Without one, the front page shows its placeholders.', 'pixonaut-shop'),
            'schema' => self::hero_schema(),
            'default' => null,
        ];
        $settings[CampaignBand::OPTION] = [
            'label' => __('Campaign band', 'pixonaut-shop'),
            'description' => __('A line of text above the header on every page, linked to an address if it has one, shown from its start to its end: each a date, or a date and time, in the site\'s time zone. Without one, no band shows.', 'pixonaut-shop'),
            'schema' => self::campaign_schema(),
            'default' => null,
        ];

        return $settings;
    }

    /** @return array<string, mixed> */
    private static function hero_schema(): array
    {
        $required_text = ['type' => 'string', 'minLength' => self::NOT_EMPTY, 'pattern' => self::NOT_BLANK];

        return [
            'type' => ['object', 'null'],
            'properties' => [
                Hero::HEADING_KEY => $required_text,
                Hero::TEXT_KEY => ['type' => 'string'],
                Hero::BUTTON_LABEL_KEY => $required_text,
                Hero::BUTTON_URL_KEY => ['type' => 'string', 'minLength' => self::NOT_EMPTY],
                Hero::IMAGE_ID_KEY => ['type' => 'integer', 'minimum' => self::FIRST_ID],
            ],
            'required' => [...Hero::TEXT_KEYS, Hero::IMAGE_ID_KEY],
            'additionalProperties' => false,
        ];
    }

    /** @return array<string, mixed> */
    private static function campaign_schema(): array
    {
        $date = ['type' => 'string', 'pattern' => CampaignBand::DATE_PATTERN];

        return [
            'type' => ['object', 'null'],
            'properties' => [
                CampaignBand::TEXT_KEY => ['type' => 'string', 'minLength' => self::NOT_EMPTY, 'pattern' => self::NOT_BLANK],
                CampaignBand::URL_KEY => ['type' => 'string'],
                CampaignBand::STARTS_KEY => $date,
                CampaignBand::ENDS_KEY => $date,
            ],
            'required' => [CampaignBand::TEXT_KEY, CampaignBand::STARTS_KEY, CampaignBand::ENDS_KEY],
            'additionalProperties' => false,
        ];
    }
}
