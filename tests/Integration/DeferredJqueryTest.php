<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Pixonaut\Shop\Assets\DeferredJquery;
use WP_Scripts;

/**
 * jQuery is deferred while every script that needs it is, and printed as a
 * blocking script as soon as one is not.
 */
final class DeferredJqueryTest extends TestCase
{
    private ?WP_Scripts $scripts = null;

    protected function setUp(): void
    {
        parent::setUp();
        $scripts       = $GLOBALS['wp_scripts'] ?? null;
        $this->scripts = $scripts instanceof WP_Scripts ? $scripts : null;
        // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- each test prints from a fresh registry.
        $GLOBALS['wp_scripts'] = null;
    }

    protected function tearDown(): void
    {
        // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- puts back the registry the test replaced.
        $GLOBALS['wp_scripts'] = $this->scripts;
        parent::tearDown();
    }

    public function testJqueryIsDeferredWhenTheScriptsNeedingItAre(): void
    {
        wp_enqueue_script('deferred-shop-script', 'https://example.test/shop.js', ['jquery'], '1', ['strategy' => 'defer']);

        $printed = self::printed();

        self::assertTrue(self::deferred('jquery-core', $printed));
        self::assertTrue(self::deferred('jquery-migrate', $printed));
    }

    public function testJqueryIsDeferredWithTheVariationFormsUtilities(): void
    {
        wp_enqueue_script('variation-form', 'https://example.test/variation.js', ['jquery', 'wp-util'], '1', ['strategy' => 'defer']);

        $printed = self::printed();

        self::assertTrue(self::deferred('jquery-core', $printed));
        self::assertTrue(self::deferred('underscore', $printed));
        self::assertTrue(self::deferred('wp-util', $printed));
    }

    public function testJqueryBlocksWhenAScriptNeedingItDoesNot(): void
    {
        wp_enqueue_script('deferred-shop-script', 'https://example.test/shop.js', ['jquery'], '1', ['strategy' => 'defer']);
        wp_enqueue_script('blocking-plugin-script', 'https://example.test/plugin.js', ['jquery'], '1', ['in_footer' => true]);

        self::assertFalse(self::deferred('jquery-core', self::printed()));
    }

    public function testJqueryBlocksWhenInlineCodeAfterItUsesIt(): void
    {
        wp_enqueue_script('deferred-shop-script', 'https://example.test/shop.js', ['jquery'], '1', ['strategy' => 'defer']);
        wp_add_inline_script('jquery-core', 'jQuery(function () {});');

        self::assertFalse(self::deferred('jquery-core', self::printed()));
    }

    public function testJqueryBlocksWhenInlineCodeBeforeAScriptNeedingItRunsFirst(): void
    {
        wp_enqueue_script('deferred-shop-script', 'https://example.test/shop.js', ['jquery'], '1', ['strategy' => 'defer']);
        wp_add_inline_script('deferred-shop-script', 'jQuery(function () {});', 'before');

        self::assertFalse(self::deferred('jquery-core', self::printed()));
    }

    public function testUnderscoreBlocksWhenInlineCodeBeforeAScriptNeedingItRunsFirst(): void
    {
        wp_enqueue_script('deferred-shop-script', 'https://example.test/shop.js', ['jquery'], '1', ['strategy' => 'defer']);
        wp_enqueue_script('templating-plugin', 'https://example.test/templates.js', ['underscore'], '1', ['strategy' => 'defer', 'in_footer' => true]);
        wp_add_inline_script('templating-plugin', '_.template("");', 'before');

        $printed = self::printed();

        self::assertFalse(self::deferred('underscore', $printed));
        self::assertFalse(self::deferred('jquery-core', $printed));
    }

    public function testUnderscoreBlocksWhenInlineCodeBeforeItRunsFirst(): void
    {
        wp_enqueue_script('deferred-shop-script', 'https://example.test/shop.js', ['jquery', 'wp-util'], '1', ['strategy' => 'defer']);
        wp_add_inline_script('underscore', 'window.beforeUnderscore = true;', 'before');

        self::assertFalse(self::deferred('underscore', self::printed()));
    }

    public function testAStoreCanKeepJqueryBlocking(): void
    {
        wp_enqueue_script('deferred-shop-script', 'https://example.test/shop.js', ['jquery'], '1', ['strategy' => 'defer']);
        add_filter(DeferredJquery::FILTER, '__return_false');

        try {
            self::assertFalse(self::deferred('jquery-core', self::printed()));
        } finally {
            remove_filter(DeferredJquery::FILTER, '__return_false');
        }
    }

    public function testWooCommercesQueuedCodeRunsOnceThePageHasLoaded(): void
    {
        $queued = "<!-- WooCommerce JavaScript -->\n<script type=\"text/javascript\">\njQuery(function($) { \n$('.x').show();\n });\n</script>\n";

        $printed = (new DeferredJquery())->after_load($queued);

        self::assertIsString($printed);
        self::assertStringContainsString('document.addEventListener("DOMContentLoaded", function () { jQuery(function($) {', $printed);
        self::assertStringContainsString("$('.x').show();\n }); });\n</script>", $printed);
    }

    /**
     * Whether the printed tag of a script carries `defer`.
     */
    private static function deferred(string $handle, string $printed): bool
    {
        if (1 !== preg_match('/<script\b[^>]*\bid="' . preg_quote($handle, '/') . '-js"[^>]*>/', $printed, $tag)) {
            self::fail($handle . ' is not printed');
        }

        return 1 === preg_match('/\sdefer[\s>]/', $tag[0]);
    }

    /**
     * The head and footer scripts the page prints, once the theme marked jQuery.
     */
    private static function printed(): string
    {
        (new DeferredJquery())->defer();
        ob_start();
        wp_print_scripts();
        wp_print_footer_scripts();

        return (string) ob_get_clean();
    }
}
