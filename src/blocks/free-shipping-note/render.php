<?php
/**
 * The free-shipping note block's markup, from FreeShippingNote.
 *
 * @package Pixonaut\Shop
 */

declare(strict_types=1);

use Pixonaut\Shop\Blocks\FreeShippingNote;

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped as it is built.
echo ( new FreeShippingNote() )->render();
