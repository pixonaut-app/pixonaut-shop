/**
 * The grid while an archive's new results are on their way
 * (`templates/archive-product.html`). WooCommerce filters, sorts and pages
 * the archive in place, through the Interactivity API's router: it fetches
 * the new page and swaps the grid, the count, the chips and the drawer's
 * content, and only then moves the address to the new one. Until then the
 * page's root has a class that shows the cards as skeletons
 * (`styles/_components.css`), and the results say they are busy.
 *
 * The drawer stays open meanwhile. Its sections stay as the customer left
 * them, though the new drawer opens only those with a chosen value, and the
 * focus stays on the control they used, though the new drawer may have
 * made it anew.
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
/** On the page's root: WooCommerce's script owns the classes inside the archive. */
const LOADING_CLASS = 'is-loading-products';
/**
 * How long new results may take: the router's own limit, after which it
 * loads the page instead. A change that asks for the results already shown,
 * such as a price typed back to what it was, fetches nothing; the skeletons
 * go then too.
 */
const LOADING_LIMIT_MS = 10_000;

/** The drawer as the customer left it when they made the change. */
interface DrawerState {
	/** The keys of its open sections. */
	openSections: string[];
	/** The id of the control they used in it, if it has one. */
	focus: string | null;
}

interface Loading {
	/** The address the results are for, which the router replaces once the new ones are in. */
	from: string;
	until: number;
	drawer: DrawerState;
}

let loading: Loading | null = null;

function drawerState(): DrawerState {
	const panel = document.querySelector( PANEL );
	const active = document.activeElement;

	return {
		openSections: Array.from( panel?.querySelectorAll< HTMLDetailsElement >( SECTION ) ?? [] )
			.filter( ( section ) => section.open )
			.map( ( section ) => section.getAttribute( SECTION_KEY ) ?? '' ),
		focus: active !== null && panel?.contains( active ) && active.id !== '' ? active.id : null,
	};
}

function start(): void {
	if ( loading === null ) {
		loading = { from: window.location.href, until: 0, drawer: drawerState() };
		window.requestAnimationFrame( watch );
	}

	loading.until = Date.now() + LOADING_LIMIT_MS;
	document.documentElement.classList.add( LOADING_CLASS );
	document.querySelector( RESULTS )?.setAttribute( 'aria-busy', 'true' );
}

function finish( drawer: DrawerState ): void {
	loading = null;
	document.documentElement.classList.remove( LOADING_CLASS );
	document.querySelector( RESULTS )?.removeAttribute( 'aria-busy' );

	const panel = document.querySelector( PANEL );

	if ( ! panel ) {
		return;
	}

	for ( const section of panel.querySelectorAll< HTMLDetailsElement >( SECTION ) ) {
		if ( drawer.openSections.includes( section.getAttribute( SECTION_KEY ) ?? '' ) ) {
			section.open = true;
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

/** Until the address moves to the new results, or the limit passes. */
function watch(): void {
	if ( loading === null ) {
		return;
	}

	if ( window.location.href !== loading.from || Date.now() > loading.until ) {
		finish( loading.drawer );

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

// In the capture phase, before WooCommerce's own handlers ask for the new
// results. A price typed into its field asks a moment after the last key,
// with no `change` while the field keeps the focus, so every key counts.
for ( const type of [ 'change', 'input' ] ) {
	document.addEventListener(
		type,
		( event ) => isIn( event.target, `${ PANEL }, ${ CHOSEN }, ${ SORTING }` ) && start(),
		true,
	);
}

// A chip, "Clear all" beside the chips and in the drawer, and the pages.
document.addEventListener(
	'click',
	( event ) => {
		if (
			isIn( event.target, `${ CHOSEN } button, ${ CLEAR_BUTTON }` ) ||
			( isIn( event.target, `${ RESULTS } ${ PAGINATION_LINK }` ) && isPlainClick( event ) )
		) {
			start();
		}
	},
	true,
);
