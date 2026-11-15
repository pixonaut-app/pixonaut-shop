<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Pixonaut\Shop\Product\SecondPicture;
use WC_Product_Simple;
use WP_Block_Patterns_Registry;
use WP_Query;

/**
 * A product card's second picture, in the grid as WooCommerce renders it:
 * the product's next gallery picture, after the first inside the card's
 * picture, lazy and sized.
 */
final class SecondPictureTest extends TestCase
{
    private const PATTERN = 'pixonaut-shop/product-grid';

    private const PICTURE_WIDTH = 800;

    private const PICTURE_HEIGHT = 1000;

    /** @var list<int> */
    private array $product_ids = [];

    /** @var list<int> */
    private array $attachments = [];

    protected function tearDown(): void
    {
        remove_all_filters('woocommerce_product_get_image_id');

        foreach ($this->product_ids as $id) {
            wp_delete_post($id, true);
        }

        foreach ($this->attachments as $id) {
            wp_delete_attachment($id, true);
        }

        // The main query is the one object a request has; empty it again.
        $GLOBALS['wp_query']->init();
        wp_reset_postdata();
        parent::tearDown();
    }

    public function testPrintsTheNextGalleryPictureAfterTheCardsFirst(): void
    {
        $studio = $this->picture('studio');
        $design = $this->picture('design');
        $this->product('Poster', $studio, [$design, $this->picture('room')]);

        $grid = $this->render();

        self::assertMatchesRegularExpression(
            '/<a\b[^>]*>\s*<img\b[^>]*data-image-id="' . $studio . '"[^>]*><img\b[^>]*class="' . SecondPicture::CLASS_NAME . '"[^>]*>/',
            $grid,
            'The second picture does not follow the first inside the card\'s link.'
        );

        $second = $this->second_picture($grid);
        self::assertStringContainsString('design.png', $second);
        self::assertStringContainsString('loading="lazy"', $second);
        self::assertStringContainsString('alt=""', $second);
        self::assertStringContainsString('width="' . self::PICTURE_WIDTH . '"', $second);
        self::assertStringContainsString('height="' . self::PICTURE_HEIGHT . '"', $second);
        self::assertSame(1, substr_count($grid, SecondPicture::CLASS_NAME));
    }

    public function testLeavesACardWithOnePictureAsItIs(): void
    {
        $this->product('Poster', $this->picture('studio'), []);

        self::assertStringNotContainsString(SecondPicture::CLASS_NAME, $this->render());
    }

    public function testLeavesACardWithoutAPictureAsItIs(): void
    {
        $this->product('Poster', 0, [$this->picture('design')]);

        self::assertStringNotContainsString(SecondPicture::CLASS_NAME, $this->render());
    }

    public function testPassesOverAGalleryFileThatIsNoPicture(): void
    {
        $this->product('Poster', $this->picture('studio'), [$this->picture('terms', 'application/pdf', 'pdf')]);

        self::assertStringNotContainsString(SecondPicture::CLASS_NAME, $this->render());
    }

    public function testScalesTheSecondPictureAsTheStorePickedForTheCards(): void
    {
        $this->product('Poster', $this->picture('studio'), [$this->picture('design')]);

        self::assertStringNotContainsString('object-fit', $this->second_picture($this->render()));
        self::assertStringContainsString(
            'style="object-fit:contain;"',
            $this->second_picture($this->render('{"showSaleBadge":false,"isDescendentOfQueryLoop":true,"scale":"contain"}'))
        );
    }

    /**
     * Pixonaut for WooCommerce lists a product once per value of an
     * attribute, and answers each card's own picture as the product's while
     * the card is shown.
     */
    public function testFollowsTheCardsOwnPictureWhereAPluginAnswersOne(): void
    {
        $studio = $this->picture('studio');
        $framed = $this->picture('framed');
        $room   = $this->picture('room');
        $id     = $this->product('Poster', $studio, [$studio, $framed, $room]);
        add_filter(
            'woocommerce_product_get_image_id',
            static fn (mixed $image, mixed $product): mixed => $product->get_id() === $id ? $framed : $image,
            10,
            2
        );

        $grid = $this->render();

        self::assertStringContainsString('data-image-id="' . $framed . '"', $grid);
        self::assertStringContainsString('room.png', $this->second_picture($grid));
    }

    /**
     * A published product with the picture and gallery given.
     *
     * @param list<int> $gallery
     */
    private function product(string $name, int $picture, array $gallery): int
    {
        $product = new WC_Product_Simple();
        $product->set_name($name);
        $product->set_regular_price('300');
        $product->set_image_id($picture);
        $product->set_gallery_image_ids($gallery);
        $product->set_status('publish');
        $id                  = $product->save();
        $this->product_ids[] = $id;

        return $id;
    }

    /** A file in the media library, with a picture's size; no file is written. */
    private function picture(string $name, string $type = 'image/png', string $extension = 'png'): int
    {
        $file = "2026/11/{$name}.{$extension}";
        $id   = wp_insert_attachment(['post_mime_type' => $type, 'post_title' => $name, 'post_status' => 'inherit'], $file, 0, true);
        self::assertIsInt($id);
        wp_update_attachment_metadata($id, ['width' => self::PICTURE_WIDTH, 'height' => self::PICTURE_HEIGHT, 'file' => $file]);
        $this->attachments[] = $id;

        return $id;
    }

    /** The second picture's tag in the grid. */
    private function second_picture(string $grid): string
    {
        self::assertSame(
            1,
            preg_match('/<img\b[^>]*class="' . SecondPicture::CLASS_NAME . '"[^>]*>/', $grid, $picture),
            'The grid has no second picture.'
        );

        return $picture[0];
    }

    /**
     * The pattern inherits the query of the template it is in, as on the
     * shop page: here, a query for the products this test made.
     *
     * @param string|null $image_attributes The Product Image block's attributes, as a store saves them, in place of the pattern's.
     */
    private function render(?string $image_attributes = null): string
    {
        $main_query = $GLOBALS['wp_query'];
        self::assertInstanceOf(WP_Query::class, $main_query);
        $main_query->query([
            'post_type' => 'product',
            'post__in'  => $this->product_ids,
        ]);

        $pattern = WP_Block_Patterns_Registry::get_instance()->get_registered(self::PATTERN);
        self::assertIsArray($pattern, self::PATTERN . ' is not registered.');
        $content = $pattern['content'];

        if (null !== $image_attributes) {
            $content = (string) preg_replace('/(<!-- wp:woocommerce\/product-image )\{[^}]*\}/', '$1' . $image_attributes, $content, 1, $replaced);
            self::assertSame(1, $replaced);
        }

        return do_blocks($content);
    }
}
