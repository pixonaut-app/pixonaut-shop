<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Unit\Theme;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Pixonaut\Shop\Orders\OrderReceivedBand;
use Pixonaut\Shop\Tests\TestCase;

/**
 * What every store shares: the templates and parts the theme ships, the
 * room the product page leaves for Pixonaut for WooCommerce, and markup
 * that carries nothing of any one store.
 */
#[CoversNothing]
final class TemplatesTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    /** The templates every store renders; WooCommerce picks the shop's by these names. */
    public const TEMPLATES = [
        'index',
        'front-page',
        'page',
        '404',
        'single-product',
        'archive-product',
        'page-cart',
        'page-checkout',
        'order-confirmation',
        'product-search-results',
        'search',
        'single',
    ];

    public const PARTS = ['header', 'footer', 'promises', 'announcement', 'order-next-steps', 'mini-cart', 'order-help'];

    /**
     * Parts that ship empty for a store to fill in the Site Editor: an empty
     * part renders nothing, so a store that has not written its own shows no
     * stray text.
     */
    public const EMPTY_PARTS = ['promises', 'announcement', 'order-next-steps', 'order-help'];

    /** The patterns templates and page patterns refer to by slug. */
    public const PATTERNS = [
        'pixonaut-shop/announcement'        => 'announcement',
        'pixonaut-shop/cart'                => 'cart',
        'pixonaut-shop/cart-title'          => 'cart-title',
        'pixonaut-shop/checkout'            => 'checkout',
        'pixonaut-shop/checkout-title'      => 'checkout-title',
        'pixonaut-shop/continue-shopping'   => 'continue-shopping',
        'pixonaut-shop/footer'              => 'footer',
        'pixonaut-shop/front-page'          => 'front-page',
        'pixonaut-shop/hero'                => 'hero',
        'pixonaut-shop/no-products'         => 'no-products',
        'pixonaut-shop/not-found'           => 'not-found',
        'pixonaut-shop/order-help'          => 'order-help',
        'pixonaut-shop/order-next-steps'    => 'order-next-steps',
        'pixonaut-shop/popular-products'    => 'popular-products',
        'pixonaut-shop/product-grid'        => 'product-grid',
        'pixonaut-shop/product-description' => 'product-description',
        'pixonaut-shop/related-products'    => 'related-products',
        'pixonaut-shop/promises'            => 'promises',
        'pixonaut-shop/search-no-results'   => 'search-no-results',
    ];

    /**
     * WooCommerce's blocks for the order received page, which print its
     * status, the order and the offer to create an account in WooCommerce's
     * own words, and only to whoever may see the order.
     */
    public const ORDER_CONFIRMATION_BLOCKS = [
        'woocommerce/order-confirmation-status',
        'woocommerce/order-confirmation-summary',
        'woocommerce/order-confirmation-totals',
        'woocommerce/order-confirmation-shipping-address',
        'woocommerce/order-confirmation-billing-address',
        'woocommerce/order-confirmation-create-account',
    ];

    /**
     * What the shop shows around its products, and the state it shows
     * without any: the shop, category and tag archives all render it.
     */
    public const SHOP_BLOCKS = [
        'woocommerce/breadcrumbs',
        'woocommerce/product-results-count',
        'woocommerce/catalog-sorting',
        'woocommerce/product-collection',
        'query-pagination',
        'woocommerce/product-collection-no-results',
    ];

    /**
     * What the cart page shows around the store's Cart page: WooCommerce's
     * notices, the page's own title and content, and the store's promises.
     */
    public const CART_PAGE_BLOCKS = [
        'woocommerce/store-notices',
        'post-title',
        'post-content',
        'template-part {"slug":"promises"',
    ];

    /**
     * What the checkout page shows around the store's Checkout page:
     * WooCommerce's notices, and the page's own title and content.
     */
    public const CHECKOUT_PAGE_BLOCKS = [
        'woocommerce/store-notices',
        'post-title',
        'post-content',
    ];

    /**
     * What the checkout pattern's Checkout block turns on: its step numbers,
     * and the terms as a box the customer ticks.
     */
    public const CHECKOUT_PATTERN_BLOCKS = [
        'woocommerce/checkout {"showFormStepNumbers":true}',
        'woocommerce/checkout-terms-block {"checkbox":true}',
    ];

    /**
     * What the cart page pattern's empty state holds: WooCommerce's own
     * message and link back to the shop in a panel, then the popular
     * products.
     */
    public const EMPTY_CART_BLOCKS = [
        'group {"className":"is-style-panel',
        'pattern {"slug":"woocommerce/cart-empty-message"}',
        'pattern {"slug":"pixonaut-shop/popular-products"}',
    ];

    /** The block that holds a cart's empty state, inside the Cart block. */
    private const EMPTY_CART = '/<!-- wp:woocommerce\/empty-cart-block -->.*?<!-- \/wp:woocommerce\/empty-cart-block -->/s';

    /**
     * What the front page pattern holds, in order: the hero, the store's
     * categories and its popular products.
     */
    public const FRONT_PAGE_BLOCKS = [
        'media-text',
        'pixonaut-shop/product-categories',
        'woocommerce/product-collection',
    ];

    /** How many popular products the front page shows, four to a row. */
    private const FRONT_PAGE_PRODUCTS = 8;

    private const FRONT_PAGE_COLUMNS = 4;

    /** A group the editor lets a store change the text, images and links of, but not rearrange. */
    private const CONTENT_ONLY_LOCK = '"templateLock":"contentOnly"';

    /** The opening of a section: a group directly inside the pattern, not nested in another block. */
    private const TOP_LEVEL_BLOCK = '/^<!-- wp:(?<name>[a-z0-9\/-]+) (?<attributes>\{.*\})? ?\/?-->$/m';

    /**
     * What a product search shows: the query again in its panel, with what
     * to try when nothing matched, the count and sorting, the results and
     * their pages, and the popular products and the store's promises below.
     */
    public const PRODUCT_SEARCH_BLOCKS = [
        'query-title',
        'search',
        'pixonaut-shop/product-categories',
        'woocommerce/product-results-count',
        'woocommerce/catalog-sorting',
        'woocommerce/product-collection',
        'query-pagination',
        'template-part {"slug":"promises"',
    ];

    /** What a search of the whole site shows: the same panel, and its posts and pages. */
    public const SITE_SEARCH_BLOCKS = [
        'query-title',
        'search',
        'pixonaut-shop/product-categories',
        'query',
        'post-template',
        'post-title',
        'query-pagination',
    ];

    /** The search field's attributes that make it search the products alone, as WooCommerce's variation does. */
    private const PRODUCT_SEARCH_FIELD = '"query":{"post_type":"product"},"namespace":"woocommerce/product-search"';

    /** The class of the group that shows what a search found nothing for, which only an empty search renders. */
    private const NO_RESULTS_CLASS = 'pixonaut-shop-search-no-results';

    /**
     * The mini-cart drawer's filled view, in the order it reads: the title
     * with the count, the lines, then the footer with the subtotal, the
     * free-shipping note and the two buttons, checkout first.
     */
    public const FILLED_MINI_CART_BLOCKS = [
        'woocommerce/filled-mini-cart-contents-block',
        'woocommerce/mini-cart-title-block',
        'woocommerce/mini-cart-title-items-counter-block',
        'woocommerce/mini-cart-items-block',
        'woocommerce/mini-cart-products-table-block',
        'woocommerce/mini-cart-footer-block',
        'pixonaut-shop/free-shipping-note',
        'woocommerce/mini-cart-checkout-button-block',
        'woocommerce/mini-cart-cart-button-block',
    ];

    /**
     * The empty view: the bag in a circle, WooCommerce's empty-cart heading
     * and its button back to the shop.
     */
    public const EMPTY_MINI_CART_BLOCKS = [
        'woocommerce/empty-mini-cart-contents-block',
        'pixonaut-shop/icon',
        'pattern',
        'woocommerce/mini-cart-shopping-button-block',
    ];

    /** Blocks that buy from a list: every product is personalised on its own page first. */
    private const ADD_TO_CART_BLOCKS = ['woocommerce/product-button', 'woocommerce/add-to-cart-form'];

    /** A reference to one of the theme's own patterns, which a template renders in its place. */
    private const THEME_PATTERN_REFERENCE = '/<!-- wp:pattern \{"slug":"(?<slug>pixonaut-shop\/[a-z0-9-]+)"\} \/-->/';

    /** The cards of a product collection: its product template and every block in it. */
    private const PRODUCT_TEMPLATE = '/<!-- wp:woocommerce\/product-template .*?<!-- \/wp:woocommerce\/product-template -->/s';

    /** The text domain every translatable string uses. */
    private const TEXT_DOMAIN = 'pixonaut-shop';

    /** A WordPress translation call, and the arguments up to its closing parenthesis. */
    private const TRANSLATION_CALL = '/\b(?:esc_html_|esc_attr_|_)_\(\s*(?<args>(?:[^()]|\([^()]*\))*)\)/';

    /** The last argument of a translation call: its text domain. */
    private const LAST_ARGUMENT = "/,\\s*'(?<domain>[^']*)'\\s*\\z/";

    /** A call to one of WooCommerce's functions, which exist only while WooCommerce is active. */
    private const WOOCOMMERCE_CALL = '/(?<!\\$|->|::|\\w)(?<function>wc_\\w+)\\s*\\(/';

    /**
     * Where Pixonaut for WooCommerce prints its designer: in place of the
     * gallery block, and inside the add-to-cart form, at
     * `woocommerce_before_add_to_cart_button`, which the form block fires.
     */
    public const PIXONAUT_INSERTION_BLOCKS = [
        'woocommerce/product-image-gallery',
        'woocommerce/add-to-cart-form',
    ];

    /** The opening of any HTML comment, and of a block delimiter. */
    private const HTML_COMMENT = '/<!--(?!\s*\/?wp:)/';

    /** A colour written as a hex value, which belongs in theme.json or the site's Styles. */
    private const HEX_COLOUR = '/#[0-9a-f]{3,8}\b/i';

    /** An image or address written into markup, which belongs in a store's own content. */
    private const HARDCODED_ASSET = '/<img\b|https?:\/\//i';

    /**
     * @return iterable<string, array{string}>
     */
    public static function templates(): iterable
    {
        foreach (self::TEMPLATES as $slug) {
            yield $slug => ["templates/{$slug}.html"];
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function markupFiles(): iterable
    {
        $files = array_merge(
            self::files('templates/*.html'),
            self::files('parts/*.html'),
            self::files('patterns/*.php')
        );

        foreach ($files as $file) {
            $relative = substr((string) realpath($file), \strlen((string) realpath(self::ROOT)) + 1);

            yield $relative => [$relative];
        }
    }

    #[DataProvider('templates')]
    public function testShipsTemplate(string $path): void
    {
        self::assertFileExists(self::ROOT . '/' . $path);
    }

    public function testShipsTheParts(): void
    {
        foreach (self::PARTS as $part) {
            self::assertFileExists(self::ROOT . "/parts/{$part}.html");
        }
    }

    public function testShipsTheEmptyPartsEmpty(): void
    {
        foreach (self::EMPTY_PARTS as $part) {
            self::assertSame('', trim($this->read("parts/{$part}.html")), "parts/{$part}.html must be empty.");
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function declaredParts(): iterable
    {
        yield 'promises' => ['promises', 'Promises'];
        yield 'announcement' => ['announcement', 'Announcement'];
        yield 'order-next-steps' => ['order-next-steps', 'Order next steps'];
        yield 'order-help' => ['order-help', 'Order help'];
    }

    #[DataProvider('declaredParts')]
    public function testDeclaresTheStoresOwnPartsWithoutAnArea(string $name, string $title): void
    {
        self::assertContains(
            ['name' => $name, 'title' => $title, 'area' => 'uncategorized'],
            ThemeJson::at(ThemeJson::theme(), 'templateParts')
        );
    }

    public function testTheHeaderHoldsTheAnnouncementPartAboveItsRow(): void
    {
        $header = $this->read('parts/header.html');

        $announcement = strpos($header, '<!-- wp:template-part {"slug":"announcement"');
        $title = strpos($header, '<!-- wp:site-title ');

        self::assertNotFalse($announcement, 'header.html must hold the announcement part.');
        self::assertNotFalse($title);
        self::assertLessThan($title, $announcement);
    }

    public function testTheOrderReceivedPageKeepsWooCommercesOrderBlocks(): void
    {
        $markup = $this->read('templates/order-confirmation.html');

        foreach (self::ORDER_CONFIRMATION_BLOCKS as $block) {
            self::assertStringContainsString("<!-- wp:{$block} ", $markup, "order-confirmation.html must keep {$block}.");
        }
    }

    public function testTheOrderReceivedPageLeavesItsNextStepsToTheStore(): void
    {
        self::assertStringContainsString(
            '<!-- wp:template-part {"slug":"order-next-steps"',
            $this->read('templates/order-confirmation.html')
        );
    }

    public function testTheOrderReceivedPageMarksThePillForItsOrderNumber(): void
    {
        self::assertMatchesRegularExpression(
            '/<!-- wp:paragraph \{"className":"is-style-pill ' . OrderReceivedBand::PILL_MARKER . '"\} -->/',
            $this->read('templates/order-confirmation.html')
        );
        self::assertStringContainsString(
            '<!-- wp:group {"className":"' . OrderReceivedBand::BADGE_MARKER . '"} -->',
            $this->read('templates/order-confirmation.html')
        );
    }

    public function testShipsThePatterns(): void
    {
        foreach (self::PATTERNS as $slug => $file) {
            self::assertStringContainsString(" * Slug: {$slug}\n", $this->read("patterns/{$file}.php"));
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function patternFiles(): iterable
    {
        foreach (self::PATTERNS as $file) {
            yield $file => ["patterns/{$file}.php"];
        }
    }

    #[DataProvider('patternFiles')]
    public function testTranslatesEveryStringInTheThemesTextDomain(string $path): void
    {
        preg_match_all(self::TRANSLATION_CALL, $this->read($path), $calls);

        foreach ($calls['args'] as $arguments) {
            self::assertMatchesRegularExpression(self::LAST_ARGUMENT, $arguments, "{$path}: a translation call names no text domain.");
            preg_match(self::LAST_ARGUMENT, $arguments, $domain);
            self::assertSame(self::TEXT_DOMAIN, $domain['domain'], "{$path} translates outside the theme's text domain.");
        }
    }

    /**
     * WordPress loads every pattern file when the editor lists patterns, with
     * or without WooCommerce, so a pattern calls one of its
     * functions only once it has checked that the function exists.
     */
    #[DataProvider('patternFiles')]
    public function testCallsWooCommerceOnlyOnceItKnowsItIsActive(string $path): void
    {
        $markup = $this->read($path);
        preg_match_all(self::WOOCOMMERCE_CALL, $markup, $calls);

        foreach (array_unique($calls['function']) as $function) {
            self::assertStringContainsString(
                "function_exists( '{$function}' )",
                $markup,
                "{$path} calls {$function}() without checking that WooCommerce is active."
            );
        }
    }

    public function testLeavesTheProductPageRoomForThePixonautDesigner(): void
    {
        $markup = $this->read('templates/single-product.html');

        foreach (self::PIXONAUT_INSERTION_BLOCKS as $block) {
            self::assertStringContainsString("<!-- wp:{$block} ", $markup, "single-product.html must keep {$block}.");
        }
    }

    public function testTheShopKeepsItsBreadcrumbsCountSortingPaginationAndEmptyState(): void
    {
        $markup = $this->expanded('templates/archive-product.html');

        foreach (self::SHOP_BLOCKS as $block) {
            self::assertStringContainsString("<!-- wp:{$block} ", $markup, "archive-product.html must keep {$block}.");
        }
    }

    public function testTheShopHasNoAddToCartButton(): void
    {
        $markup = $this->expanded('templates/archive-product.html');

        foreach (self::ADD_TO_CART_BLOCKS as $block) {
            self::assertStringNotContainsString("<!-- wp:{$block} ", $markup, "archive-product.html shows {$block}.");
        }
    }

    public function testTheProductSearchKeepsItsPanelCountSortingResultsAndPagination(): void
    {
        $markup = $this->expanded('templates/product-search-results.html');

        foreach (self::PRODUCT_SEARCH_BLOCKS as $block) {
            self::assertStringContainsString("<!-- wp:{$block}", $markup, "product-search-results.html must keep {$block}.");
        }

        self::assertStringContainsString('"type":"search"', $markup);
        self::assertStringContainsString(self::PRODUCT_SEARCH_FIELD, $markup);
        self::assertStringContainsString(
            '<!-- wp:pattern {"slug":"pixonaut-shop/popular-products"} /-->',
            $this->read('templates/product-search-results.html')
        );
    }

    public function testTheProductSearchHasNoAddToCartButton(): void
    {
        $markup = $this->expanded('templates/product-search-results.html');

        foreach (self::ADD_TO_CART_BLOCKS as $block) {
            self::assertStringNotContainsString("<!-- wp:{$block} ", $markup, "product-search-results.html shows {$block}.");
        }
    }

    public function testTheProductSearchShowsTheGridsCards(): void
    {
        self::assertSame(
            $this->product_template('patterns/product-grid.php'),
            $this->product_template('templates/product-search-results.html'),
            'The search results and the product grid show different cards.'
        );
    }

    /**
     * Below the search field, inside its panel, before anything else the
     * search shows.
     */
    public function testBothSearchesSayWhatTheyFoundNothingForInTheirPanel(): void
    {
        foreach (['product-search-results', 'search'] as $template) {
            $markup  = $this->read("templates/{$template}.html");
            $field   = strpos($markup, '<!-- wp:search ');
            $nothing = strpos($markup, '<!-- wp:pattern {"slug":"pixonaut-shop/search-no-results"} /-->');
            $panel   = strpos($markup, '<!-- /wp:group -->', (int) $field);

            self::assertNotFalse($field, "{$template}.html has no search field.");
            self::assertNotFalse($nothing, "{$template}.html never says what its search found nothing for.");
            self::assertGreaterThan($field, $nothing);
            self::assertLessThan($panel, $nothing, "{$template}.html says it outside the search's panel.");
        }

        self::assertStringContainsString(self::NO_RESULTS_CLASS, $this->read('patterns/search-no-results.php'));
        self::assertStringContainsString('"layout":"buttons"', $this->read('patterns/search-no-results.php'));
    }

    public function testTheSiteSearchListsItsPostsAndPages(): void
    {
        $markup = $this->expanded('templates/search.html');

        foreach (self::SITE_SEARCH_BLOCKS as $block) {
            self::assertStringContainsString("<!-- wp:{$block} ", $markup, "search.html must keep {$block}.");
        }

        self::assertStringContainsString('"inherit":true', $markup);
        self::assertStringNotContainsString('"post_type":"product"', $markup, 'search.html searches the products alone.');
    }

    /**
     * A card's picture starts at the product image's full size in its own
     * shape, so no product is cropped or banded until a store picks a shape
     * on the Product Image block: WooCommerce's thumbnail size is cropped
     * square unless its Product Images setting says otherwise.
     */
    public function testTheGridsCardsKeepThePicturesOwnShape(): void
    {
        $card = $this->product_template('patterns/product-grid.php');

        self::assertStringContainsString('<!-- wp:woocommerce/product-image ', $card);
        self::assertStringNotContainsString('"aspectRatio"', $card);
        self::assertStringNotContainsString('"imageSizing"', $card);
    }

    public function testThePopularProductsShowTheGridsCards(): void
    {
        self::assertSame(
            $this->product_template('patterns/product-grid.php'),
            $this->product_template('patterns/popular-products.php'),
            'The popular products and the product grid show different cards.'
        );
    }

    public function testTheRelatedProductsShowTheGridsCards(): void
    {
        self::assertSame(
            $this->product_template('patterns/product-grid.php'),
            $this->product_template('patterns/related-products.php'),
            'The related products and the product grid show different cards.'
        );
    }

    public function testTheFrontPageLeavesTheWholePageToItsContent(): void
    {
        $template = $this->read('templates/front-page.html');

        self::assertStringContainsString('<!-- wp:template-part {"slug":"header"', $template);
        self::assertStringContainsString('<!-- wp:post-content {"align":"full"', $template);
        self::assertStringNotContainsString('<!-- wp:post-title ', $template);
        self::assertStringContainsString('<!-- wp:template-part {"slug":"promises"', $template);
        self::assertStringContainsString('<!-- wp:template-part {"slug":"footer"', $template);
        self::assertLessThan(
            strpos($template, '<!-- wp:template-part {"slug":"promises"'),
            strpos($template, '<!-- wp:post-content '),
            'The promises sit below the page content.'
        );
    }

    /**
     * The page pattern is offered when a store creates a page, as a start
     * for its content, and nowhere else.
     */
    public function testOffersTheFrontPagePatternWhenAStoreCreatesAPage(): void
    {
        $header = $this->read('patterns/front-page.php');

        self::assertStringContainsString(" * Post Types: page\n", $header);
        self::assertStringContainsString(" * Block Types: core/post-content\n", $header);
    }

    public function testTheFrontPageHoldsTheHeroTheCategoriesAndThePopularProducts(): void
    {
        $markup = $this->expanded('patterns/front-page.php');
        $last = -1;

        foreach (self::FRONT_PAGE_BLOCKS as $block) {
            $at = strpos($markup, "<!-- wp:{$block} ");
            self::assertNotFalse($at, "The front page pattern has no {$block}.");
            self::assertGreaterThan($last, $at, "{$block} is out of order.");
            $last = $at;
        }

        self::assertStringContainsString('"perPage":' . self::FRONT_PAGE_PRODUCTS . ',', $markup);
        self::assertStringContainsString('"columns":' . self::FRONT_PAGE_COLUMNS . ',', $markup);
        self::assertStringContainsString('"collection":"woocommerce/product-collection/best-sellers"', $markup);
    }

    public function testThePopularProductsOnTheFrontPageShowTheGridsCards(): void
    {
        self::assertSame(
            $this->product_template('patterns/product-grid.php'),
            $this->product_template('patterns/front-page.php'),
            'The front page and the product grid show different cards.'
        );
    }

    /**
     * Every section of the page pattern, the hero's included, lets a store
     * edit its text, images and links without breaking its layout.
     *
     * @return iterable<string, array{string}>
     */
    public static function pagePatternSections(): iterable
    {
        yield 'hero' => ['patterns/hero.php'];
        yield 'front-page' => ['patterns/front-page.php'];
    }

    #[DataProvider('pagePatternSections')]
    public function testLocksEverySectionToItsContent(string $path): void
    {
        preg_match_all(self::TOP_LEVEL_BLOCK, $this->read($path), $sections, PREG_SET_ORDER);
        self::assertNotEmpty($sections, "{$path} has no section.");

        foreach ($sections as $section) {
            if ('pattern' === $section['name']) {
                continue;
            }

            self::assertSame('group', $section['name'], "{$path}: a section is not a group.");
            self::assertStringContainsString(self::CONTENT_ONLY_LOCK, $section['attributes'], "{$path}: a section is not locked to its content.");
        }
    }

    /** The store chooses the hero's picture: the pattern leaves it empty. */
    public function testTheHeroLeavesItsPictureEmpty(): void
    {
        $hero = $this->read('patterns/hero.php');

        self::assertStringContainsString('<!-- wp:media-text ', $hero);
        self::assertStringNotContainsString('"mediaUrl"', $hero);
        self::assertStringNotContainsString('"mediaId"', $hero);
        self::assertStringContainsString('is-style-band-tint-1', $hero);
        self::assertStringContainsString('is-style-pill', $hero);
    }

    /**
     * The description and the attributes sit in their own panel below the
     * purchase panel, rather than in tabs beside it.
     */
    public function testShowsTheProductDetailsInAPanelBelowThePurchase(): void
    {
        $template = $this->read('templates/single-product.html');
        $form = strpos($template, '<!-- wp:woocommerce/add-to-cart-form ');
        $details = strpos($template, '<!-- wp:pattern {"slug":"pixonaut-shop/product-description"} /-->');

        self::assertNotFalse($form);
        self::assertNotFalse($details, 'single-product.html must place the product description panel.');
        self::assertGreaterThan($form, $details);

        $panel = $this->read('patterns/product-description.php');
        self::assertStringContainsString('is-style-panel', $panel);
        self::assertStringContainsString('<!-- wp:woocommerce/product-details ', $panel);
    }

    /**
     * The price prints in the add-to-cart form's last row (`Product\PriceRow`),
     * where Pixonaut for WooCommerce updates it, not as a block of its own.
     */
    public function testLeavesThePriceToTheAddToCartFormsRow(): void
    {
        self::assertStringNotContainsString(
            '<!-- wp:woocommerce/product-price ',
            $this->read('templates/single-product.html')
        );
    }

    public function testTheCartPageKeepsItsNoticesTitleContentAndPromises(): void
    {
        $markup = $this->expanded('templates/page-cart.html');

        foreach (self::CART_PAGE_BLOCKS as $block) {
            self::assertStringContainsString("<!-- wp:{$block}", $markup, "page-cart.html must keep {$block}.");
        }
    }

    public function testTheCartPatternIsAPagePatternWithTheDesignedEmptyState(): void
    {
        $markup = $this->read('patterns/cart.php');

        self::assertStringContainsString(" * Post Types: page\n", $markup);
        self::assertStringContainsString('<!-- wp:woocommerce/cart ', $markup);
        self::assertMatchesRegularExpression(self::EMPTY_CART, $markup, 'cart.php has no empty state.');
        preg_match(self::EMPTY_CART, $markup, $empty);

        foreach (self::EMPTY_CART_BLOCKS as $block) {
            self::assertStringContainsString("<!-- wp:{$block}", $empty[0], "The cart's empty state must hold {$block}.");
        }
    }

    /**
     * A content page is the store's own text in a narrow column: the
     * breadcrumbs, then one panel with the title, the excerpt as its lede and
     * the content, then the store's promises on a band.
     */
    public function testTheContentPageHoldsItsTitleLedeAndContentInOnePanel(): void
    {
        $page = $this->read('templates/page.html');

        self::assertMatchesRegularExpression(
            '/<!-- wp:woocommerce\/breadcrumbs [^>]*\/-->\s*<!-- wp:group \{[^}]*"className":"is-style-panel[^"]*"[^>]*-->'
            . '.*<!-- wp:post-title \{"level":1\} \/-->'
            . '\s*<!-- wp:post-excerpt \{[^>]*"textColor":"muted"[^>]*"fontSize":"large"[^>]*\/-->'
            . '\s*<!-- wp:post-content [^>]*\/-->\s*<\/article>\s*<!-- \/wp:group -->'
            . '\s*<!-- wp:group \{[^}]*"className":"is-style-band-tint-1[^"]*"[^>]*-->\s*<div [^>]*>'
            . '\s*<!-- wp:template-part \{"slug":"promises"/s',
            $page
        );
    }

    /**
     * The 404 page is a centred panel with the status as a pill, the theme's
     * heading and sentence, the product search and the way home, with the
     * store's categories below it.
     */
    public function testTheNotFoundPageOffersTheSearchTheHomePageAndTheCategories(): void
    {
        $markup = $this->expanded('templates/404.html');

        self::assertMatchesRegularExpression(
            '/<!-- wp:group \{[^}]*"className":"is-style-panel[^"]*"[^>]*-->'
            . '\s*<div [^>]*>\s*<!-- wp:paragraph \{"className":"is-style-pill"\} -->\s*<p class="is-style-pill">404<\/p>'
            . '.*<!-- wp:heading \{[^>]*"level":1[^>]*-->'
            . '.*<!-- wp:woocommerce\/product-search '
            . '.*<!-- wp:button \{"className":"is-style-outline"\} -->\s*<div [^\n]*home_url\( \'\/\' \)'
            . '.*<!-- \/wp:group -->'
            . '.*<!-- wp:heading -->[^\n]*\n[^\n]*Shop by category'
            . '.*<!-- wp:pixonaut-shop\/product-categories [^>]*\/-->/s',
            $markup
        );
    }

    public function testTheCheckoutPageKeepsItsNoticesTitleAndContent(): void
    {
        $markup = $this->expanded('templates/page-checkout.html');

        foreach (self::CHECKOUT_PAGE_BLOCKS as $block) {
            self::assertStringContainsString("<!-- wp:{$block}", $markup, "page-checkout.html must keep {$block}.");
        }
    }

    /**
     * The secure payment line sits in the Checkout block's form, after its
     * place-order button, where the store can remove it.
     */
    public function testTheCheckoutPatternIsAPagePatternWithStepNumbersTermsBoxAndSecurePaymentLine(): void
    {
        $markup = $this->read('patterns/checkout.php');

        self::assertStringContainsString(" * Post Types: page\n", $markup);

        foreach (self::CHECKOUT_PATTERN_BLOCKS as $block) {
            self::assertStringContainsString("<!-- wp:{$block} -->", $markup);
        }

        self::assertMatchesRegularExpression(
            '#<!-- /wp:woocommerce/checkout-actions-block -->\s*<!-- wp:paragraph {"className":"pixonaut-shop-secure-payment"} -->.*?<!-- /wp:paragraph -->\s*</div>\s*<!-- /wp:woocommerce/checkout-fields-block -->#s',
            $markup
        );
    }

    public function testTheMiniCartReadsFromTheLinesToTheButtons(): void
    {
        $this->assertBlocksInOrder('parts/mini-cart.html', self::FILLED_MINI_CART_BLOCKS);
    }

    public function testTheEmptyMiniCartShowsTheBagWooCommercesHeadingAndTheShopButton(): void
    {
        $this->assertBlocksInOrder('parts/mini-cart.html', self::EMPTY_MINI_CART_BLOCKS);

        $part = $this->read('parts/mini-cart.html');
        self::assertStringContainsString('<!-- wp:pixonaut-shop/icon {"icon":"bag","circle":true', $part);
        self::assertStringContainsString('<!-- wp:pattern {"slug":"woocommerce/mini-cart-empty-cart-message"} /-->', $part);
    }

    /**
     * The free-shipping note sits in the footer, where the subtotal is; the
     * filled view's footer closes before the empty view opens.
     */
    public function testTheFreeShippingNoteIsInTheMiniCartsFooter(): void
    {
        $part = $this->read('parts/mini-cart.html');

        self::assertMatchesRegularExpression(
            '/<!-- wp:woocommerce\/mini-cart-footer-block .*<!-- wp:pixonaut-shop\/free-shipping-note \/-->.*<!-- \/wp:woocommerce\/mini-cart-footer-block -->/s',
            $part
        );
    }

    #[DataProvider('markupFiles')]
    public function testHoldsNoCommentThatIsNotABlockDelimiter(string $path): void
    {
        self::assertDoesNotMatchRegularExpression(self::HTML_COMMENT, $this->read($path));
    }

    #[DataProvider('markupFiles')]
    public function testHardcodesNoColour(string $path): void
    {
        self::assertDoesNotMatchRegularExpression(self::HEX_COLOUR, $this->read($path));
    }

    #[DataProvider('markupFiles')]
    public function testHardcodesNoImageOrAddress(string $path): void
    {
        self::assertDoesNotMatchRegularExpression(self::HARDCODED_ASSET, $this->read($path));
    }

    /**
     * Fails unless every block in `$blocks` opens in `$path`, each after the
     * one before it.
     *
     * @param list<string> $blocks
     */
    private function assertBlocksInOrder(string $path, array $blocks): void
    {
        $markup = $this->read($path);
        $previous = -1;

        foreach ($blocks as $block) {
            $position = preg_match('/<!-- wp:' . preg_quote($block, '/') . '[ \-]/', $markup, $match, PREG_OFFSET_CAPTURE, $previous + 1)
                ? $match[0][1]
                : false;

            self::assertNotFalse($position, "{$path} must hold {$block} after the block before it.");
            $previous = $position;
        }
    }

    /**
     * @return list<string>
     */
    private static function files(string $pattern): array
    {
        $files = glob(self::ROOT . '/' . $pattern);

        return false === $files ? [] : $files;
    }

    /**
     * A file's markup with every reference to one of the theme's patterns
     * replaced by that pattern's own, as WordPress renders it.
     */
    private function expanded(string $path): string
    {
        return (string) preg_replace_callback(
            self::THEME_PATTERN_REFERENCE,
            fn (array $reference): string => $this->expanded('patterns/' . self::PATTERNS[$reference['slug']] . '.php'),
            $this->read($path)
        );
    }

    /** A file's product template, its indentation aside. */
    private function product_template(string $path): string
    {
        self::assertMatchesRegularExpression(self::PRODUCT_TEMPLATE, $this->read($path), "{$path} has no product template.");
        preg_match(self::PRODUCT_TEMPLATE, $this->read($path), $template);

        return (string) preg_replace('/\s+/', ' ', $template[0]);
    }

    private function read(string $path): string
    {
        // No WordPress runtime in a unit test, so no WP_Filesystem.
        $contents = file_get_contents(self::ROOT . '/' . $path);
        self::assertIsString($contents);

        return $contents;
    }
}
