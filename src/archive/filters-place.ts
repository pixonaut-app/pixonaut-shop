/**
 * Keeps a customer's place while they filter an archive
 * (`patterns/product-filters.php`). WooCommerce loads the page again for
 * every value ticked, which would send them back to the top and close the
 * drawer the filters open in after each tick. The place is noted as a filter
 * changes and taken up again as the next page loads: the page's scroll, and
 * the drawer open where it was scrolled to, with the same sections open and
 * the focus on the control the customer used.
 */

import { archivePath } from './archive-path';

const PANEL = '.pixonaut-shop-archive-filters';
const CHOSEN = '.pixonaut-shop-archive-chosen';
/** The drawer's "Clear all" (`inc/Archive/FilterDrawer.php`), a button, which loads the page without a `change`. */
const CLEAR_BUTTON = '.pixonaut-shop-filters-clear';
const OPEN_BUTTON = '.wc-block-product-filters__open-overlay';
const DIALOG_CONTENT = '.wc-block-product-filters__overlay-content';
/** A section of the drawer, named by its key (`inc/Archive/FilterSections.php`). */
const SECTION_KEY = 'data-pixonaut-shop-section';
const SECTION = `details[${ SECTION_KEY }]`;
/** WooCommerce's class on the panel while its dialog is open. */
const OPENED_CLASS = 'is-overlay-opened';
/** Skips the dialog's slide while it is opened again on a new page. */
const RESTORING_CLASS = 'is-restoring-filters-place';

const STORAGE_KEY = 'pixonaut-shop-filters-place';
/** A place older than this was left by a change that loaded no page. */
const PLACE_LIFETIME_MS = 10_000;
/** How often, and how many times, the button is pressed until WooCommerce's script answers. */
const OPEN_RETRY_MS = 100;
const OPEN_ATTEMPTS = 50;

interface Place {
	/** The archive's path, without the page it was on. */
	path: string;
	at: number;
	scroll: number;
	/** How far the open drawer was scrolled, or null with the drawer closed. */
	dialogScroll: number | null;
	/** The keys of the drawer's open sections. */
	sections: string[];
	/** The id of the control the customer used in the drawer, if it has one. */
	focus: string | null;
}

function notePlace(): void {
	const panel = document.querySelector( PANEL );
	const opened = panel?.classList.contains( OPENED_CLASS ) ?? false;
	const active = document.activeElement;
	const place: Place = {
		path: archivePath( window.location.pathname ),
		at: Date.now(),
		scroll: window.scrollY,
		dialogScroll: opened ? ( panel?.querySelector( DIALOG_CONTENT )?.scrollTop ?? 0 ) : null,
		sections: Array.from( panel?.querySelectorAll< HTMLDetailsElement >( SECTION ) ?? [] )
			.filter( ( section ) => section.open )
			.map( ( section ) => section.getAttribute( SECTION_KEY ) ?? '' ),
		focus:
			opened && active !== null && panel?.contains( active ) && active.id !== '' ? active.id : null,
	};

	try {
		window.sessionStorage.setItem( STORAGE_KEY, JSON.stringify( place ) );
	} catch {
		// Without storage the next page starts at its top, as it would have.
	}
}

function notedPlace(): Place | null {
	try {
		const stored = window.sessionStorage.getItem( STORAGE_KEY );
		window.sessionStorage.removeItem( STORAGE_KEY );
		const place = stored === null ? null : ( JSON.parse( stored ) as Place );

		return place !== null &&
			place.path === archivePath( window.location.pathname ) &&
			Date.now() - place.at < PLACE_LIFETIME_MS
			? place
			: null;
	} catch {
		return null;
	}
}

/** Opens the sections the customer had open; those with a chosen value are open already. */
function openSections( panel: Element, keys: string[] ): void {
	for ( const section of panel.querySelectorAll< HTMLDetailsElement >( SECTION ) ) {
		if ( keys.includes( section.getAttribute( SECTION_KEY ) ?? '' ) ) {
			section.open = true;
		}
	}
}

/**
 * Opens the drawer as the customer left it. WooCommerce's script answers the
 * button only once it has started, so the button is pressed until the drawer
 * opens, and never again after: the customer may close it right away.
 */
function openDialog( panel: Element, scroll: number, focus: string | null ): void {
	const root = document.documentElement;
	let attemptsLeft = OPEN_ATTEMPTS;
	let timer = 0;

	const finish = (): void => {
		window.clearTimeout( timer );
		watcher.disconnect();
		// The slide is back for the customer's own opening and closing.
		window.requestAnimationFrame( () => root.classList.remove( RESTORING_CLASS ) );
	};

	const watcher = new MutationObserver( () => {
		if ( ! panel.classList.contains( OPENED_CLASS ) ) {
			return;
		}

		const content = panel.querySelector( DIALOG_CONTENT );

		if ( content ) {
			content.scrollTop = scroll;
		}

		// By id: a nested value's id is its own (`NestedAttributeFilters`).
		const control = focus === null ? null : document.getElementById( focus );

		if ( control && panel.contains( control ) ) {
			control.focus( { preventScroll: true } );
		}

		finish();
	} );

	const press = (): void => {
		if ( attemptsLeft === 0 ) {
			finish();

			return;
		}

		attemptsLeft -= 1;
		panel.querySelector< HTMLButtonElement >( OPEN_BUTTON )?.click();
		timer = window.setTimeout( press, OPEN_RETRY_MS );
	};

	// On the page's root: WooCommerce's script owns the panel's own classes.
	root.classList.add( RESTORING_CLASS );
	watcher.observe( panel, { attributes: true, attributeFilter: [ 'class' ] } );
	press();
}

function takeUpPlace(): void {
	const place = notedPlace();

	if ( place === null ) {
		return;
	}

	window.scrollTo( { top: place.scroll, behavior: 'instant' } );

	const panel = document.querySelector( PANEL );

	if ( ! panel ) {
		return;
	}

	openSections( panel, place.sections ?? [] );

	if ( place.dialogScroll !== null ) {
		openDialog( panel, place.dialogScroll, place.focus ?? null );
	}
}

function isAFilter( target: EventTarget | null ): boolean {
	return target instanceof Element && target.closest( `${ PANEL }, ${ CHOSEN }` ) !== null;
}

// In the capture phase, before WooCommerce's own handler loads the next page.
// A price typed into its field loads it a moment after the last key, with no
// `change` while the field keeps the focus, so every key notes the place.
for ( const type of [ 'change', 'input' ] ) {
	document.addEventListener( type, ( event ) => isAFilter( event.target ) && notePlace(), true );
}
// A chip, "Clear all" beside the chips, and the drawer's own "Clear all".
document.addEventListener(
	'click',
	( event ) =>
		event.target instanceof Element &&
		event.target.closest( `${ CHOSEN }, ${ CLEAR_BUTTON }` ) !== null &&
		notePlace(),
	true,
);

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', takeUpPlace );
} else {
	takeUpPlace();
}
