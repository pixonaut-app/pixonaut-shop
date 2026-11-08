<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * The product categories block against categories made here: the store's
 * top-level categories with products, as tiles or as buttons, and nothing
 * at all when it has none.
 */
final class ProductCategoriesTest extends TestCase
{
    use MakesCategories;

    private const BLOCK = 'pixonaut-shop/product-categories';

    /** The term meta WooCommerce keeps a category's place in the store's own order in. */
    private const ORDER_META = 'order';

    protected function setUp(): void
    {
        parent::setUp();
        $this->start_making_categories();
    }

    protected function tearDown(): void
    {
        $this->stop_making_categories();
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
        $this->picture($mugs);

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
     * @param array<string, mixed> $attributes
     */
    private function render(array $attributes = []): string
    {
        return $this->render_block(self::BLOCK, $attributes);
    }
}
