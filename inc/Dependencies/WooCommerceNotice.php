<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Dependencies;

use Closure;

/**
 * Tells an administrator, in the dashboard, when WooCommerce is not active.
 *
 * The theme still renders its pages and posts without WooCommerce, but its
 * product, shop, cart and checkout templates have nothing to show.
 * Pixonaut for WooCommerce is optional, so its absence gets no notice.
 *
 * The notice is a core dismissible notice, and its own link dismisses it for
 * good for the administrator who follows it.
 */
final class WooCommerceNotice
{
    /** Only someone who can install the plugin is told about it. */
    public const REQUIRED_CAPABILITY = 'install_plugins';

    /** The plugin installer, searched for WooCommerce. */
    public const INSTALL_SCREEN = 'plugin-install.php?s=woocommerce&tab=search&type=term';

    /** The query argument, and the nonce action, of the link that dismisses the notice for good. */
    public const DISMISS_ARG = 'pixonaut-shop-dismiss-woocommerce-notice';

    /** The user meta that holds an administrator's dismissal. */
    public const DISMISSED_META = 'pixonaut_shop_woocommerce_notice_dismissed';

    /** What the user meta holds once the notice is dismissed. */
    private const DISMISSED = '1';

    /** The class WooCommerce declares when it is active. */
    private const WOOCOMMERCE_CLASS = 'WooCommerce';

    /** @var Closure(): bool */
    private readonly Closure $is_active;

    /**
     * @param (Closure(): bool)|null $is_active Whether WooCommerce is active; defaults to looking for its class.
     */
    public function __construct(?Closure $is_active = null)
    {
        $this->is_active = $is_active ?? static fn (): bool => class_exists(self::WOOCOMMERCE_CLASS);
    }

    public function register(): void
    {
        add_action('admin_notices', [$this, 'render']);
        add_action('admin_init', [$this, 'dismiss']);
    }

    public function render(): void
    {
        if (
            ($this->is_active)()
            || ! current_user_can(self::REQUIRED_CAPABILITY)
            || self::DISMISSED === get_user_meta(get_current_user_id(), self::DISMISSED_META, true)
        ) {
            return;
        }

        printf(
            '<div class="notice notice-warning is-dismissible"><p>%s <a href="%s">%s</a> | <a href="%s">%s</a></p></div>',
            esc_html__('Product pages, the shop, the cart and the checkout need WooCommerce. Until it is active, visitors see your pages and posts only.', 'pixonaut-shop'),
            esc_url(admin_url(self::INSTALL_SCREEN)),
            esc_html__('Install WooCommerce', 'pixonaut-shop'),
            esc_url(wp_nonce_url(add_query_arg(self::DISMISS_ARG, self::DISMISSED), self::DISMISS_ARG)),
            esc_html__('Do not show this again', 'pixonaut-shop')
        );
    }

    /**
     * Remembers the dismissal for the administrator who followed the
     * notice's dismiss link.
     */
    public function dismiss(): void
    {
        // The nonce is checked below, before anything is saved.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (! isset($_GET[self::DISMISS_ARG])) {
            return;
        }

        check_admin_referer(self::DISMISS_ARG);
        update_user_meta(get_current_user_id(), self::DISMISSED_META, self::DISMISSED);
    }
}
