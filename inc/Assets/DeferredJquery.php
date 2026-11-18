<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Assets;

use WP_Scripts;

/**
 * Loads jQuery without holding up the first paint where nothing needs it
 * sooner. WooCommerce prints jQuery in the head as a blocking script, while
 * every WooCommerce script that uses it on the shop's pages is deferred, so
 * the browser waited for jQuery before drawing anything, about 80 ms on a
 * phone's connection.
 *
 * Just before the head's scripts are printed, jQuery and its migrate layer
 * are marked for deferring, and WordPress defers them only when every
 * enqueued script that needs them is deferred too and none has inline code
 * after it. They stay blocking as well when a script needing them has
 * inline code before it, which would run first. WooCommerce's queue of
 * inline jQuery code (`wc_enqueue_js()`, printed in the footer) waits for
 * the page to load. A store with other inline code that uses jQuery as the
 * page is read returns false from `pixonaut_shop_defers_jquery`.
 */
final class DeferredJquery
{
    /**
     * Filters whether jQuery may be deferred; true. Return false where code
     * printed into the page, outside WordPress's scripts, uses jQuery
     * before the page has loaded.
     */
    public const FILTER = 'pixonaut_shop_defers_jquery';

    /** jQuery and its migrate layer, which the `jquery` handle stands for. */
    private const HANDLES = ['jquery-core', 'jquery-migrate'];

    /** The handles a script needing jQuery names. */
    private const DEPENDED_ON = ['jquery', 'jquery-core', 'jquery-migrate'];

    private const STRATEGY = 'defer';

    /** What WooCommerce's queue of inline code starts with. */
    private const QUEUED_START = 'jQuery(function($) {';

    public function register(): void
    {
        add_action('wp_print_scripts', [$this, 'defer']);
        add_filter('woocommerce_queued_js', [$this, 'after_load']);
    }

    public function defer(): void
    {
        // The storefront's pages; not the admin's, which fires the same action.
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- the theme's own filter, prefixed.
        if (is_admin() || ! (bool) apply_filters(self::FILTER, true)) {
            return;
        }

        $scripts = wp_scripts();

        if (self::needed_before($scripts)) {
            return;
        }

        foreach (self::HANDLES as $handle) {
            if ($scripts->query($handle, 'registered')) {
                $scripts->add_data($handle, 'strategy', self::STRATEGY);
            }
        }
    }

    /**
     * WooCommerce's queued inline code, run once the page has loaded, after
     * the deferred scripts, jQuery among them.
     */
    public function after_load(mixed $js): mixed
    {
        if (! \is_string($js) || ! str_contains($js, self::QUEUED_START)) {
            return $js;
        }

        return str_replace(
            self::QUEUED_START,
            'document.addEventListener("DOMContentLoaded", function () { ' . self::QUEUED_START,
            preg_replace('#\}\);\s*</script>#', '}); });' . "\n</script>", $js, 1) ?? $js
        );
    }

    /**
     * Whether an enqueued script needing jQuery has inline code before it.
     */
    private static function needed_before(WP_Scripts $scripts): bool
    {
        foreach (array_keys($scripts->registered) as $handle) {
            if (
                [] !== array_filter((array) $scripts->get_data($handle, 'before'))
                && $scripts->query($handle, 'enqueued')
                && self::needs_jquery($scripts, $handle, [])
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, true> $seen
     */
    private static function needs_jquery(WP_Scripts $scripts, string $handle, array $seen): bool
    {
        $registered    = $scripts->registered[$handle] ?? null;
        $deps          = null !== $registered ? $registered->deps : [];
        $seen[$handle] = true;

        foreach ($deps as $dependency) {
            if (\in_array($dependency, self::DEPENDED_ON, true)) {
                return true;
            }

            if (! isset($seen[$dependency]) && self::needs_jquery($scripts, $dependency, $seen)) {
                return true;
            }
        }

        return false;
    }
}
