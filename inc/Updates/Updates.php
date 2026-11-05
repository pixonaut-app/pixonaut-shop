<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Updates;

use YahnisElsts\PluginUpdateChecker\v5p7\Vcs\ThemeUpdateChecker;

/**
 * Where the theme's updates come from.
 *
 * The theme checks its public repository's GitHub releases.
 * `PIXONAUT_SHOP_UPDATE_TOKEN` is optional: it authenticates the checks, which
 * lifts GitHub's limit on anonymous requests for a store on a shared address.
 */
final class Updates
{
    /**
     * The `wp-config.php` constant holding an optional GitHub token. It is
     * read on each request and never stored.
     */
    public const TOKEN_CONSTANT = 'PIXONAUT_SHOP_UPDATE_TOKEN';

    private ?GitHubUpdater $updater = null;

    /**
     * @param string      $stylesheet_file Absolute path of the theme's style.css.
     * @param string|null $token           The GitHub token; null when none is set.
     */
    public function __construct(
        private readonly string $stylesheet_file,
        private readonly ?string $token,
    ) {
    }

    public static function for_theme(): self
    {
        return new self(PIXONAUT_SHOP_THEME_DIR . '/style.css', self::configured_token());
    }

    /**
     * The token from `wp-config.php`, or null when it is missing or empty.
     */
    public static function configured_token(): ?string
    {
        if (! \defined(self::TOKEN_CONSTANT)) {
            return null;
        }

        $token = \constant(self::TOKEN_CONSTANT);

        return \is_string($token) && '' !== trim($token) ? trim($token) : null;
    }

    public function register(): void
    {
        // The library is a Composer dependency: a copy of the theme uploaded
        // without its vendor directory boots, and simply does not update.
        if (! class_exists(ThemeUpdateChecker::class)) {
            return;
        }

        $this->updater = new GitHubUpdater($this->stylesheet_file, $this->token);
        $this->updater->register();
    }

    /**
     * The GitHub updater, once register() started one.
     */
    public function updater(): ?GitHubUpdater
    {
        return $this->updater;
    }
}
