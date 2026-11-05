<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use PHPUnit\Framework\TestCase;
use WP_Block_Template;
use WP_Post;

/**
 * The store's Cart page as WordPress renders it with the theme's cart
 * template: a title row with the page's title and the way back to the shop,
 * then the page's own content, which holds WooCommerce's Cart block.
 */
final class CartPageTest extends TestCase
{
    use RendersAStorePage;

    private const CONTINUE_SHOPPING = 'Continue shopping';

    /** The class the Cart block's wrapper prints. */
    private const CART_BLOCK = 'wp-block-woocommerce-cart';

    protected function tearDown(): void
    {
        $this->reset_main_query();
        parent::tearDown();
    }

    public function testTheCartPageUsesTheThemesCartTemplate(): void
    {
        self::assertSame(get_stylesheet() . '//page-cart', $this->template()->id);
    }

    public function testTheTitleRowHoldsThePagesTitleAndTheWayBackToTheShop(): void
    {
        $page = $this->render();

        self::assertMatchesRegularExpression(
            '/<h1 [^>]*wp-block-post-title[^>]*>' . preg_quote(get_the_title($this->cart_page()), '/') . '</',
            $page
        );
        self::assertMatchesRegularExpression(
            '/<a [^>]*href="' . preg_quote((string) get_post_type_archive_link('product'), '/') . '"[^>]*>' . self::CONTINUE_SHOPPING . '</',
            $page
        );
    }

    public function testShowsThePagesContentWithItsCartBlock(): void
    {
        self::assertStringContainsString(self::CART_BLOCK, $this->render());
    }

    private function cart_page(): WP_Post
    {
        return $this->store_page('cart');
    }

    private function template(): WP_Block_Template
    {
        return $this->page_template($this->cart_page());
    }

    private function render(): string
    {
        return $this->render_page($this->cart_page());
    }
}
