<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use PHPUnit\Framework\TestCase;
use WP_Block_Template;
use WP_Post;

/**
 * The store's Checkout page as WordPress renders it with the theme's
 * checkout template: a title row with the page's title and the way back to
 * the cart, then the page's own content, which holds WooCommerce's Checkout
 * block.
 */
final class CheckoutPageTest extends TestCase
{
    use RendersAStorePage;

    private const RETURN_TO_CART = 'Return to Cart';

    /** The class the Checkout block's wrapper prints. */
    private const CHECKOUT_BLOCK = 'wp-block-woocommerce-checkout';

    protected function tearDown(): void
    {
        $this->reset_main_query();
        parent::tearDown();
    }

    public function testTheCheckoutPageUsesTheThemesCheckoutTemplate(): void
    {
        self::assertSame(get_stylesheet() . '//page-checkout', $this->template()->id);
    }

    public function testTheTitleRowHoldsThePagesTitleAndTheWayBackToTheCart(): void
    {
        $page = $this->render();

        self::assertMatchesRegularExpression(
            '/<h1 [^>]*wp-block-post-title[^>]*>' . preg_quote(get_the_title($this->checkout_page()), '/') . '</',
            $page
        );
        self::assertMatchesRegularExpression(
            '/<a [^>]*href="' . preg_quote(wc_get_cart_url(), '/') . '"[^>]*>' . self::RETURN_TO_CART . '</',
            $page
        );
    }

    public function testShowsThePagesContentWithItsCheckoutBlock(): void
    {
        self::assertStringContainsString(self::CHECKOUT_BLOCK, $this->render());
    }

    private function checkout_page(): WP_Post
    {
        return $this->store_page('checkout');
    }

    private function template(): WP_Block_Template
    {
        return $this->page_template($this->checkout_page());
    }

    private function render(): string
    {
        return $this->render_page($this->checkout_page());
    }
}
