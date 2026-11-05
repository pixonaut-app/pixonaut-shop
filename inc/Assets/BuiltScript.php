<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Assets;

/**
 * A script wp-scripts built into `build/`, enqueued with the dependencies and
 * version it wrote beside it in `<name>.asset.php`.
 */
final class BuiltScript
{
    private const BUILD_DIR = 'build/';

    /**
     * Enqueues `build/<name>.js` in the footer, after `$dependencies` and
     * its built ones. Returns whether it did: a checkout that has not
     * been built has no script to load.
     *
     * @param non-empty-string       $handle
     * @param list<non-empty-string> $dependencies
     */
    public static function enqueue(string $handle, string $name, array $dependencies = []): bool
    {
        $asset_path = get_theme_file_path(self::BUILD_DIR . $name . '.asset.php');

        if (! is_readable($asset_path)) {
            return false;
        }

        $asset   = require $asset_path;
        $built   = \is_array($asset) && \is_array($asset['dependencies'] ?? null) ? $asset['dependencies'] : [];
        $version = \is_array($asset) && \is_string($asset['version'] ?? null) ? $asset['version'] : false;

        foreach ($built as $dependency) {
            if (\is_string($dependency) && '' !== $dependency && ! \in_array($dependency, $dependencies, true)) {
                $dependencies[] = $dependency;
            }
        }

        wp_enqueue_script($handle, get_theme_file_uri(self::BUILD_DIR . $name . '.js'), $dependencies, $version, true);

        return true;
    }
}
