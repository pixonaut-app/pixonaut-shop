/**
 * The mega header's menu button and panel (`inc/Header/MegaMenu.php`), which
 * the server prints whole: this script opens and closes the panel, switches
 * the group whose items show, and keeps focus inside the panel while it is
 * open.
 *
 * In the panel, a group shows its items when it is hovered, focused or
 * clicked, and one group's items always show. In the phone's drawer, a tap
 * opens a group's items under it and closes the others'. The stylesheet says
 * which of the two the panel is (`--pixonaut-shop-mega-drawer`), so the
 * breakpoint is written once, in `src/styles/_mega-menu.css`.
 */

import { groupStep, trapStep } from './mega-menu-keys.ts';

const ROOT = '.pixonaut-shop-mega';
const TOGGLE = '.pixonaut-shop-mega__toggle';
const PANEL = '.pixonaut-shop-mega__panel';
const CLOSE = '.pixonaut-shop-mega__close';
const BACKDROP = '.pixonaut-shop-mega__backdrop';
/** The groups' buttons and the plain links among them, in the menu's order. */
const GROUPS = '.pixonaut-shop-mega__group';
const FOCUSABLE = 'a[href], button:not([disabled])';

/** The custom property the stylesheet sets to 1 where the panel is a drawer. */
const DRAWER_PROPERTY = '--pixonaut-shop-mega-drawer';
const DRAWER = '1';

/** The class on `<html>` while the panel is open, which stops the page scrolling under the drawer. */
const OPEN_CLASS = 'pixonaut-shop-mega-is-open';

const MOUSE = 'mouse';

function setUp( root: HTMLElement ): void {
	const toggle = root.querySelector< HTMLButtonElement >( TOGGLE );
	const panel = root.querySelector< HTMLElement >( PANEL );
	const backdrop = root.querySelector< HTMLElement >( BACKDROP );

	if ( ! toggle || ! panel ) {
		return;
	}

	const groups = [ ...panel.querySelectorAll< HTMLElement >( GROUPS ) ];
	const buttons = groups.filter(
		( group ): group is HTMLButtonElement => group instanceof HTMLButtonElement,
	);

	const isDrawer = (): boolean =>
		getComputedStyle( root ).getPropertyValue( DRAWER_PROPERTY ).trim() === DRAWER;
	const isOpen = (): boolean => ! panel.hidden;

	const itemsOf = ( button: HTMLButtonElement ): HTMLElement | null =>
		document.getElementById( button.getAttribute( 'aria-controls' ) ?? '' );

	/** Shows the group's items and hides every other group's; `null` hides them all. */
	const show = ( shown: HTMLButtonElement | null ): void => {
		for ( const button of buttons ) {
			const items = itemsOf( button );
			button.setAttribute( 'aria-expanded', String( button === shown ) );
			if ( items ) {
				items.hidden = button !== shown;
			}
		}
	};

	const shownGroup = (): HTMLButtonElement | null =>
		buttons.find( ( button ) => button.getAttribute( 'aria-expanded' ) === 'true' ) ?? null;

	/** The panel always shows a group's items; the drawer may show none. */
	const fitLayout = (): void => {
		if ( ! isDrawer() && ! shownGroup() ) {
			show( buttons[ 0 ] ?? null );
		}
	};

	const open = (): void => {
		panel.hidden = false;
		if ( backdrop ) {
			backdrop.hidden = false;
		}
		toggle.setAttribute( 'aria-expanded', 'true' );
		document.documentElement.classList.add( OPEN_CLASS );
		if ( isDrawer() ) {
			show( null );
		}
		fitLayout();
		( groups[ 0 ] ?? panel.querySelector< HTMLElement >( FOCUSABLE ) )?.focus();
	};

	const close = (): void => {
		panel.hidden = true;
		if ( backdrop ) {
			backdrop.hidden = true;
		}
		toggle.setAttribute( 'aria-expanded', 'false' );
		document.documentElement.classList.remove( OPEN_CLASS );
		toggle.focus();
	};

	/**
	 * What Tab cycles through while the panel is open: the panel's visible
	 * links and buttons, and the menu button beside it, except in the drawer,
	 * which covers it and has its own close button.
	 */
	const focusables = (): HTMLElement[] => [
		...( isDrawer() ? [] : [ toggle ] ),
		...[ ...panel.querySelectorAll< HTMLElement >( FOCUSABLE ) ].filter(
			( element ) => element.getClientRects().length > 0,
		),
	];

	toggle.addEventListener( 'click', () => ( isOpen() ? close() : open() ) );
	root.querySelector( CLOSE )?.addEventListener( 'click', close );
	backdrop?.addEventListener( 'click', close );

	for ( const button of buttons ) {
		button.addEventListener( 'click', () => {
			if ( isDrawer() ) {
				show( button.getAttribute( 'aria-expanded' ) === 'true' ? null : button );
			} else {
				show( button );
			}
		} );
		button.addEventListener( 'focus', () => {
			if ( ! isDrawer() ) {
				show( button );
			}
		} );
		button.addEventListener( 'pointerenter', ( event ) => {
			if ( event.pointerType === MOUSE && ! isDrawer() ) {
				show( button );
			}
		} );
	}

	document.addEventListener( 'keydown', ( event ) => {
		if ( ! isOpen() ) {
			return;
		}

		if ( event.key === 'Escape' ) {
			event.preventDefault();
			close();
			return;
		}

		if ( event.key === 'Tab' ) {
			const elements = focusables();
			const active = document.activeElement;
			const target = trapStep(
				active instanceof HTMLElement ? elements.indexOf( active ) : -1,
				elements.length,
				event.shiftKey,
			);
			if ( target !== null ) {
				event.preventDefault();
				elements[ target ]?.focus();
			}
			return;
		}

		const current = groups.indexOf( document.activeElement as HTMLElement );
		const target = current === -1 ? null : groupStep( event.key, current, groups.length );
		if ( target !== null ) {
			event.preventDefault();
			groups[ target ]?.focus();
		}
	} );

	window.addEventListener( 'resize', () => {
		if ( isOpen() ) {
			fitLayout();
		}
	} );
}

for ( const root of document.querySelectorAll< HTMLElement >( ROOT ) ) {
	setUp( root );
}
