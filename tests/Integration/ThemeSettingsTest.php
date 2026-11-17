<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pixonaut\Shop\Campaign\CampaignBand;
use Pixonaut\Shop\FrontPage\Hero;
use Pixonaut\Shop\Header\HeaderVariant;
use Pixonaut\Shop\Pixonaut\ThemeSettings;
use Pixonaut\Shop\Product\VariablePrice;
use WP_Error;

/**
 * The settings the theme declares through Pixonaut for WooCommerce's
 * `pixonaut_wc_theme_settings` filter, checked as the plugin checks a value
 * a tool sends: with WordPress's `rest_validate_value_from_schema()`. A value
 * the theme shows passes; one it would not show is refused.
 */
final class ThemeSettingsTest extends TestCase
{
    private const HERO = [
        'heading' => 'Gifts with a name on them',
        'text' => 'Mugs, prints and tees made for one person.',
        'button_label' => 'See the gifts',
        'button_url' => 'https://shop.test/shop/',
        'image_id' => 12,
    ];

    private const CAMPAIGN = [
        'text' => 'Black Friday: 20% off everything',
        'url' => 'https://shop.test/shop/',
        'starts' => '2026-11-27',
        'ends' => '2026-11-30T23:59:00',
    ];

    protected function tearDown(): void
    {
        delete_option(HeaderVariant::OPTION);
        delete_option(VariablePrice::SETTING);
    }

    public function testTheActiveThemeDeclaresItsFourSettings(): void
    {
        $settings = apply_filters(ThemeSettings::FILTER, []);

        self::assertIsArray($settings);
        foreach ([HeaderVariant::OPTION, VariablePrice::SETTING, Hero::OPTION, CampaignBand::OPTION] as $option) {
            self::assertArrayHasKey($option, $settings);
        }
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function accepted(): iterable
    {
        yield 'the simple header' => [HeaderVariant::OPTION, HeaderVariant::SIMPLE];
        yield 'the mega header' => [HeaderVariant::OPTION, HeaderVariant::MEGA];
        yield 'from' => [VariablePrice::SETTING, VariablePrice::FROM];
        yield 'from, short' => [VariablePrice::SETTING, VariablePrice::FROM_SHORT];
        yield 'the range' => [VariablePrice::SETTING, VariablePrice::RANGE];
        yield 'a hero' => [Hero::OPTION, self::HERO];
        yield 'a hero without a sentence' => [Hero::OPTION, ['text' => ''] + self::HERO];
        yield 'a hero linking within the store' => [Hero::OPTION, ['button_url' => '/shop/'] + self::HERO];
        yield 'no hero' => [Hero::OPTION, null];
        yield 'a campaign' => [CampaignBand::OPTION, self::CAMPAIGN];
        yield 'a campaign without a link' => [CampaignBand::OPTION, array_diff_key(self::CAMPAIGN, ['url' => true])];
        yield 'a campaign with times and an offset' => [CampaignBand::OPTION, ['starts' => '2026-11-27T08:00:00+01:00', 'ends' => '2026-11-30 23:59Z'] + self::CAMPAIGN];
        yield 'no campaign' => [CampaignBand::OPTION, null];
    }

    #[DataProvider('accepted')]
    public function testAcceptsWhatTheThemeShows(string $option, mixed $value): void
    {
        self::assertTrue(rest_validate_value_from_schema($value, self::schema($option), $option));
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function refused(): iterable
    {
        yield 'a header the theme has not built' => [HeaderVariant::OPTION, 'wide'];
        yield 'no header' => [HeaderVariant::OPTION, null];
        yield 'a price choice the theme does not know' => [VariablePrice::SETTING, 'to'];
        yield 'a hero that is a string' => [Hero::OPTION, 'Gifts'];
        yield 'a hero without a picture' => [Hero::OPTION, array_diff_key(self::HERO, ['image_id' => true])];
        yield 'a hero with an empty heading' => [Hero::OPTION, ['heading' => ''] + self::HERO];
        yield 'a hero whose heading is spaces' => [Hero::OPTION, ['heading' => '   '] + self::HERO];
        yield 'a hero with an empty button label' => [Hero::OPTION, ['button_label' => ''] + self::HERO];
        yield 'a hero without a button address' => [Hero::OPTION, ['button_url' => ''] + self::HERO];
        yield 'a hero whose picture is no ID' => [Hero::OPTION, ['image_id' => 0] + self::HERO];
        yield 'a hero whose picture is a name' => [Hero::OPTION, ['image_id' => 'hero.webp'] + self::HERO];
        yield 'a hero with a key it does not have' => [Hero::OPTION, ['pill' => 'New'] + self::HERO];
        yield 'a campaign without text' => [CampaignBand::OPTION, ['text' => ''] + self::CAMPAIGN];
        yield 'a campaign without an end' => [CampaignBand::OPTION, array_diff_key(self::CAMPAIGN, ['ends' => true])];
        yield 'a campaign starting on a date in words' => [CampaignBand::OPTION, ['starts' => 'next Friday'] + self::CAMPAIGN];
        yield 'a campaign ending on a date written the other way round' => [CampaignBand::OPTION, ['ends' => '30/11/2026'] + self::CAMPAIGN];
        yield 'a campaign with a key it does not have' => [CampaignBand::OPTION, ['colour' => 'red'] + self::CAMPAIGN];
    }

    #[DataProvider('refused')]
    public function testRefusesWhatTheThemeWouldNotShow(string $option, mixed $value): void
    {
        self::assertInstanceOf(WP_Error::class, rest_validate_value_from_schema($value, self::schema($option), $option));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function options(): iterable
    {
        yield 'header' => [HeaderVariant::OPTION];
        yield 'variable price' => [VariablePrice::SETTING];
        yield 'hero' => [Hero::OPTION];
        yield 'campaign' => [CampaignBand::OPTION];
    }

    #[DataProvider('options')]
    public function testEachDefaultFitsItsSchema(string $option): void
    {
        $setting = (new ThemeSettings())->declare([])[$option];

        self::assertTrue(rest_validate_value_from_schema($setting['default'], $setting['schema'], $option));
    }

    public function testEveryHeaderTheSchemaAcceptsIsTheHeaderTheStoreShows(): void
    {
        foreach (self::schema(HeaderVariant::OPTION)['enum'] as $variant) {
            update_option(HeaderVariant::OPTION, $variant);

            self::assertSame($variant, HeaderVariant::current());
        }
    }

    public function testEveryPriceChoiceTheSchemaAcceptsIsTheChoiceTheStoreShows(): void
    {
        foreach (self::schema(VariablePrice::SETTING)['enum'] as $choice) {
            update_option(VariablePrice::SETTING, $choice);

            self::assertSame($choice, VariablePrice::current());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function schema(string $option): array
    {
        return (new ThemeSettings())->declare([])[$option]['schema'];
    }
}
