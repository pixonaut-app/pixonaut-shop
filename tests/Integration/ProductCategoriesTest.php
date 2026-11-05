<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use PHPUnit\Framework\TestCase;
use WC_Product_Simple;

/**
 * The product categories block against categories made here: the store's
 * top-level categories with products, as tiles or as buttons, and nothing
 * at all when it has none.
 */
final class ProductCategoriesTest extends TestCase
{
    private const BLOCK = 'pixonaut-shop/product-categories';

    private const TAXONOMY = 'product_cat';

    /** The term meta WooCommerce keeps a category's place in the store's own order in. */
    private const ORDER_META = 'order';

    private const THUMBNAIL_META = 'thumbnail_id';

    /** @var list<int> */
    private array $term_ids = [];

    /** @var list<int> */
    private array $post_ids = [];

    /**
     * Leaves out of the block's query the categories that were there before
     * the test, such as the shop fixtures other suites add, so each test
     * sees only the categories it makes.
     *
     * @var callable(array<string, mixed>, list<string>): array<string, mixed>
     */
    private $only_new_categories;

    protected function setUp(): void
    {
        parent::setUp();

        $existing = get_terms(['taxonomy' => self::TAXONOMY, 'hide_empty' => false, 'fields' => 'ids']);
        self::assertIsArray($existing);

        $this->only_new_categories = static function (array $args, array $taxonomies) use ($existing): array {
            if ([self::TAXONOMY] === $taxonomies) {
                $args['exclude'] = array_merge((array) ($args['exclude'] ?? []), $existing);
            }

            return $args;
        };
        add_filter('get_terms_args', $this->only_new_categories, 10, 2);
    }

    protected function tearDown(): void
    {
        remove_filter('get_terms_args', $this->only_new_categories);

        foreach ($this->post_ids as $id) {
            wp_delete_post($id, true);
        }

        foreach ($this->term_ids as $id) {
            wp_delete_term($id, self::TAXONOMY);
        }

        parent::tearDown();
    }

    public function testShowsTheTopLevelCategoriesWithProductsAsTilesMostProductsFirst(): void
    {
        $posters = $this->category('Posters', 1);
        $mugs    = $this->category('Mugs', 2);
        $this->category('Empty shelf', 0);
        $this->category('Travel mugs', 1, $mugs);

        $tiles = $this->render();

        self::assertMatchesRegularExpression('/\A<div class="[^"]*\bis-tiles\b/', $tiles);
        // WooCommerce counts a subcategory's products in its parent's too.
        self::assertMatchesRegularExpression('/Mugs<.*3 products<.*Posters<.*1 product</s', $tiles);
        self::assertStringContainsString((string) get_term_link($posters), $tiles);
        self::assertStringNotContainsString('Empty shelf', $tiles);
        self::assertStringNotContainsString('Travel mugs', $tiles);
    }

    public function testShowsATopLevelCategoryWhoseProductsAreAllInASubcategory(): void
    {
        $prints = $this->category('Prints', 0);
        $this->category('Framed prints', 2, $prints);

        self::assertMatchesRegularExpression('/Prints<.*2 products</s', $this->render());
    }

    public function testOrdersByTheCountShownIncludingSubcategories(): void
    {
        $mugs = $this->category('Mugs', 1);
        $this->category('Travel mugs', 2, $mugs);
        $this->category('Posters', 2);

        self::assertMatchesRegularExpression('/Mugs<.*3 products<.*Posters<.*2 products</s', $this->render());
    }

    public function testLeavesOutACategoryWhoseProductsAreHiddenFromTheCatalogue(): void
    {
        $this->category('Mugs', 1);
        $this->category('Secret', 1, 0, 'hidden');

        $tiles = $this->render();

        self::assertStringContainsString('Mugs', $tiles);
        self::assertStringNotContainsString('Secret', $tiles);
    }

    public function testShowsTheCategorysImageOnItsTileAndTheGroundWithoutOne(): void
    {
        $mugs = $this->category('Mugs', 1);
        $this->category('Posters', 1);
        update_term_meta($mugs, self::THUMBNAIL_META, $this->image());

        $tiles = $this->render();

        self::assertSame(1, preg_match_all('/<img\b/', $tiles));
        self::assertMatchesRegularExpression('/__picture"><img\b[^>]*><\/span><span[^>]*>Mugs</', $tiles);
        self::assertStringContainsString('__picture"></span><span class="wp-block-pixonaut-shop-product-categories__name">Posters<', $tiles);
    }

    public function testFollowsTheStoresOwnCategoryOrderWhenChosen(): void
    {
        $mugs    = $this->category('Mugs', 2);
        $posters = $this->category('Posters', 1);
        update_term_meta($mugs, self::ORDER_META, 2);
        update_term_meta($posters, self::ORDER_META, 1);

        self::assertMatchesRegularExpression('/Posters<.*Mugs</s', $this->render(['orderBy' => 'order']));
    }

    public function testShowsNoMoreThanTheNumberChosen(): void
    {
        $this->category('Mugs', 2);
        $this->category('Posters', 1);

        $tiles = $this->render(['number' => 1]);

        self::assertStringContainsString('Mugs', $tiles);
        self::assertStringNotContainsString('Posters', $tiles);
    }

    public function testShowsTheCategoriesAsOutlineButtons(): void
    {
        $mugs = $this->category('Mugs', 2);
        $this->category('Empty shelf', 0);

        $buttons = $this->render(['layout' => 'buttons']);

        self::assertMatchesRegularExpression('/\A<div class="[^"]*\bis-buttons\b/', $buttons);
        self::assertMatchesRegularExpression(
            '/<div class="wp-block-button is-style-outline[^"]*"><a class="wp-block-button__link wp-element-button" href="'
            . preg_quote((string) get_term_link($mugs), '/') . '">Mugs<\/a>/',
            $buttons
        );
        self::assertStringNotContainsString('products', $buttons);
        self::assertStringNotContainsString('Empty shelf', $buttons);
    }

    public function testRendersNothingWithoutACategoryThatHasProducts(): void
    {
        $this->category('Empty shelf', 0);

        self::assertSame('', trim($this->render()));
        self::assertSame('', trim($this->render(['layout' => 'buttons'])));
    }

    /**
     * A category with this many published products of its own, shown in the
     * catalogue as `$visibility` says.
     */
    private function category(string $name, int $products, int $parent_id = 0, string $visibility = 'visible'): int
    {
        $term = wp_insert_term($name, self::TAXONOMY, ['parent' => $parent_id]);
        self::assertIsArray($term);
        $this->term_ids[] = $term['term_id'];

        for ($i = 0; $i < $products; ++$i) {
            $product = new WC_Product_Simple();
            $product->set_name("{$name} product {$i}");
            $product->set_status('publish');
            $product->set_category_ids([$term['term_id']]);
            $product->set_catalog_visibility($visibility);
            $this->post_ids[] = $product->save();
        }

        return $term['term_id'];
    }

    /** An image in the media library; its file is never read for the markup. */
    private function image(): int
    {
        $id = wp_insert_attachment(['post_mime_type' => 'image/png', 'post_title' => 'Mugs'], 'mugs.png');
        self::assertIsInt($id);
        $this->post_ids[] = $id;

        return $id;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function render(array $attributes = []): string
    {
        $comment_attributes = [] === $attributes ? '' : wp_json_encode($attributes) . ' ';

        return do_blocks('<!-- wp:' . self::BLOCK . ' ' . $comment_attributes . '/-->');
    }
}
