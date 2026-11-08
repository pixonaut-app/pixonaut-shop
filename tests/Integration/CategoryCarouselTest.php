<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * The category carousel block against categories made here: a scrolling
 * row of round category pictures, the children of the category chosen for
 * it or the top-level categories, with arrow buttons its script shows, and
 * nothing at all without a category that has products.
 */
final class CategoryCarouselTest extends TestCase
{
    use MakesCategories;

    private const BLOCK = 'pixonaut-shop/category-carousel';

    private const BLOCK_CLASS = 'wp-block-pixonaut-shop-category-carousel';

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

    public function testShowsTheTopLevelCategoriesWithProductsWithoutAChosenCategory(): void
    {
        $mugs = $this->category('Mugs', 1);
        $this->category('Posters', 2);
        $this->category('Empty shelf', 0);
        $this->category('Travel mugs', 1, $mugs);

        $html = $this->render_block(self::BLOCK);

        self::assertMatchesRegularExpression('/\A<div class="[^"]*\b' . self::BLOCK_CLASS . '\b/', $html);
        self::assertMatchesRegularExpression('/>Mugs<.*>Posters</s', $html);
        self::assertStringContainsString('href="' . esc_url((string) get_term_link($mugs)) . '"', $html);
        self::assertStringNotContainsString('Empty shelf', $html);
        self::assertStringNotContainsString('Travel mugs', $html);
        self::assertStringNotContainsString('<h2', $html, 'No heading was chosen.');
    }

    public function testShowsTheChosenCategorysChildrenUnderTheHeadingChosen(): void
    {
        $for_whom = $this->category('For whom', 0);
        $this->category('For her', 1, $for_whom);
        $this->category('For him', 1, $for_whom);
        $this->category('Mugs', 1);

        $html = $this->render_block(self::BLOCK, ['parent' => 'for-whom', 'heading' => 'Find a gift']);

        self::assertMatchesRegularExpression('/<h2 class="[^"]*__heading[^"]*">Find a gift<\/h2>/', $html);
        self::assertMatchesRegularExpression('/>For her<.*>For him</s', $html);
        self::assertStringNotContainsString('Mugs', $html);
    }

    public function testTheArrowsAreHiddenButtonsWithANameForTheRow(): void
    {
        $this->category('Mugs', 1);

        $html = $this->render_block(self::BLOCK);

        self::assertSame(1, preg_match('/<ul class="' . self::BLOCK_CLASS . '__list" id="([^"]+)"/', $html, $list));
        foreach (['Previous categories', 'Next categories'] as $name) {
            self::assertMatchesRegularExpression(
                '/<button type="button" class="[^"]*__arrow[^"]*" aria-controls="' . preg_quote($list[1], '/')
                . '" aria-label="' . $name . '" hidden><svg\b[^>]*aria-hidden="true"/',
                $html
            );
        }
    }

    public function testEachRenderHasItsOwnRowId(): void
    {
        $this->category('Mugs', 1);

        preg_match_all('/__list" id="([^"]+)"/', $this->render_block(self::BLOCK) . $this->render_block(self::BLOCK), $ids);

        self::assertCount(2, array_unique($ids[1]));
    }

    public function testShowsEachPictureLazilyAndSized(): void
    {
        $mugs = $this->category('Mugs', 1);
        $this->category('Posters', 1);
        $this->picture($mugs);

        $html = $this->render_block(self::BLOCK);

        self::assertSame(1, preg_match_all('/<img\b/', $html));
        self::assertMatchesRegularExpression('/__picture"><img\b[^>]*\bloading="lazy"[^>]*><\/span><span[^>]*>Mugs</', $html);
        self::assertMatchesRegularExpression('/<img\b[^>]*\bsizes="[^"]+"/', $html);
        self::assertStringContainsString('__picture"></span><span class="' . self::BLOCK_CLASS . '__name">Posters<', $html);
    }

    public function testLoadsThePicturesTogether(): void
    {
        foreach (['Mugs', 'Posters', 'Prints'] as $name) {
            $this->picture($this->category($name, 1));
        }
        wp_cache_flush();

        $queries = [];
        $record  = static function (string $query) use (&$queries): string {
            $queries[] = $query;

            return $query;
        };
        add_filter('query', $record);
        $this->render_block(self::BLOCK);
        remove_filter('query', $record);

        $single = preg_grep('/\bID = \d+\b.*LIMIT 1/s', $queries);
        self::assertSame([], array_values(false === $single ? [] : $single), 'A picture is loaded on its own.');
    }

    public function testRendersNothingWithoutACategoryThatHasProducts(): void
    {
        $occasion = $this->category('Occasion', 0);
        $this->category('Christening', 0, $occasion);

        self::assertSame('', trim($this->render_block(self::BLOCK)));
        self::assertSame('', trim($this->render_block(self::BLOCK, ['parent' => 'occasion', 'heading' => 'Occasions'])));
        self::assertSame('', trim($this->render_block(self::BLOCK, ['parent' => 'no-such-category'])));
    }
}
