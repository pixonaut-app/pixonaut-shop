/**
 * The mega header's menu button and panel (`inc/Header/MegaMenu.php`), which
 * the server prints whole. The panel is a popover the button opens, so the
 * browser opens and closes it without this script, Escape and a click
 * outside included, and then lists every group's items.
 *
 * This script adds the rest. In the panel, one group's items show at a
 * time, switched when a group is hovered, focused or clicked, and the arrow
 * keys move between the groups. In the phone's drawer, a tap opens a group's
 * items under it and closes the others'. While the panel is open, Tab stays
 * in it, and on closing, focus goes back to the menu button. The stylesheet
 * says which of the two the panel is (`--pixonaut-shop-mega-drawer`), so the
 * breakpoint is written once, in `src/styles/_mega-menu.css`.
 */

import { groupStep, trapStep } from './mega-menu-keys.ts';

const ROOT = '.pixonaut-shop-mega';
const TOGGLE = '.pixonaut-shop-mega__toggle';
const PANEL = '.pixonaut-shop-mega__panel';
/** The groups' buttons and the plain links among them, in the menu's order. */
const GROUPS = '.pixonaut-shop-mega__group';
const FOCUSABLE = 'a[href], button:not([disabled])';
/** The header the panel opens under, where the browser cannot anchor it there. */
const HEADER = '.pixonaut-shop-header';

/** The class that tells the stylesheet the script runs: one group's items at a time. */
const ENHANCED_CLASS = 'is-enhanced';

/** The custom property the stylesheet sets to 1 where the panel is a drawer. */
const DRAWER_PROPERTY = '--pixonaut-shop-mega-drawer';
const DRAWER = '1';

/** Where the panel's top goes, for a browser without CSS anchor positioning. */
const TOP_PROPERTY = '--pixonaut-shop-mega-top';
const ANCHORING = 'anchor-name: --pixonaut-shop-mega';

/** The class on `<html>` while the panel is open, which stops the page scrolling under the drawer. */
const OPEN_CLASS = 'pixonaut-shop-mega-is-open';

const OPEN = 'open';
const MOUSE = 'mouse';

function setUp( root: HTMLElement ): void {
	const toggle = root.querySelector< HTMLButtonElement >( TOGGLE );
	const panel = root.querySelector< HTMLElement >( PANEL );

	if ( ! toggle || ! panel ) {
		return;
	}

	const groups = [ ...panel.querySelectorAll< HTMLElement >( GROUPS ) ];
	const buttons = groups.filter(
		( group ): group is HTMLButtonElement => group instanceof HTMLButtonElement,
	);

	const isDrawer = (): boolean =>
		getComputedStyle( root ).getPropertyValue( DRAWER_PROPERTY ).trim() === DRAWER;
	const isOpen = (): boolean => panel.matches( ':popover-open' );

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

	/** The panel's visible links and buttons, which Tab cycles through while it is open. */
	const focusables = (): HTMLElement[] =>
		[ ...panel.querySelectorAll< HTMLElement >( FOCUSABLE ) ].filter(
			( element ) => element.getClientRects().length > 0,
		);

	root.classList.add( ENHANCED_CLASS );
	toggle.setAttribute( 'aria-expanded', 'false' );
	show( isDrawer() ? null : ( buttons[ 0 ] ?? null ) );

	panel.addEventListener( 'toggle', ( event ) => {
		const opened = ( event as ToggleEvent ).newState === OPEN;

		toggle.setAttribute( 'aria-expanded', String( opened ) );
		document.documentElement.classList.toggle( OPEN_CLASS, opened );

		if ( opened ) {
			if ( ! CSS.supports( ANCHORING ) ) {
				const bottom = root.closest( HEADER )?.getBoundingClientRect().bottom ?? 0;
				panel.style.setProperty( TOP_PROPERTY, `${ bottom }px` );
			}
			if ( isDrawer() ) {
				show( null );
			}
			fitLayout();
			( groups[ 0 ] ?? focusables()[ 0 ] )?.focus();
			return;
		}

		const active = document.activeElement;
		if ( ! active || active === document.body || panel.contains( active ) ) {
			toggle.focus();
		}
	} );

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
