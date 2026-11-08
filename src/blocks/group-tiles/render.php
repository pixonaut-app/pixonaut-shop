<?php
/**
 * The group tiles block's markup, from GroupTiles.
 *
 * @package Pixonaut\Shop
 *
 * @var array<string, mixed> $attributes
 */

declare(strict_types=1);

use Pixonaut\Shop\Blocks\GroupTiles;

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped as it is built.
echo ( new GroupTiles() )->render( $attributes );
