<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Product;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Pixonaut\Shop\Product\SecondPicture;
use Pixonaut\Shop\Tests\TestCase;

/**
 * Which of a product's gallery pictures its card shows second.
 */
#[CoversClass(SecondPicture::class)]
final class SecondPictureTest extends TestCase
{
    private const STUDIO = 11;

    private const DESIGN = 12;

    private const ROOM = 13;

    public function testFiltersWooCommercesProductImageBlock(): void
    {
        (new SecondPicture())->register();

        self::assertNotFalse(has_filter('render_block_woocommerce/product-image', 'Pixonaut\Shop\Product\SecondPicture->render()'));
    }

    /**
     * @param array<array-key, mixed> $gallery
     */
    #[DataProvider('galleries')]
    public function testPicksTheGalleryPictureAfterTheCards(int $expected, int $picture, array $gallery): void
    {
        self::assertSame($expected, SecondPicture::next($picture, $gallery));
    }

    /**
     * @return iterable<string, array{int, int, array<array-key, mixed>}>
     */
    public static function galleries(): iterable
    {
        yield 'the gallery\'s first picture' => [self::DESIGN, self::STUDIO, [self::DESIGN, self::ROOM]];
        yield 'as WooCommerce stores the IDs, in text' => [self::DESIGN, self::STUDIO, ['12', '13']];
        yield 'the one after the card\'s, when the card shows a gallery picture' => [self::ROOM, self::DESIGN, [self::STUDIO, self::DESIGN, self::ROOM]];
        yield 'round to the first, after the gallery\'s last' => [self::STUDIO, self::ROOM, [self::STUDIO, self::DESIGN, self::ROOM]];
        yield 'never the card\'s own picture again' => [self::ROOM, self::STUDIO, [self::STUDIO, self::STUDIO, self::ROOM]];
        yield 'none without a gallery' => [0, self::STUDIO, []];
        yield 'none when the gallery holds the card\'s picture alone' => [0, self::STUDIO, [self::STUDIO]];
        yield 'none for a card without a picture' => [0, 0, [self::DESIGN]];
    }

    public function testLeavesABlockOutsideAProductLoopAlone(): void
    {
        $image = '<div class="wc-block-components-product-image"><img src="studio.png" alt="Poster"></div>';

        self::assertSame($image, (new SecondPicture())->render($image, ['attrs' => []]));
        self::assertSame($image, (new SecondPicture())->render($image, ['attrs' => []], (object) ['context' => ['postId' => 5]]));
        self::assertSame($image, (new SecondPicture())->render($image, ['attrs' => []], (object) ['context' => ['postId' => 5, 'queryId' => 0, 'imageId' => self::ROOM]]));
        self::assertSame('', (new SecondPicture())->render('', ['attrs' => []], (object) ['context' => ['postId' => 5, 'queryId' => 0]]));
    }
}
