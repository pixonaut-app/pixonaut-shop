<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Assets;

/**
 * Loads jQuery without holding up the first paint where nothing needs it
 * sooner. WooCommerce prints jQuery in the head as a blocking script, while
 * every WooCommerce script that uses it on the shop's pages is deferred, so
 * the browser waited for jQuery before drawing anything, about 80 ms on a
 * phone's connection.
 *
 * The scripts are marked for deferring, and WordPress defers them only when
 * every enqueued script that needs them is deferred too and none has inline
 * code after it that would run first; otherwise it prints them as before.
 */
final class DeferredJquery
{
    /** jQuery and its migrate layer, which the `jquery` handle stands for. */
    private const HANDLES = ['jquery-core', 'jquery-migrate'];

    /** After every other enqueue, so the strategy is set on what the page prints. */
    private const PRIORITY = PHP_INT_MAX;

    private const STRATEGY = 'defer';

    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'defer'], self::PRIORITY);
    }

    public function defer(): void
    {
        foreach (self::HANDLES as $handle) {
            if (wp_script_is($handle, 'registered')) {
                wp_script_add_data($handle, 'strategy', self::STRATEGY);
            }
        }
    }
}
