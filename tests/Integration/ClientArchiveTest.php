<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Pixonaut\Shop\Archive\ArchiveIndexFields;
use Pixonaut\Shop\Archive\ClientArchive;
use Pixonaut\Shop\Product\LowestPrice;
use WC_Product_Simple;

/**
 * What the archive filtered in the browser draws with: the theme's fields on
 * each card and variation of the plugin's index, and the store's price
 * markup, words and panels beside the script.
 */
final class ClientArchiveTest extends TestCase
{
    private const REGULAR = '300';

    private const SALE = '200';

    private const PICTURE_WIDTH = 800;

    private const PICTURE_HEIGHT = 1000;

    /** @var list<int> */
    private array $posts = [];

    protected function tearDown(): void
    {
        foreach ($this->posts as $id) {
            wp_delete_post($id, true);
        }

        parent::tearDown();
    }

    public function testTheThemeAsksThePluginForItsArchivesIndexes(): void
    {
        self::assertTrue(current_theme_supports(ClientArchive::INDEX_SUPPORT));
    }

    public function testThePriceMarkupHasPlaceholdersForTheAmounts(): void
    {
        $prices = ClientArchive::config()['prices'];

        self::assertIsArray($prices);
        self::assertMatchesRegularExpression('/woocommerce-Price-amount.*\{\{a\}\}/s', $prices['amount']);
        self::assertMatchesRegularExpression('/<del\b.*\{\{a\}\}.*<ins\b.*\{\{b\}\}/s', $prices['sale'], 'The regular price struck, then the reduced one.');
        self::assertMatchesRegularExpression('/\{\{a\}\}.*\{\{b\}\}/s', $prices['range']);
        self::assertMatchesRegularExpression('/<span class="from">.*\{\{a\}\}/s', $prices['from']);
        self::assertMatchesRegularExpression('/' . LowestPrice::NOTE_CLASS . '.*\{\{a\}\}/s', $prices['lowest']);
        self::assertSame('from', $prices['varied']);
        self::assertStringNotContainsString('1.00', $prices['amount'], 'No amount is printed where its placeholder goes.');
    }

    public function testTheWordsCarryTheirNumbersInEachForm(): void
    {
        $config = ClientArchive::config();
        $text   = $config['text'];

        self::assertIsArray($text);
        self::assertSame(['one' => '%s product', 'other' => '%s products'], $text['count']);
        self::assertStringContainsString('%1$s', $text['seen']);
        self::assertStringContainsString('%2$s', $text['seen']);
        self::assertSame(ClientArchive::AUTO_LOADS, $config['autoLoads']);
        self::assertStringContainsString('pixonaut-shop-no-products', $config['noProducts']);
    }

    public function testAReducedVariationCarriesItsLowestPriceAndACardItsSecondPicture(): void
    {
        $fields  = new ArchiveIndexFields(new LowestPrice());
        $picture = $this->picture('first');
        $next    = $this->picture('next');
        $product = new WC_Product_Simple();
        $product->set_name('Index tee');
        $product->set_regular_price(self::REGULAR);
        $product->set_sale_price(self::SALE);
        $product->set_image_id($picture);
        $product->set_gallery_image_ids([$next]);
        $product->set_status('publish');
        $this->posts[] = $product->save();

        $row = $fields->variation(['price' => (float) self::SALE], $product);
        self::assertIsArray($row);
        self::assertSame((float) self::REGULAR, $row['lowestPrice'], 'First seen on sale: its regular price.');

        $card = $fields->card(['id' => '1'], $product);
        self::assertIsArray($card);
        self::assertSame(self::PICTURE_WIDTH, $card['secondImage']['width'] ?? null);
        self::assertStringContainsString('next', (string) ($card['secondImage']['src'] ?? ''));

        $product->set_sale_price('');
        $product->save();
        self::assertArrayNotHasKey('lowestPrice', (array) $fields->variation(['price' => (float) self::REGULAR], wc_get_product($product->get_id())));
    }

    /** A file in the media library, with a picture's size; no file is written. */
    private function picture(string $name): int
    {
        $file = "2026/11/{$name}.png";
        $id   = wp_insert_attachment(['post_mime_type' => 'image/png', 'post_title' => $name, 'post_status' => 'inherit'], $file, 0, true);
        self::assertIsInt($id);
        wp_update_attachment_metadata($id, ['width' => self::PICTURE_WIDTH, 'height' => self::PICTURE_HEIGHT, 'file' => $file]);
        $this->posts[] = $id;

        return $id;
    }
}
