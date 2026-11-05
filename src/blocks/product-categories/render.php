<?php
/**
 * The product categories block's markup, from ProductCategories.
 *
 * @package Pixonaut\Shop
 *
 * @var array<string, mixed> $attributes
 */

declare(strict_types=1);

use Pixonaut\Shop\Blocks\ProductCategories;

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped as it is built.
echo ( new ProductCategories() )->render( $attributes );
