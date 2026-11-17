/**
 * Keyboard and screen reader handling for the drawer an archive's filters open
 * in (`patterns/product-filters.php`, `inc/Archive/FilterDrawer.php`).
 * WooCommerce's script opens and closes it, and Escape closes it; the focus
 * is left where it was. Here it moves into the drawer as it opens, stays
 * there while it is open, and goes back to the button that opened it as it
 * closes.
 */

import { focusableIn, nextInCycle } from './focus-cycle';

const PANEL = '.pixonaut-shop-archive-filters';
const OPEN_BUTTON = '.wc-block-product-filters__open-overlay';
const DIALOG = '.wc-block-product-filters__overlay-dialog';
const CLOSE_BUTTON = '.wc-block-product-filters__close-overlay';
/** WooCommerce's class on the panel while its drawer is open. */
const OPENED_CLASS = 'is-overlay-opened';

function watch( panel: HTMLElement ): void {
	const dialog = panel.querySelector< HTMLElement >( DIALOG );
	const openButton = panel.querySelector< HTMLElement >( OPEN_BUTTON );

	if ( ! dialog || ! openButton ) {
		return;
	}

	let opened = panel.classList.contains( OPENED_CLASS );

	new MutationObserver( () => {
		const isOpen = panel.classList.contains( OPENED_CLASS );

		if ( isOpen === opened ) {
			return;
		}

		opened = isOpen;

		if ( isOpen ) {
			// The drawer may open with the focus already in it, on a page that
			// takes up the customer's place (`filters-place.ts`).
			if ( ! dialog.contains( document.activeElement ) ) {
				dialog.querySelector< HTMLElement >( CLOSE_BUTTON )?.focus();
			}

			return;
		}

		const active = document.activeElement;

		if ( active === null || active === document.body || dialog.contains( active ) ) {
			openButton.focus();
		}
	} ).observe( panel, { attributes: true, attributeFilter: [ 'class' ] } );

	dialog.addEventListener( 'keydown', ( event ) => {
		if ( event.key !== 'Tab' || ! panel.classList.contains( OPENED_CLASS ) ) {
			return;
		}

		const next = nextInCycle( focusableIn( dialog ), document.activeElement, event.shiftKey );

		if ( next ) {
			event.preventDefault();
			next.focus();
		}
	} );
}

function start(): void {
	const panel = document.querySelector< HTMLElement >( PANEL );

	if ( panel ) {
		watch( panel );
	}
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', start );
} else {
	start();
}
