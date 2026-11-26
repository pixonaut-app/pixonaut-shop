<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Pixonaut\Shop\Product\ThemeProducts;
use WC_Product_Simple;
use WP_Query;

/**
 * The product page's "More in the theme" section, as the single-product
 * template renders it: other products with the product's first tag, in any
 * category.
 *
 * The related products below it are left to `product-page.spec.ts`:
 * WooCommerce reads the product a related collection is for once a request,
 * so in this suite's one process it stays the first product any test
 * rendered.
 */
final class ThemeProductsTest extends TestCase
{
    private const CATEGORY_TAXONOMY = 'product_cat';

    /** @var list<int> */
    private array $product_ids = [];

    /** @var list<array{int, string}> */
    private array $terms = [];

    protected function tearDown(): void
    {
        foreach ($this->product_ids as $id) {
            wp_delete_post($id, true);
        }

        foreach ($this->terms as [$id, $taxonomy]) {
            wp_delete_term($id, $taxonomy);
        }

        $GLOBALS['wp_query']->init();
        wp_reset_postdata();
        unset($GLOBALS['product']);
        parent::tearDown();
    }

    public function testShowsTheOtherProductsWithTheTagInAnyCategory(): void
    {
        $sloths = $this->term('Theme sloths', ThemeProducts::TAXONOMY);
        $siblings = $this->term('Theme siblings', self::CATEGORY_TAXONOMY);
        $parents = $this->term('Theme parents', self::CATEGORY_TAXONOMY);
        $product = $this->product('Big sister sloth tee', $siblings, [$sloths]);
        $this->product('Mum sloth tee', $parents, [$sloths]);
        $this->product('Dad sloth tee', $parents, [$sloths]);
        $this->product('Sold-out sloth tee', $parents, [$sloths], 'outofstock');
        $this->product('Little brother dino tee', $siblings, []);

        $theme = $this->section($this->render($product), ThemeProducts::CLASS_NAME);

        self::assertStringContainsString('More in the theme Theme sloths', $theme);
        self::assertStringContainsString('href="' . esc_url((string) get_term_link($sloths, ThemeProducts::TAXONOMY)) . '"', $theme, 'See all leads to the tag\'s archive.');
        self::assertStringContainsString('Mum sloth tee', $theme);
        self::assertStringContainsString('Dad sloth tee', $theme);
        self::assertStringNotContainsString('Big sister sloth tee', $theme, 'Not the product the page is for.');
        self::assertStringNotContainsString('Sold-out sloth tee', $theme);
        self::assertStringNotContainsString('Little brother dino tee', $theme, 'Not a product without the tag.');
    }

    public function testShowsAProductWithSeveralTagsWithItsFirstByName(): void
    {
        $zebras = $this->term('Theme zebras', ThemeProducts::TAXONOMY);
        $aardvarks = $this->term('Theme aardvarks', ThemeProducts::TAXONOMY);
        $category = $this->term('Theme animals', self::CATEGORY_TAXONOMY);
        $product = $this->product('Zebra and aardvark tee', $category, [$zebras, $aardvarks]);

        foreach (['Aardvark mug', 'Aardvark poster', 'Zebra mug', 'Zebra poster'] as $name) {
            $this->product($name, $category, [str_starts_with($name, 'Zebra') ? $zebras : $aardvarks]);
        }

        $theme = $this->section($this->render($product), ThemeProducts::CLASS_NAME);

        self::assertStringContainsString('More in the theme Theme aardvarks', $theme);
        self::assertStringContainsString('Aardvark poster', $theme);
        self::assertStringNotContainsString('Zebra poster', $theme);
    }

    public function testRendersNothingWhenFewerThanTwoOtherProductsShareTheTag(): void
    {
        $owls = $this->term('Theme owls', ThemeProducts::TAXONOMY);
        $category = $this->term('Theme birds', self::CATEGORY_TAXONOMY);
        $product = $this->product('Owl tee', $category, [$owls]);
        $this->product('Owl mug', $category, [$owls]);
        $this->product('Sold-out owl poster', $category, [$owls], 'outofstock');

        $page = $this->render($product);

        self::assertStringNotContainsString(ThemeProducts::CLASS_NAME, $page);
        self::assertStringNotContainsString('More in the theme', $page);
    }

    public function testRendersNothingForAProductWithoutATag(): void
    {
        $category = $this->term('Theme plain', self::CATEGORY_TAXONOMY);
        $product = $this->product('Untagged tee', $category, []);
        $this->product('Untagged mug', $category, []);
        $this->product('Untagged poster', $category, []);

        self::assertStringNotContainsString(ThemeProducts::CLASS_NAME, $this->render($product));
    }

    private function term(string $name, string $taxonomy): int
    {
        $term = wp_insert_term($name, $taxonomy);
        self::assertIsArray($term);
        $this->terms[] = [(int) $term['term_id'], $taxonomy];

        return (int) $term['term_id'];
    }

    /**
     * @param list<int> $tags
     */
    private function product(string $name, int $category, array $tags, string $stock_status = 'instock'): int
    {
        $product = new WC_Product_Simple();
        $product->set_name($name);
        $product->set_regular_price('249');
        $product->set_status('publish');
        $product->set_stock_status($stock_status);
        $product->set_category_ids([$category]);
        $product->set_tag_ids($tags);

        $id = $product->save();
        $this->product_ids[] = $id;

        return $id;
    }

    /**
     * The markup from a section's class to the next section, or the page's end.
     */
    private function section(string $page, string $class_name): string
    {
        $start = strpos($page, $class_name);
        self::assertNotFalse($start, "The page has no {$class_name}.");
        $end = strpos($page, '<section', $start);

        return false === $end ? substr($page, $start) : substr($page, $start, $end - $start);
    }

    private function render(int $product_id): string
    {
        $query = $GLOBALS['wp_query'];
        self::assertInstanceOf(WP_Query::class, $query);
        $query->query(['post_type' => 'product', 'p' => $product_id]);
        $query->the_post();

        $template = get_block_template(get_stylesheet() . '//single-product');
        self::assertNotNull($template);

        return do_blocks($template->content);
    }
}
