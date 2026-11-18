<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Pixonaut\Shop\Assets\AssetManager;
use Pixonaut\Shop\Product\CardShape;

/**
 * The shape a store picks for its product cards' pictures, read from the
 * shop's template as the Site Editor saved it, and printed for every other
 * product picture to read.
 */
final class CardShapeTest extends TestCase
{
    private const TEMPLATE = 'archive-product';

    /** The shop's cards with a shape picked on their picture, as the Site Editor saves them. */
    private const SHAPED_CARDS = '<!-- wp:woocommerce/product-collection {"queryId":0,"query":{"inherit":true}} -->'
        . '<div class="wp-block-woocommerce-product-collection">'
        . '<!-- wp:woocommerce/product-template -->'
        . '<!-- wp:woocommerce/product-image {"aspectRatio":"3/4","scale":"contain","isDescendentOfQueryLoop":true} /-->'
        . '<!-- /wp:woocommerce/product-template -->'
        . '</div><!-- /wp:woocommerce/product-collection -->';

    /** WooCommerce's own size for the gallery's thumbnails, cropped square. */
    private const WOOCOMMERCE_GALLERY_THUMBNAIL = [100, 100];

    private int $template = 0;

    private int $synced_pattern = 0;

    protected function tearDown(): void
    {
        foreach ([$this->template, $this->synced_pattern] as $post) {
            if ($post > 0) {
                wp_delete_post($post, true);
            }
        }

        wp_dequeue_style(AssetManager::HANDLE);
        wp_deregister_style(AssetManager::HANDLE);
        parent::tearDown();
    }

    public function testTheThemesCardsKeepEachPicturesOwnShape(): void
    {
        $shape = new CardShape();

        self::assertNull($shape->shape());
        self::assertNotContains(CardShape::BODY_CLASS, $shape->body_class([]));

        (new AssetManager())->enqueue_frontend();
        $shape->print_properties();
        self::assertFalse(wp_styles()->get_data(AssetManager::HANDLE, 'after'));
    }

    public function testTheShapeTheStorePickedInTheSiteEditorIsPrintedForEveryPicture(): void
    {
        $this->save_template(self::SHAPED_CARDS);
        $shape = new CardShape();

        self::assertSame(['aspect' => 0.75, 'fit' => 'contain'], $shape->shape());
        self::assertContains(CardShape::BODY_CLASS, $shape->body_class([]));

        (new AssetManager())->enqueue_frontend();
        $shape->print_properties();
        self::assertSame(
            [':root{--pixonaut-shop-card-aspect:0.75;--pixonaut-shop-card-fit:contain}'],
            wp_styles()->get_data(AssetManager::HANDLE, 'after')
        );
    }

    public function testReadsTheCardsInASyncedPatternTheShopInserts(): void
    {
        $this->synced_pattern = (int) wp_insert_post([
            'post_type'    => 'wp_block',
            'post_title'   => 'Shop grid',
            'post_status'  => 'publish',
            'post_content' => self::SHAPED_CARDS,
        ]);
        $this->save_template('<!-- wp:block {"ref":' . $this->synced_pattern . '} /-->');

        self::assertSame(['aspect' => 0.75, 'fit' => 'contain'], (new CardShape())->shape());
    }

    public function testTheGallerysThumbnailsKeepTheirPicturesShape(): void
    {
        self::assertSame(CardShape::THUMBNAIL_SIZE, apply_filters('woocommerce_gallery_thumbnail_size', self::WOOCOMMERCE_GALLERY_THUMBNAIL));
    }

    private function save_template(string $content): void
    {
        $this->template = (int) wp_insert_post([
            'post_type'    => 'wp_template',
            'post_name'    => self::TEMPLATE,
            'post_title'   => 'Product Catalog',
            'post_status'  => 'publish',
            'post_content' => $content,
        ]);
        wp_set_object_terms($this->template, get_stylesheet(), 'wp_theme');
    }
}
