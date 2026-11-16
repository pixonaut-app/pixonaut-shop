/**
 * Opening and closing the header's panels: the mega header's (`src/header/mega-menu.ts`) and
 * the search's (`src/header/search.ts`). Where the browser has the Popover API, the button and
 * the close button open and close a panel by their `popovertarget`, without script, and this
 * follows the popover's `toggle` event. Where it has not (Safari before 17), those attributes
 * do nothing and `:popover-open` is a selector the browser throws on, so this opens and closes
 * the panel itself with a class, which the stylesheet shows it by, and sets the button's
 * `aria-expanded`. There it also does what the browser does for a popover: Escape and a press
 * outside close the panel, and opening one panel closes the others.
 */

/** The class that shows the panel in a browser without the Popover API. */
export const FALLBACK_OPEN_CLASS = 'is-open';

const OPEN = 'open';

/** The parts of the elements this reads and writes, so a test can stand in for them. */
export interface PanelElements {
	panel: Pick<
		HTMLElement,
		'addEventListener' | 'matches' | 'hidePopover' | 'classList' | 'contains'
	>;
	toggle: Pick< HTMLElement, 'addEventListener' | 'setAttribute' | 'contains' >;
	/** The buttons inside the panel that close it. */
	closers: Pick< HTMLElement, 'addEventListener' >[];
}

/** The page the panels share, as an event target, so a test can stand in for `document`. */
export type PanelPage = Pick< EventTarget, 'addEventListener' | 'dispatchEvent' >;

/** What a panel opening without the Popover API tells the page, so every other panel closes. */
export const PANEL_OPENED_EVENT = 'pixonaut-shop-panel-opened';

const ESCAPE = 'Escape';

export interface PanelControl {
	isOpen(): boolean;
	close(): void;
}

/** Whether elements have the Popover API: pass `HTMLElement.prototype`. */
export function supportsPopover( prototype: object ): boolean {
	return 'popover' in prototype;
}

/**
 * Opens and closes the panel, calling `changed` with whether it is now open.
 */
export function controlPanel(
	{ panel, toggle, closers }: PanelElements,
	supported: boolean,
	changed: ( opened: boolean ) => void,
	page?: PanelPage,
): PanelControl {
	if ( supported ) {
		panel.addEventListener( 'toggle', ( event ) => {
			changed( ( event as ToggleEvent ).newState === OPEN );
		} );

		return {
			isOpen: () => panel.matches( ':popover-open' ),
			close: () => panel.hidePopover(),
		};
	}

	let open = false;
	const set = ( opened: boolean ): void => {
		if ( opened === open ) {
			return;
		}
		open = opened;
		panel.classList.toggle( FALLBACK_OPEN_CLASS, opened );
		toggle.setAttribute( 'aria-expanded', String( opened ) );
		if ( opened ) {
			page?.dispatchEvent( new CustomEvent( PANEL_OPENED_EVENT, { detail: panel } ) );
		}
		changed( opened );
	};

	toggle.addEventListener( 'click', () => set( ! open ) );
	for ( const closer of closers ) {
		closer.addEventListener( 'click', () => set( false ) );
	}

	page?.addEventListener( PANEL_OPENED_EVENT, ( event ) => {
		if ( ( event as CustomEvent ).detail !== panel ) {
			set( false );
		}
	} );
	page?.addEventListener( 'keydown', ( event ) => {
		if ( open && ( event as KeyboardEvent ).key === ESCAPE ) {
			set( false );
		}
	} );
	page?.addEventListener( 'pointerdown', ( event ) => {
		const target = event.target as Node | null;
		if ( open && ! panel.contains( target ) && ! toggle.contains( target ) ) {
			set( false );
		}
	} );

	return {
		isOpen: () => open,
		close: () => set( false ),
	};
}
