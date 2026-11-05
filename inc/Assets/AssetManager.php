<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Assets;

use Pixonaut\Shop\Support\ThemeVersion;

/**
 * Enqueues the built stylesheet for the front end and the editor canvas.
 *
 * The same compiled stylesheet is loaded in both places, so what an editor
 * sees in the canvas matches what a visitor sees. Neither carries a colour,
 * a font or a size of its own: those come from theme.json and the site's
 * Styles.
 */
final class AssetManager
{
    public const HANDLE = 'pixonaut-shop';

    private const STYLESHEET = 'build/app.css';

    private ?string $asset_version = null;

    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend']);
        add_action('after_setup_theme', [$this, 'add_editor_styles']);
    }

    public function enqueue_frontend(): void
    {
        wp_enqueue_style(
            self::HANDLE,
            get_theme_file_uri(self::STYLESHEET),
            [],
            $this->version(),
        );
    }

    public function add_editor_styles(): void
    {
        add_theme_support('editor-styles');
        add_editor_style(self::STYLESHEET);
    }

    /**
     * The stylesheet's modification time, so a rebuild busts the cache without
     * a version bump. Falls back to the theme version when unreadable.
     */
    private function version(): string
    {
        if (null !== $this->asset_version) {
            return $this->asset_version;
        }

        $path      = get_theme_file_path(self::STYLESHEET);
        $timestamp = is_readable($path) ? filemtime($path) : false;

        $this->asset_version = false !== $timestamp
            ? (string) $timestamp
            : ThemeVersion::get();

        return $this->asset_version;
    }
}
