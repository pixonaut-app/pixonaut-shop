<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Pixonaut;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Pixonaut\Shop\Campaign\CampaignBand;
use Pixonaut\Shop\FrontPage\Hero;
use Pixonaut\Shop\Header\HeaderVariant;
use Pixonaut\Shop\Pixonaut\ThemeSettings;
use Pixonaut\Shop\Product\VariablePrice;
use Pixonaut\Shop\Tests\TestCase;

#[CoversClass(ThemeSettings::class)]
final class ThemeSettingsTest extends TestCase
{
    /** A setting a theme declared before the theme's callback runs. */
    private const OTHER_SETTING = 'another_theme_option';

    protected function setUp(): void
    {
        parent::setUp();
        Functions\stubTranslationFunctions();
    }

    public function testRegistersOnThePluginsThemeSettingsFilter(): void
    {
        (new ThemeSettings())->register();

        self::assertSame('pixonaut_wc_theme_settings', ThemeSettings::FILTER);
        self::assertNotFalse(
            has_filter(ThemeSettings::FILTER, 'Pixonaut\Shop\Pixonaut\ThemeSettings->declare()')
        );
    }

    public function testDeclaresTheFourOptionsTheThemeReads(): void
    {
        $settings = (new ThemeSettings())->declare([]);

        self::assertSame(
            [HeaderVariant::OPTION, VariablePrice::SETTING, Hero::OPTION, CampaignBand::OPTION],
            array_keys($settings)
        );
    }

    public function testKeepsWhatOthersDeclared(): void
    {
        $other = ['label' => 'Other', 'description' => 'Other', 'schema' => ['type' => 'string'], 'default' => ''];

        $settings = (new ThemeSettings())->declare([self::OTHER_SETTING => $other]);

        self::assertSame($other, $settings[self::OTHER_SETTING]);
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
    public function testEachSettingHasALabelADescriptionAndASchema(string $option): void
    {
        $setting = (new ThemeSettings())->declare([])[$option];

        self::assertIsString($setting['label']);
        self::assertNotSame('', $setting['label']);
        self::assertIsString($setting['description']);
        self::assertNotSame('', $setting['description']);
        self::assertIsArray($setting['schema']);
        self::assertArrayHasKey('default', $setting);
    }

    public function testTheHeaderIsOneOfTheVariantsTheThemeBuiltSimpleByDefault(): void
    {
        $setting = (new ThemeSettings())->declare([])[HeaderVariant::OPTION];

        self::assertSame(['type' => 'string', 'enum' => [HeaderVariant::SIMPLE, HeaderVariant::MEGA]], $setting['schema']);
        self::assertSame(HeaderVariant::SIMPLE, $setting['default']);
    }

    public function testTheVariablePriceIsOneOfItsChoicesFromByDefault(): void
    {
        $setting = (new ThemeSettings())->declare([])[VariablePrice::SETTING];

        self::assertSame(
            ['type' => 'string', 'enum' => [VariablePrice::FROM, VariablePrice::FROM_SHORT, VariablePrice::RANGE]],
            $setting['schema']
        );
        self::assertSame(VariablePrice::DEFAULT, $setting['default']);
    }

    public function testTheHeroIsAnObjectOfItsFiveKeysOrNullByDefault(): void
    {
        $setting = (new ThemeSettings())->declare([])[Hero::OPTION];
        $schema = $setting['schema'];

        self::assertSame(['object', 'null'], $schema['type']);
        self::assertFalse($schema['additionalProperties']);
        self::assertSame(['heading', 'text', 'button_label', 'button_url', 'image_id'], array_keys($schema['properties']));
        self::assertSame(array_keys($schema['properties']), $schema['required']);
        self::assertSame('integer', $schema['properties']['image_id']['type']);
        self::assertSame(1, $schema['properties']['image_id']['minimum']);
        self::assertSame(1, $schema['properties']['heading']['minLength']);
        self::assertSame(1, $schema['properties']['button_label']['minLength']);
        self::assertNull($setting['default']);
    }

    public function testTheCampaignIsAnObjectWithTextAndDatesOrNullByDefault(): void
    {
        $setting = (new ThemeSettings())->declare([])[CampaignBand::OPTION];
        $schema = $setting['schema'];

        self::assertSame(['object', 'null'], $schema['type']);
        self::assertFalse($schema['additionalProperties']);
        self::assertSame(['text', 'url', 'starts', 'ends'], array_keys($schema['properties']));
        self::assertSame(['text', 'starts', 'ends'], $schema['required']);
        self::assertSame(1, $schema['properties']['text']['minLength']);
        self::assertSame(CampaignBand::DATE_PATTERN, $schema['properties']['starts']['pattern']);
        self::assertSame(CampaignBand::DATE_PATTERN, $schema['properties']['ends']['pattern']);
        self::assertNull($setting['default']);
    }
}
