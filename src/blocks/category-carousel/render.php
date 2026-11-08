<?php
/**
 * The category carousel block's markup, from CategoryCarousel.
 *
 * @package Pixonaut\Shop
 *
 * @var array<string, mixed> $attributes
 */

declare(strict_types=1);

use Pixonaut\Shop\Blocks\CategoryCarousel;

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped as it is built.
echo ( new CategoryCarousel() )->render( $attributes );
