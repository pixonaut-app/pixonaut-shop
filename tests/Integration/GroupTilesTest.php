<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * The group tiles block against categories made here: a heading, then the
 * children of the category chosen for it as picture tiles, and nothing at
 * all without a child that has products.
 */
final class GroupTilesTest extends TestCase
{
    use MakesCategories;

    private const BLOCK = 'pixonaut-shop/group-tiles';

    private const BLOCK_CLASS = 'wp-block-pixonaut-shop-group-tiles';

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

    public function testShowsTheChosenCategorysChildrenWithProductsUnderItsName(): void
    {
        $occasion = $this->category('Occasion', 0);
        $birthday = $this->category('Birthday', 2, $occasion);
        $wedding  = $this->category('Wedding', 1, $occasion);
        $this->category('Christening', 0, $occasion);
        $this->category('Mugs', 3);
        update_term_meta($birthday, self::ORDER_META, 2);
        update_term_meta($wedding, self::ORDER_META, 1);

        $html = $this->render_block(self::BLOCK, ['parent' => 'occasion']);

        self::assertMatchesRegularExpression('/\A<div class="[^"]*\b' . self::BLOCK_CLASS . '\b/', $html);
        self::assertMatchesRegularExpression('/<h2 class="[^"]*__heading[^"]*">Occasion<\/h2>/', $html);
        // The store's own order, not the product count.
        self::assertMatchesRegularExpression('/>Wedding<.*>Birthday</s', $html);
        self::assertStringContainsString('href="' . esc_url((string) get_term_link($birthday)) . '"', $html);
        self::assertStringNotContainsString('Christening', $html);
        self::assertStringNotContainsString('Mugs', $html);
        self::assertStringNotContainsString('products', $html, 'A tile shows no count.');
    }

    public function testShowsTheHeadingChosenForIt(): void
    {
        $occasion = $this->category('Occasion', 0);
        $this->category('Birthday', 1, $occasion);

        $html = $this->render_block(self::BLOCK, ['parent' => 'occasion', 'heading' => 'Gifts for <every> day']);

        self::assertStringContainsString('>Gifts for &lt;every&gt; day</h2>', $html);
        self::assertStringNotContainsString('>Occasion<', $html);
    }

    public function testEscapesACategorysName(): void
    {
        $occasion = $this->category('Occasion', 0);
        // WordPress strips tags from a term's name as it saves it; quotes stay.
        $this->category('Mum & "Dad"', 1, $occasion);

        $html = $this->render_block(self::BLOCK, ['parent' => 'occasion']);

        self::assertStringNotContainsString('"Dad"', $html);
        self::assertStringContainsString('Mum &amp; &quot;Dad&quot;', $html);
    }

    public function testShowsEachChildsPictureLazilyAndTheGroundWithoutOne(): void
    {
        $occasion = $this->category('Occasion', 0);
        $birthday = $this->category('Birthday', 1, $occasion);
        $this->category('Wedding', 1, $occasion);
        $this->picture($birthday);

        $html = $this->render_block(self::BLOCK, ['parent' => 'occasion']);

        self::assertSame(1, preg_match_all('/<img\b/', $html));
        self::assertMatchesRegularExpression('/__picture"><img\b[^>]*\bloading="lazy"[^>]*><\/span><span[^>]*>Birthday</', $html);
        self::assertMatchesRegularExpression('/<img\b[^>]*\bsizes="[^"]+"/', $html);
        self::assertMatchesRegularExpression('/<img\b[^>]*\balt=""/', $html);
        self::assertStringContainsString('__picture"></span><span class="' . self::BLOCK_CLASS . '__name">Wedding<', $html);
    }

    public function testRendersNothingWithoutAChildThatHasProducts(): void
    {
        $occasion = $this->category('Occasion', 4);
        $this->category('Christening', 0, $occasion);

        self::assertSame('', trim($this->render_block(self::BLOCK, ['parent' => 'occasion'])));
    }

    public function testRendersNothingWithoutAChosenCategoryOrForOneTheStoreHasNot(): void
    {
        $this->category('Mugs', 1);

        self::assertSame('', trim($this->render_block(self::BLOCK)));
        self::assertSame('', trim($this->render_block(self::BLOCK, ['parent' => 'no-such-category'])));
    }
}
