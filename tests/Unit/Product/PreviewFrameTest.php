<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Product;

use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use Pixonaut\Shop\Blocks\AspectRatios;
use Pixonaut\Shop\Product\PreviewFrame;
use Pixonaut\Shop\Tests\TestCase;

#[CoversClass(PreviewFrame::class)]
final class PreviewFrameTest extends TestCase
{
    private const GALLERY = 'woocommerce/product-image-gallery';

    /** What the gallery block renders, or the plugin's preview in its place. */
    private const PREVIEW = '<div class="pixonaut-preview">Preview</div>';

    protected function setUp(): void
    {
        parent::setUp();
        Functions\stubTranslationFunctions();
        Functions\stubEscapeFunctions();
    }

    public function testRegistersItsStylesAndWrapsTheGalleryAfterThePlugin(): void
    {
        $this->frame()->register();

        self::assertNotFalse(has_action('init', 'Pixonaut\Shop\Product\PreviewFrame->register_styles()'));
        self::assertSame(
            20,
            has_filter('render_block_' . self::GALLERY, 'Pixonaut\Shop\Product\PreviewFrame->render()')
        );
    }

    public function testOffersAFrameStyleOnTheGalleryForEveryShape(): void
    {
        $registered = [];
        Functions\when('register_block_style')->alias(
            static function (string $block, array $style) use (&$registered): bool {
                $registered[$block][] = $style;

                return true;
            }
        );

        $this->frame()->register_styles();

        $presets = (new AspectRatios())->presets();
        self::assertSame([self::GALLERY], array_keys($registered));
        self::assertSame(
            array_map(static fn (array $preset): string => 'frame-' . $preset['slug'], $presets),
            array_column($registered[self::GALLERY], 'name')
        );
        self::assertSame(array_column($presets, 'name'), array_column($registered[self::GALLERY], 'label'));
    }

    public function testAFrameStyleShowsItsShapeOnTheGalleryInTheEditorToo(): void
    {
        $registered = [];
        Functions\when('register_block_style')->alias(
            static function (string $block, array $style) use (&$registered): bool {
                $registered[$style['name']] = $style['inline_style'] ?? null;

                return true;
            }
        );

        $this->frame()->register_styles();

        self::assertSame(
            '.wp-block-woocommerce-product-image-gallery.is-style-frame-5-7{--ps-frame-aspect:0.7143}',
            $registered['frame-5-7']
        );
    }

    public function testLeavesTheGalleryAloneWithoutAFrameStyle(): void
    {
        $frame = $this->frame();

        self::assertSame(self::PREVIEW, $frame->render(self::PREVIEW, $this->gallery(null)));
        self::assertSame(self::PREVIEW, $frame->render(self::PREVIEW, $this->gallery('is-style-default extra')));
        self::assertSame(self::PREVIEW, $frame->render(self::PREVIEW, $this->gallery('is-style-frame-unknown')));
    }

    public function testHoldsTheFrameAtTheChosenShape(): void
    {
        self::assertSame(
            '<div class="pixonaut-shop-product-frame" style="--ps-frame-aspect: 0.7143">' . self::PREVIEW . '</div>',
            $this->frame()->render(self::PREVIEW, $this->gallery('extra is-style-frame-5-7'))
        );
    }

    /**
     * The plugin prints each side's opening shape on that side's frame; the
     * first side's holds every side, so switching side never resizes it.
     */
    public function testHoldsEverySideAtTheFirstSidesOpeningShapeWithoutAFrameStyle(): void
    {
        $two_sides = '<div class="pixonaut-preview">'
            . '<div class="pixonaut-preview__frame" style="--pixonaut-preview-ratio: 3 / 2; --pixonaut-preview-aspect: 1.5"></div>'
            . '<div class="pixonaut-preview__frame" style="--pixonaut-preview-ratio: 5 / 7; --pixonaut-preview-aspect: 0.714286"></div>'
            . '</div>';

        self::assertSame(
            '<div class="pixonaut-shop-product-frame" style="--ps-frame-aspect: 1.5">' . $two_sides . '</div>',
            $this->frame()->render($two_sides, $this->gallery(null))
        );
    }

    public function testTheStoresShapeWinsOverTheOpeningShape(): void
    {
        $side = '<div class="pixonaut-preview__frame" style="--pixonaut-preview-aspect: 1.5"></div>';

        self::assertStringStartsWith(
            '<div class="pixonaut-shop-product-frame" style="--ps-frame-aspect: 1">',
            $this->frame()->render($side, $this->gallery('is-style-frame-square'))
        );
    }

    public function testLeavesAnEmptyGalleryEmpty(): void
    {
        self::assertSame('', $this->frame()->render('', $this->gallery('is-style-frame-square')));
    }

    private function frame(): PreviewFrame
    {
        return new PreviewFrame(new AspectRatios());
    }

    /**
     * @return array{blockName: string, attrs: array<string, string>}
     */
    private function gallery(?string $class_name): array
    {
        return [
            'blockName' => self::GALLERY,
            'attrs'     => null === $class_name ? [] : ['className' => $class_name],
        ];
    }
}
