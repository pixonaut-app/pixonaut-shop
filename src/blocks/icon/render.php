<?php
/**
 * The icon block's markup: its SVG, from Icons, inside a span that carries
 * the block's classes and text colour. An unknown name renders nothing.
 *
 * @package Pixonaut\Shop
 *
 * @var array<string, mixed> $attributes
 */

declare(strict_types=1);

use Pixonaut\Shop\Blocks\Icons;

$pixonaut_shop_svg = Icons::svg( is_string( $attributes['icon'] ?? null ) ? $attributes['icon'] : '' );

if ( '' !== $pixonaut_shop_svg ) {
	// Core escapes the wrapper's attributes; the SVG is the theme's own constant markup.
	// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
	printf(
		'<span %s>%s</span>',
		get_block_wrapper_attributes( empty( $attributes['circle'] ) ? [] : [ 'class' => 'has-circle' ] ),
		$pixonaut_shop_svg
	);
	// phpcs:enable
}
