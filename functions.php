<?php

/**
 * Theme bootstrap.
 *
 * @package Pixonaut\Shop
 */

declare(strict_types=1);

define('PIXONAUT_SHOP_THEME_FILE', __FILE__);
define('PIXONAUT_SHOP_THEME_DIR', __DIR__);

$pixonaut_shop_autoload = __DIR__ . '/vendor/autoload.php';

if (file_exists($pixonaut_shop_autoload)) {
    require_once $pixonaut_shop_autoload;
} else {
    // The class-to-path rule lives in FallbackAutoloadPath, not here, so it
    // is unit-testable on its own; see that class's docblock.
    require_once __DIR__ . '/inc/Support/FallbackAutoloadPath.php';

    /**
     * Fallback autoloader so the theme boots when uploaded without Composer.
     */
    spl_autoload_register(
        static function (string $class_name): void {
            $path = \Pixonaut\Shop\Support\FallbackAutoloadPath::resolve($class_name, __DIR__);

            if (null !== $path && is_readable($path)) {
                require_once $path;
            }
        }
    );
}

(new \Pixonaut\Shop\Bootstrap())->register();
