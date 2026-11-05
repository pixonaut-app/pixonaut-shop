<?php

declare(strict_types=1);

namespace Pixonaut\Shop;

/**
 * The theme's single entry point.
 *
 * Every hook in the theme is registered from here, so the whole hook surface
 * is greppable from one file. Hooks are added with explicit priorities
 * inside each module where a priority matters, rather than relying on the
 * order these calls happen to run in: the call order below is
 * documentation, not a dependency graph.
 */
final class Bootstrap
{
    public function register(): void
    {
        $this->register_i18n();
        $this->register_assets();
        $this->register_blocks();
        $this->register_header();
        $this->register_content();
        $this->register_archive();
        $this->register_front_page();
        $this->register_dependencies();
        $this->register_product();
        $this->register_account();
        $this->register_orders();
        $this->register_pixonaut();
        $this->register_updates();
    }

    private function register_i18n(): void
    {
        (new I18n\TextDomain())->register();
    }

    private function register_assets(): void
    {
        (new Assets\AssetManager())->register();
    }

    /**
     * What the theme adds to core's blocks: its block styles, the shapes its
     * pictures are offered in, and the menu heading its footer needs where
     * core prints none. Then its own blocks, and the free-shipping note's
     * text in the Store API cart.
     */
    private function register_blocks(): void
    {
        (new Blocks\BlockStyles())->register();
        (new Blocks\AspectRatios())->register();
        (new Blocks\MenuHeading())->register();
        (new Blocks\BlockLoader())->register();
        (new Blocks\FreeShippingNote())->register();
    }

    /** The header's search, which keeps an empty field open rather than submit it. */
    private function register_header(): void
    {
        (new Header\HeaderSearch())->register();
    }

    private function register_content(): void
    {
        (new Content\PageExcerpt())->register();
    }

    private function register_archive(): void
    {
        (new Archive\ProductGridDefaults())->register();
        (new Archive\ShownWhenEmpty())->register();
        (new Archive\NoResultsTitle())->register();
    }

    private function register_front_page(): void
    {
        (new FrontPage\LatestPostsFallback())->register();
    }

    /**
     * WooCommerce is required; Pixonaut for WooCommerce is optional
     * and needs nothing from the theme.
     */
    private function register_dependencies(): void
    {
        (new Dependencies\WooCommerceNotice())->register();
    }

    /** What the order received page fills in when it renders. */
    private function register_orders(): void
    {
        (new Orders\OrderReceivedBand())->register();
    }

    /**
     * The product page: the preview frame at the shape the store picks, and
     * in the add-to-cart form the price in the row with the quantity and the
     * button, and a variable product's attributes as chips where the store
     * asks for them.
     */
    private function register_product(): void
    {
        (new Product\PreviewFrame(new Blocks\AspectRatios()))->register();
        (new Product\PriceRow())->register();
        (new Product\VariationSwatches())->register();
    }

    /**
     * My account's dashboard, orders table and order view, on WooCommerce's
     * hooks, which run only on its account pages.
     */
    private function register_account(): void
    {
        $status = new Account\OrderStatus();
        $status->register();
        (new Account\Dashboard())->register();
        (new Account\ViewOrder($status))->register();
    }

    /**
     * Callbacks on Pixonaut for WooCommerce's documented hooks, which run only
     * when the plugin fires them.
     */
    private function register_pixonaut(): void
    {
        (new Pixonaut\Integration())->register();
    }

    /**
     * Updates from the repository's GitHub releases.
     */
    private function register_updates(): void
    {
        Updates\Updates::for_theme()->register();
    }
}
