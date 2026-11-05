<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Blocks;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Pixonaut\Shop\Blocks\AspectRatios;
use Pixonaut\Shop\Tests\TestCase;
use WP_Theme_JSON_Data;

#[CoversClass(AspectRatios::class)]
final class AspectRatiosTest extends TestCase
{
    /** Every shape the theme offers, square first, then the portrait print sizes and their landscape turns. */
    private const PRESETS = [
        ['name' => 'Square - 1:1', 'slug' => 'square', 'ratio' => '1'],
        ['name' => 'Portrait - 4:5', 'slug' => '4-5', 'ratio' => '4/5'],
        ['name' => 'Portrait - 5:7', 'slug' => '5-7', 'ratio' => '5/7'],
        ['name' => 'Portrait - 3:4', 'slug' => '3-4', 'ratio' => '3/4'],
        ['name' => 'Portrait - 2:3', 'slug' => '2-3', 'ratio' => '2/3'],
        ['name' => 'Landscape - 5:4', 'slug' => '5-4', 'ratio' => '5/4'],
        ['name' => 'Landscape - 7:5', 'slug' => '7-5', 'ratio' => '7/5'],
        ['name' => 'Landscape - 4:3', 'slug' => '4-3', 'ratio' => '4/3'],
        ['name' => 'Landscape - 3:2', 'slug' => '3-2', 'ratio' => '3/2'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Functions\stubTranslationFunctions();
    }

    public function testRegistersOnTheThemesThemeJson(): void
    {
        (new AspectRatios())->register();

        self::assertNotFalse(has_filter('wp_theme_json_data_theme', 'Pixonaut\Shop\Blocks\AspectRatios->add_presets()'));
    }

    public function testOffersThePrintShapesWithFiveBySevenAmongThem(): void
    {
        self::assertSame(self::PRESETS, (new AspectRatios())->presets());
    }

    public function testADeveloperExtendsTheShapesThroughAFilter(): void
    {
        $panorama = ['name' => 'Panorama - 3:1', 'slug' => '3-1', 'ratio' => '3/1'];
        Filters\expectApplied(AspectRatios::FILTER)
            ->once()
            ->with(self::PRESETS)
            ->andReturnUsing(static fn (array $presets): array => [...$presets, $panorama]);

        self::assertSame([...self::PRESETS, $panorama], (new AspectRatios())->presets());
    }

    public function testLeavesOutAShapeAFilterGotWrong(): void
    {
        Filters\expectApplied(AspectRatios::FILTER)->andReturn([
            self::PRESETS[0],
            ['name' => 'No ratio', 'slug' => 'none'],
            ['name' => 'Zero', 'slug' => 'zero', 'ratio' => '0/1'],
            ['name' => 'Words', 'slug' => 'words', 'ratio' => 'wide'],
            ['name' => 'Bad slug', 'slug' => 'Bad slug!', 'ratio' => '2/1'],
            'not a preset',
        ]);

        self::assertSame([self::PRESETS[0]], (new AspectRatios())->presets());
    }

    public function testAShapeAFilterRepeatsReplacesTheEarlierOneInItsPlace(): void
    {
        $wider = ['name' => 'Square, wider', 'slug' => 'square', 'ratio' => '11/10'];
        Filters\expectApplied(AspectRatios::FILTER)->andReturn([self::PRESETS[0], self::PRESETS[1], $wider]);

        self::assertSame([$wider, self::PRESETS[1]], (new AspectRatios())->presets());
    }

    public function testAddsTheShapesToTheThemesPresets(): void
    {
        $data    = Mockery::mock(WP_Theme_JSON_Data::class);
        $updated = Mockery::mock(WP_Theme_JSON_Data::class);
        $data->expects('update_with')
            ->with([
                'version'  => 3,
                'settings' => ['dimensions' => ['aspectRatios' => self::PRESETS]],
            ])
            ->andReturn($updated);

        self::assertSame($updated, (new AspectRatios())->add_presets($data));
    }

    /**
     * @return iterable<string, array{string, float|null}>
     */
    public static function ratios(): iterable
    {
        yield 'a whole number' => ['1', 1.0];
        yield 'width over height' => ['5/7', 5 / 7];
        yield 'spaced' => ['16 / 9', 16 / 9];
        yield 'decimals' => ['1.5/1', 1.5];
        yield 'nothing' => ['', null];
        yield 'zero height' => ['1/0', null];
        yield 'a word' => ['auto', null];
        yield 'three parts' => ['1/2/3', null];
    }

    #[DataProvider('ratios')]
    public function testReadsARatioAsWidthPerUnitOfHeight(string $ratio, ?float $aspect): void
    {
        self::assertSame($aspect, AspectRatios::aspect($ratio));
    }
}
