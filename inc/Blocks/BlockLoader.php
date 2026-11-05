<?php

declare(strict_types=1);

namespace Pixonaut\Shop\Blocks;

/**
 * Registers the theme's own blocks, each from its directory in `build/blocks/`
 * (built from `src/blocks/` by `pnpm build`), so a new block needs no code
 * here. A theme uploaded without its build boots without them.
 *
 * Each block's editor strings are translated from `languages/`: WordPress
 * looks for their JSON catalogues under the `textdomain` its block.json names,
 * where `I18n\TextDomain` loads the theme's.
 */
final class BlockLoader
{
    private const BLOCKS_DIRECTORY = 'build/blocks';

    public function register(): void
    {
        add_action('init', [$this, 'register_blocks']);
    }

    public function register_blocks(): void
    {
        $metadata_files = glob(get_theme_file_path(self::BLOCKS_DIRECTORY . '/*/block.json'));

        foreach (false === $metadata_files ? [] : $metadata_files as $metadata_file) {
            register_block_type(\dirname($metadata_file));
        }
    }
}
