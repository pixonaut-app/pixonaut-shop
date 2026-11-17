<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Pixonaut\Shop\Archive\CardSwatches;
use WC_Product;
use WC_Product_Simple;
use WP_Block_Patterns_Registry;
use WP_Query;

/**
 * The swatches on a card of a design the archive shows in several chosen
 * values, as Pixonaut for WooCommerce names them for the card being drawn.
 */
final class CardSwatchesTest extends TestCase
{
    private const PATTERN = 'pixonaut-shop/product-grid';

    private const PICTURE = ['src' => 'https://example.test/hanger.jpg', 'srcset' => '', 'width' => 600, 'height' => 800];

    /** @var list<int> */
    private array $product_ids = [];

    protected function tearDown(): void
    {
        remove_all_filters(CardSwatches::FILTER);

        foreach ($this->product_ids as $id) {
            wp_delete_post($id, true);
        }

        $GLOBALS['wp_query']->init();
        wp_reset_postdata();
        parent::tearDown();
    }

    public function testACardShownInSeveralValuesHasASwatchForEachTheShownOnePressed(): void
    {
        $id = $this->product('Swatched print');
        add_filter(CardSwatches::FILTER, static fn (array $swatches, WC_Product $product): array => $product->get_id() === $id ? [
            ['values' => [['key' => 'framing', 'id' => 'hanger', 'label' => 'Hanger']], 'url' => 'https://example.test/print/?framing=hanger', 'image' => self::PICTURE, 'current' => true],
            ['values' => [['key' => 'framing', 'id' => 'frame', 'label' => 'Frame'], ['key' => 'colour', 'id' => 'oak', 'label' => 'Oak & ash']], 'url' => 'https://example.test/print/?framing=frame', 'image' => ['src' => 'https://example.test/frame.jpg', 'alt' => 'Framed print'] + self::PICTURE, 'title' => 'Swatched print, framed'],
        ] : $swatches, 10, 2);

        $grid = $this->render();

        self::assertMatchesRegularExpression('/<div class="' . CardSwatches::GROUP_CLASS . '" role="group" aria-label="Versions of Swatched print">/', $grid);
        self::assertMatchesRegularExpression('/<button type="button" class="' . CardSwatches::SWATCH_CLASS . '" aria-pressed="true" data-pixonaut-shop-link="https:\/\/example.test\/print\/\?framing=hanger"[^>]*>Hanger<\/button>/', $grid);
        self::assertMatchesRegularExpression('/aria-pressed="false"[^>]*data-pixonaut-shop-picture="[^"]*frame\.jpg[^"]*Framed print[^"]*" data-pixonaut-shop-title="Swatched print, framed">Frame \/ Oak &amp; ash<\/button>/', $grid);
        self::assertMatchesRegularExpression('/wp-block-woocommerce-product-image.*<\/div><div class="' . CardSwatches::GROUP_CLASS . '"/s', $grid, 'After the card\'s picture.');
    }

    public function testACardShownOnceHasNone(): void
    {
        $this->product('Single print');
        add_filter(CardSwatches::FILTER, static fn (): array => [
            ['values' => [['key' => 'framing', 'id' => 'hanger', 'label' => 'Hanger']], 'url' => 'https://example.test/', 'image' => self::PICTURE, 'current' => true],
        ]);

        self::assertStringNotContainsString(CardSwatches::GROUP_CLASS, $this->render());
    }

    private function product(string $name): int
    {
        $product = new WC_Product_Simple();
        $product->set_name($name);
        $product->set_regular_price('300');
        $product->set_status('publish');
        $id                  = $product->save();
        $this->product_ids[] = $id;

        return $id;
    }

    private function render(): string
    {
        $main_query = $GLOBALS['wp_query'];
        self::assertInstanceOf(WP_Query::class, $main_query);
        $main_query->query(['post_type' => 'product', 'post__in' => $this->product_ids]);

        $pattern = WP_Block_Patterns_Registry::get_instance()->get_registered(self::PATTERN);
        self::assertIsArray($pattern);

        return do_blocks($pattern['content']);
    }
}
