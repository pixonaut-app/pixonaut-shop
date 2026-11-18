<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Product;

use PHPUnit\Framework\Attributes\CoversClass;
use Pixonaut\Shop\Product\CardShape;
use Pixonaut\Shop\Tests\TestCase;

#[CoversClass(CardShape::class)]
final class CardShapeTest extends TestCase
{
    private const PATTERN = 'pixonaut-shop/product-grid';

    public function testACardsPictureWithoutAShapeKeepsItsOwn(): void
    {
        self::assertNull(CardShape::of_blocks([self::cards(self::picture([]))], self::no_patterns(...)));
    }

    public function testReadsTheShapeTheStorePickedOnTheCardsPicture(): void
    {
        self::assertSame(
            ['aspect' => 0.75, 'fit' => 'cover'],
            CardShape::of_blocks([self::cards(self::picture(['aspectRatio' => '3/4']))], self::no_patterns(...))
        );
    }

    public function testTheBlocksDimensionsWinOverItsOwnSettingAsInWooCommerce(): void
    {
        $picture = self::picture(['aspectRatio' => '1', 'style' => ['dimensions' => ['aspectRatio' => '4/5']], 'scale' => 'contain']);

        self::assertSame(['aspect' => 0.8, 'fit' => 'contain'], CardShape::of_blocks([self::cards($picture)], self::no_patterns(...)));
    }

    public function testAShapeThatIsNoRatioKeepsThePicturesOwn(): void
    {
        self::assertNull(CardShape::of_blocks([self::cards(self::picture(['aspectRatio' => 'auto']))], self::no_patterns(...)));
    }

    public function testAnUnknownScaleFillsAsTheBlocksDefault(): void
    {
        $shape = CardShape::of_blocks([self::cards(self::picture(['aspectRatio' => '5/7', 'scale' => 'stretch']))], self::no_patterns(...));

        self::assertSame('cover', $shape['fit'] ?? null);
    }

    public function testAPictureOutsideACardIsNotTheCards(): void
    {
        $blocks = [self::picture(['aspectRatio' => '16/9']), self::cards(self::picture(['aspectRatio' => '2/3']))];

        self::assertEqualsWithDelta(2 / 3, CardShape::of_blocks($blocks, self::no_patterns(...))['aspect'] ?? null, 0.0001);
    }

    public function testTheFirstCardDecidesEvenWithoutAShape(): void
    {
        $blocks = [self::cards(self::picture([])), self::cards(self::picture(['aspectRatio' => '2/3']))];

        self::assertNull(CardShape::of_blocks($blocks, self::no_patterns(...)));
    }

    public function testReadsTheCardsInAPatternTheTemplateInserts(): void
    {
        $patterns = static fn (string $slug): array => self::PATTERN === $slug ? [self::cards(self::picture(['aspectRatio' => '4/5']))] : [];

        self::assertSame(
            ['aspect' => 0.8, 'fit' => 'cover'],
            CardShape::of_blocks([self::block('core/group', [], [self::block('core/pattern', ['slug' => self::PATTERN])])], $patterns)
        );
    }

    public function testAPatternThatInsertsItselfEnds(): void
    {
        $patterns = static fn (string $slug): array => [self::block('core/pattern', ['slug' => $slug])];

        self::assertNull(CardShape::of_blocks([self::block('core/pattern', ['slug' => self::PATTERN])], $patterns));
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function no_patterns(string $slug): array
    {
        self::fail("No pattern is inserted, yet {$slug} was read.");
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private static function picture(array $attributes): array
    {
        return self::block('woocommerce/product-image', $attributes);
    }

    /**
     * @param array<string, mixed> $picture
     * @return array<string, mixed>
     */
    private static function cards(array $picture): array
    {
        return self::block('woocommerce/product-collection', [], [
            self::block('woocommerce/product-template', [], [$picture, self::block('core/post-title', [])]),
        ]);
    }

    /**
     * @param array<string, mixed>       $attributes
     * @param list<array<string, mixed>> $inner
     * @return array<string, mixed>
     */
    private static function block(string $name, array $attributes, array $inner = []): array
    {
        return ['blockName' => $name, 'attrs' => $attributes, 'innerBlocks' => $inner, 'innerHTML' => '', 'innerContent' => []];
    }
}
