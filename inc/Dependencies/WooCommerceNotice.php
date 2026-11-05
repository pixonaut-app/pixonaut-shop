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
 */
final class WooCommerceNotice
{
    /** Only someone who can install the plugin is told about it. */
    public const REQUIRED_CAPABILITY = 'install_plugins';

    /** The plugin installer, searched for WooCommerce. */
    public const INSTALL_SCREEN = 'plugin-install.php?s=woocommerce&tab=search&type=term';

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
    }

    public function render(): void
    {
        if (($this->is_active)() || ! current_user_can(self::REQUIRED_CAPABILITY)) {
            return;
        }

        printf(
            '<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
            esc_html__('Product pages, the shop, the cart and the checkout need WooCommerce. Until it is active, visitors see your pages and posts only.', 'pixonaut-shop'),
            esc_url(admin_url(self::INSTALL_SCREEN)),
            esc_html__('Install WooCommerce', 'pixonaut-shop')
        );
    }
}
