/**
 * The grid while an archive's new results are on their way
 * (`templates/archive-product.html`). WooCommerce filters, sorts and pages
 * the archive in place, through the Interactivity API's router: it fetches
 * the new page and swaps the grid, the count, the chips and the drawer's
 * content, and only then moves the address to the new one.
 *
 * The router shows its loading bar once a page it fetches takes a moment,
 * and only then: a change that asks for the results already shown, such as
 * a price typed back to what it was, fetches nothing. While the bar runs,
 * the page's root has a class that shows the cards as skeletons
 * (`styles/_archive.css`), and the results say they are busy.
 *
 * The drawer stays open meanwhile. As the new drawer comes in, its sections
 * stay as the customer last left them, though the new drawer opens only those
 * with a chosen value, and the focus stays on the control they last used,
 * though the new drawer may have made it anew.
 */

const RESULTS = '.pixonaut-shop-archive-results';
const PANEL = '.pixonaut-shop-archive-filters';
const CHOSEN = '.pixonaut-shop-archive-chosen';
const SORTING = '.wp-block-woocommerce-catalog-sorting';
const PAGINATION_LINK = '.wp-block-query-pagination a[href]';
/** The drawer's "Clear all" (`inc/Archive/FilterDrawer.php`), a button. */
const CLEAR_BUTTON = '.pixonaut-shop-filters-clear';
/** A section of the drawer, named by its key (`inc/Archive/FilterSections.php`). */
const SECTION_KEY = 'data-pixonaut-shop-section';
const SECTION = `details[${ SECTION_KEY }]`;
/** The router's loading bar, and its class while a page it fetches is on its way. */
const ROUTER_BAR = '.wp-interactivity-router-loading-bar';
const ROUTER_BAR_RUNNING = 'start-animation';
/** On the page's root: WooCommerce's script owns the classes inside the archive. */
const LOADING_CLASS = 'is-loading-products';
/**
 * How long the drawer is kept for new results: the router's own limit,
 * after which it loads the page instead.
 */
const KEEP_LIMIT_MS = 10_000;

/** The drawer as the customer last left it. */
interface DrawerState {
	/** Whether each section is open, by its key. */
	sections: Map< string, boolean >;
	/** The id of the control they last used in it, if it has one. */
	focus: string | null;
}

interface Kept {
	/** The address of the results shown, which the router replaces once the new ones are in. */
	from: string;
	until: number;
	drawer: DrawerState;
}

let kept: Kept | null = null;

function sectionsOf( panel: Element | null ): HTMLDetailsElement[] {
	return Array.from( panel?.querySelectorAll< HTMLDetailsElement >( SECTION ) ?? [] );
}

function drawerState(): DrawerState {
	const panel = document.querySelector( PANEL );
	const active = document.activeElement;

	return {
		sections: new Map(
			sectionsOf( panel ).map( ( section ) => [
				section.getAttribute( SECTION_KEY ) ?? '',
				section.open,
			] ),
		),
		focus: active !== null && panel?.contains( active ) && active.id !== '' ? active.id : null,
	};
}

/** Notes the drawer as it is now, for the results a change asked for. */
function keep(): void {
	if ( kept === null ) {
		kept = { from: window.location.href, until: 0, drawer: drawerState() };
		window.requestAnimationFrame( watch );
	} else {
		kept.drawer = drawerState();
	}

	kept.until = Date.now() + KEEP_LIMIT_MS;
}

/** Whether the drawer the customer left is still the one on the page: the new results are not in yet. */
function isWaiting(): boolean {
	return kept !== null && window.location.href === kept.from;
}

/** Puts the new drawer back as the customer left the one it replaced. */
function restore( drawer: DrawerState ): void {
	const panel = document.querySelector( PANEL );

	if ( ! panel ) {
		return;
	}

	for ( const section of sectionsOf( panel ) ) {
		const open = drawer.sections.get( section.getAttribute( SECTION_KEY ) ?? '' );

		// A section the old drawer did not have keeps the new drawer's choice.
		if ( open !== undefined ) {
			section.open = open;
		}
	}

	// Only a focus the swap lost: the customer may have moved it on meanwhile.
	const active = document.activeElement;

	if ( drawer.focus === null || ( active !== null && active !== document.body ) ) {
		return;
	}

	// By id: a nested value's id is its own (`NestedAttributeFilters`).
	const control = document.getElementById( drawer.focus );

	if ( control && panel.contains( control ) ) {
		control.focus( { preventScroll: true } );
	}
}

/**
 * Until the address moves to the new results, or the limit passes: a change
 * that asks for the results already shown never moves it.
 */
function watch(): void {
	if ( kept === null ) {
		return;
	}

	if ( ! isWaiting() ) {
		const { drawer } = kept;
		kept = null;
		restore( drawer );

		return;
	}

	if ( Date.now() > kept.until ) {
		kept = null;

		return;
	}

	window.requestAnimationFrame( watch );
}

function isIn( target: EventTarget | null, selector: string ): boolean {
	return target instanceof Element && target.closest( selector ) !== null;
}

/** A click the router takes: a plain one, not one that opens a new tab or window. */
function isPlainClick( event: MouseEvent ): boolean {
	return (
		event.button === 0 && ! event.metaKey && ! event.ctrlKey && ! event.shiftKey && ! event.altKey
	);
}

/** Shows the skeletons while the router's bar runs. */
function followRouterBar(): void {
	const bar = document.querySelector( ROUTER_BAR );

	if ( ! bar ) {
		return;
	}

	new MutationObserver( () => {
		const loading = bar.classList.contains( ROUTER_BAR_RUNNING );
		const results = document.querySelector( RESULTS );

		document.documentElement.classList.toggle( LOADING_CLASS, loading );

		if ( loading ) {
			results?.setAttribute( 'aria-busy', 'true' );
		} else {
			results?.removeAttribute( 'aria-busy' );
		}
	} ).observe( bar, { attributes: true, attributeFilter: [ 'class' ] } );
}

// In the capture phase, before WooCommerce's own handlers ask for the new
// results. A price typed into its field asks a moment after the last key,
// with no `change` while the field keeps the focus, so every key counts.
for ( const type of [ 'change', 'input' ] ) {
	document.addEventListener(
		type,
		( event ) => isIn( event.target, `${ PANEL }, ${ CHOSEN }, ${ SORTING }` ) && keep(),
		true,
	);
}

document.addEventListener(
	'click',
	( event ) => {
		// A chip, "Clear all" beside the chips and in the drawer, and the pages.
		if (
			isIn( event.target, `${ CHOSEN } button, ${ CLEAR_BUTTON }` ) ||
			( isIn( event.target, `${ RESULTS } ${ PAGINATION_LINK }` ) && isPlainClick( event ) )
		) {
			keep();
		}
	},
	true,
);

// A section opened or closed, or the focus moved on, while the results are
// on their way: the drawer is kept as the customer left it last. Once the new
// drawer is in, its own sections opening and closing are not the customer's.
for ( const type of [ 'toggle', 'focusin' ] ) {
	document.addEventListener(
		type,
		( event ) => {
			if ( kept !== null && isWaiting() && isIn( event.target, PANEL ) ) {
				kept.drawer = drawerState();
			}
		},
		true,
	);
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', followRouterBar );
} else {
	followRouterBar();
}
