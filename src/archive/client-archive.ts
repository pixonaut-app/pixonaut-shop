/**
 * An archive filtered, sorted and counted in the browser, from the index of
 * its cards Pixonaut for WooCommerce publishes (`inc/Archive/ClientArchive.php`).
 *
 * The server renders the archive for its address first. Once the index is
 * in, the archive takes over: the cards the server drew stay, the pages give
 * way to cards that load as the shopper scrolls (`client/feed.ts`), and every
 * tick, chip, "Clear all", price and sorting updates the grid, the counts
 * and the drawer at once, with no request (`client/drawer.ts`). The address
 * follows each choice in its search parameters, as the server reads them
 * (`index/address.ts`): replaced while values are ticked in the open drawer,
 * a new entry for each other change, so back and forward step through them
 * and restore the scroll position.
 *
 * Without an index (none yet, an error, a slow network) nothing changes:
 * WooCommerce filters the archive on the server, as it always has.
 */

import { CARD, drawCard, modelOf } from './client/cards.ts';
import {
	CHIP_FILTER,
	CHIP_VALUE,
	CHOSEN,
	type ChipsState,
	CLIENT_CHOSEN_CLASS,
	chipsFor,
	chipsOf,
	type DrawerState,
	drawerOf,
	FILTER_KEY,
	PANEL,
	PRICE_CHIP,
	syncChips,
	syncCounts,
	syncDrawer,
	type Words,
} from './client/drawer.ts';
import { Feed, type FeedWords } from './client/feed.ts';
import { CLIENT_ARCHIVE_ATTRIBUTE } from './client-archive-flag.ts';
import { addressOf, choiceFrom } from './index/address.ts';
import { type Choice, counts, type Shown, shownCards } from './index/engine.ts';
import { formatAmount, type PriceTemplates } from './index/price-markup.ts';
import type { ArchiveIndex, IndexPointer } from './index/types.ts';

const POINTER_ID = 'pixonaut-archive-index';
const CONFIG_ID = 'pixonaut-shop-archive-config';
/** How long the index may take before the archive stays with the server. */
const INDEX_TIMEOUT_MS = 8000;
const RESULTS = 'main .pixonaut-shop-archive-results';
const GRID = `${ RESULTS } .wc-block-product-template`;
const PAGINATION = `${ RESULTS } .wp-block-query-pagination`;
const SORTING = 'main .wc-block-catalog-sorting';
const PRICE_SLIDER = '.wc-block-product-filter-price-slider';
const DRAWER_CLEAR = '.pixonaut-shop-filters-clear';
const CHOSEN_CLEAR = `.${ CLIENT_CHOSEN_CLASS } .wc-block-product-filter-clear-button`;
/** WooCommerce's class on the filters' panel while its drawer is open. */
const OPENED_CLASS = 'is-overlay-opened';
const NO_PRODUCTS_CLASS = 'pixonaut-shop-archive-none';
/** How often the scroll position is noted for back and forward. */
const SCROLL_NOTE_MS = 300;
/** The events WooCommerce's price slider asks for new results on. */
const PRICE_EVENTS = [ 'mouseup', 'keyup', 'touchend' ];

interface Config {
	pageSize: number;
	autoLoads: number;
	defaultSort: string;
	prices: PriceTemplates;
	saleBadge: string;
	noProducts: string;
	text: Words & FeedWords & { fetching: string };
}

/** What the archive keeps in each history entry it makes. */
interface Entry {
	drawn: number;
	scroll: number;
}

const HISTORY_KEY = 'pixonautShopArchive';

function readJson< T >( id: string ): T | null {
	try {
		const element = document.getElementById( id );

		return element ? ( JSON.parse( element.textContent ?? '' ) as T ) : null;
	} catch {
		return null;
	}
}

function isIndex( data: unknown ): data is ArchiveIndex {
	const index = data as Partial< ArchiveIndex > | null;

	return (
		typeof index === 'object' &&
		index !== null &&
		Array.isArray( index.cards ) &&
		Array.isArray( index.filters ) &&
		Array.isArray( index.sorts ) &&
		Array.isArray( index.images ) &&
		typeof index.currency === 'object'
	);
}

async function loadIndex( pointer: IndexPointer ): Promise< ArchiveIndex | null > {
	const controller = new AbortController();
	const timer = window.setTimeout( () => controller.abort(), INDEX_TIMEOUT_MS );

	try {
		const response = await fetch( pointer.url, {
			credentials: 'same-origin',
			signal: controller.signal,
		} );
		const data: unknown = response.ok ? await response.json() : null;

		return isIndex( data ) ? data : null;
	} catch {
		return null;
	} finally {
		window.clearTimeout( timer );
	}
}

function entryOf( state: unknown ): Entry | null {
	const entry = ( state as Record< string, unknown > | null )?.[ HISTORY_KEY ] as Entry | undefined;

	return entry && typeof entry.drawn === 'number' && typeof entry.scroll === 'number'
		? entry
		: null;
}

function copyOf( choice: Choice ): Choice {
	return {
		...choice,
		values: new Map( [ ...choice.values ].map( ( [ key, ids ] ) => [ key, new Set( ids ) ] ) ),
	};
}

class ClientArchive {
	private choice: Choice;
	private shown: Shown[] = [];
	private readonly feed: Feed;
	private readonly drawer: DrawerState;
	private readonly chips: ChipsState | null;
	private readonly none: HTMLElement;
	private readonly model: HTMLElement;
	/** Whether a choice made in the drawer since it opened has its own history entry yet. */
	private drawerEntry = false;
	private scrollTimer = 0;
	private readonly index: ArchiveIndex;
	private readonly config: Config;
	private readonly grid: HTMLElement;

	constructor( index: ArchiveIndex, config: Config, grid: HTMLElement, panel: Element ) {
		this.index = index;
		this.config = config;
		this.grid = grid;
		this.model = modelOf( grid.querySelector( CARD ) as Element );
		this.choice = choiceFrom( new URL( window.location.href ), index.filters, config.defaultSort );
		this.drawer = drawerOf( panel, index );
		const chosen = document.querySelector( `main ${ CHOSEN }` );
		this.chips = chosen ? chipsOf( chosen ) : null;

		// The pages give way to cards that load as the shopper scrolls; the block's own display would keep it.
		for ( const pagination of document.querySelectorAll< HTMLElement >( PAGINATION ) ) {
			pagination.hidden = true;
			pagination.style.display = 'none';
		}

		this.none = document.createElement( 'div' );
		this.none.className = NO_PRODUCTS_CLASS;
		this.none.hidden = true;
		this.none.innerHTML = config.noProducts;

		this.feed = new Feed( {
			grid,
			pageSize: config.pageSize,
			autoLoads: config.autoLoads,
			words: config.text,
			draw: ( place ) => this.drawAt( place ),
			onDrawn: () => this.noteEntry(),
		} );
		grid.parentElement?.append( this.none );

		this.shown = shownCards( index, this.choice );
		// The cards the server drew for the address stay as they are.
		this.feed.adopt( this.shown.length, grid.querySelectorAll( CARD ).length );
		this.render();
		this.noteEntry();
		this.listen( panel );
	}

	private drawAt( place: number ): HTMLElement {
		return drawCard( this.model, this.shown[ place ], {
			index: this.index,
			prices: this.config.prices,
			saleBadge: this.config.saleBadge,
			choice: this.choice,
		} );
	}

	/** The drawer, the chips and the counts for the current choice. */
	private render(): void {
		const { index, choice } = this;
		syncDrawer( this.drawer, index, choice, counts( index, choice ) );

		const chips = chipsFor( this.drawer, index, choice, ( min, max ) =>
			this.priceLabel( min, max ),
		);

		if ( this.chips ) {
			syncChips( this.chips, chips, this.config.text );
		}

		syncCounts( this.shown.length, chips.length, this.config.text );
		this.grid.hidden = this.shown.length === 0;
		this.none.hidden = this.shown.length > 0;

		const sorting = document.querySelector< HTMLSelectElement >( `${ SORTING } select` );

		if ( sorting && sorting.value !== choice.sort ) {
			sorting.value = choice.sort;
		}
	}

	private priceLabel( min: number | null, max: number | null ): string {
		const filter = this.index.filters.find( ( known ) => known.type === 'price' );
		const amount = ( n: number ): string =>
			this.config.prices.amount
				.replace( '{{a}}', formatAmount( n, this.index.currency ) )
				.replace( /<[^>]*>/g, '' );
		const range = [ min === null ? '' : amount( min ), max === null ? '' : amount( max ) ].join(
			' – ',
		);

		return `${ filter?.label ?? '' }: ${ range }`.trim();
	}

	/** Shows a new choice, from its first cards (or as many as `keep`), and notes it in the address. */
	private apply( next: Choice, history: 'push' | 'replace' | 'none', keep = 0 ): void {
		this.choice = next;
		this.shown = shownCards( this.index, next );
		this.feed.reset( this.shown.length, keep );
		this.render();

		if ( history === 'none' ) {
			return;
		}

		const url = addressOf(
			new URL( window.location.href ),
			next,
			this.index.filters,
			this.config.defaultSort,
		);
		const state = { [ HISTORY_KEY ]: { drawn: this.feed.drawnCount, scroll: window.scrollY } };

		if ( history === 'push' ) {
			window.history.pushState( state, '', url );
		} else {
			window.history.replaceState( state, '', url );
		}
	}

	/** The history mode for a change made in the drawer while it is open, or elsewhere. */
	private historyMode(): 'push' | 'replace' {
		const open = document.querySelector( PANEL )?.classList.contains( OPENED_CLASS ) ?? false;

		if ( ! open ) {
			return 'push';
		}

		const mode = this.drawerEntry ? 'replace' : 'push';
		this.drawerEntry = true;

		return mode;
	}

	private noteEntry(): void {
		const state = {
			...( ( window.history.state as Record< string, unknown > | null ) ?? {} ),
			[ HISTORY_KEY ]: { drawn: this.feed.drawnCount, scroll: window.scrollY },
		};
		window.history.replaceState( state, '' );
	}

	private toggle( key: string, value: string, on: boolean ): void {
		const next = copyOf( this.choice );
		const values = next.values.get( key ) ?? new Set< string >();

		if ( on ) {
			values.add( value );
		} else {
			values.delete( value );
			this.clearOpenedBy( next, key );
		}

		if ( values.size > 0 ) {
			next.values.set( key, values );
		} else {
			next.values.delete( key );
		}

		this.apply( next, this.historyMode() );
	}

	/** Clears the values of each dependent attribute no chosen value of its parent opens any more. */
	private clearOpenedBy( next: Choice, parentKey: string ): void {
		const chosenParents = next.values.get( parentKey ) ?? new Set< string >();

		for ( const filter of this.index.filters ) {
			if ( filter.parent?.key !== parentKey ) {
				continue;
			}

			if ( ! filter.parent.values.some( ( id ) => chosenParents.has( id ) ) ) {
				next.values.delete( filter.key );
			}
		}
	}

	private setPrice( min: number | null, max: number | null, history: 'push' | 'replace' ): void {
		const next = copyOf( this.choice );
		next.min = min;
		next.max = max;
		this.apply( next, history );
	}

	/** Reads the price slider: an end at its limit chooses nothing. */
	private priceFromSlider( slider: Element ): { min: number | null; max: number | null } {
		const low = slider.querySelector< HTMLInputElement >( 'input[type="range"].min' );
		const high = slider.querySelector< HTMLInputElement >( 'input[type="range"].max' );
		const min = Number( low?.value );
		const max = Number( high?.value );

		return {
			min: low && min > Number( low.min ) ? min : null,
			max: high && max < Number( high.max ) ? max : null,
		};
	}

	private listen( panel: Element ): void {
		const inPanel = ( target: EventTarget | null ): target is Element =>
			target instanceof Element && panel.contains( target );
		const inSlider = ( target: EventTarget | null ): target is HTMLInputElement =>
			target instanceof HTMLInputElement && target.closest( PRICE_SLIDER ) !== null;

		// In the capture phase, before WooCommerce asks the server for new results.
		document.addEventListener(
			'change',
			( event ) => {
				const target = event.target;

				if ( target instanceof HTMLInputElement && target.hasAttribute( FILTER_KEY ) ) {
					event.stopPropagation();
					this.toggle( target.getAttribute( FILTER_KEY ) ?? '', target.value, target.checked );
				} else if ( inSlider( target ) ) {
					event.stopPropagation();

					if ( target.type === 'text' ) {
						const amount = Number( target.value.replace( /[^\d.,]/g, '' ).replace( ',', '.' ) );
						const { min, max } = this.choice;
						const valid = Number.isFinite( amount ) ? amount : null;
						this.setPrice(
							target.classList.contains( 'min' ) ? valid : min,
							target.classList.contains( 'max' ) ? valid : max,
							this.historyMode(),
						);
					}
				} else if ( target instanceof HTMLSelectElement && target.closest( SORTING ) ) {
					event.stopPropagation();
					this.apply( { ...copyOf( this.choice ), sort: target.value }, 'push' );
				} else if ( inPanel( target ) ) {
					event.stopPropagation();
				}
			},
			true,
		);

		document.addEventListener(
			'input',
			( event ) => {
				const target = event.target;

				if ( ! inSlider( target ) ) {
					return;
				}

				event.stopPropagation();

				if ( target.type === 'range' ) {
					const slider = target.closest( PRICE_SLIDER );
					const { min, max } = slider ? this.priceFromSlider( slider ) : { min: null, max: null };
					this.setPrice( min, max, this.historyMode() );
				}
			},
			true,
		);

		for ( const type of PRICE_EVENTS ) {
			document.addEventListener(
				type,
				( event ) => {
					if ( inSlider( event.target ) ) {
						event.stopPropagation();
					}
				},
				true,
			);
		}

		document.addEventListener(
			'click',
			( event ) => {
				const target = event.target instanceof Element ? event.target : null;
				const chip = target?.closest( `[${ CHIP_FILTER }]` );
				const clear = target?.closest( `${ DRAWER_CLEAR }, ${ CHOSEN_CLEAR }` );

				if ( chip ) {
					event.preventDefault();
					event.stopPropagation();
					const key = chip.getAttribute( CHIP_FILTER ) ?? '';

					if ( key === PRICE_CHIP ) {
						this.setPrice( null, null, 'push' );
					} else {
						this.toggle( key, chip.getAttribute( CHIP_VALUE ) ?? '', false );
					}
				} else if ( clear ) {
					event.preventDefault();
					event.stopPropagation();
					this.apply(
						{ values: new Map(), min: null, max: null, sort: this.choice.sort },
						this.historyMode(),
					);
				}
			},
			true,
		);

		document.addEventListener(
			'submit',
			( event ) => {
				if ( event.target instanceof Element && event.target.closest( SORTING ) ) {
					event.preventDefault();
					event.stopPropagation();
				}
			},
			true,
		);

		// A new opening of the drawer starts a new history entry with its first change.
		new MutationObserver( () => {
			if ( ! panel.classList.contains( OPENED_CLASS ) ) {
				this.drawerEntry = false;
			}
		} ).observe( panel, { attributes: true, attributeFilter: [ 'class' ] } );

		// Before the router, which would load the page again for an entry it did not make.
		window.addEventListener(
			'popstate',
			( event ) => {
				event.stopImmediatePropagation();
				const entry = entryOf( event.state );
				const next = choiceFrom(
					new URL( window.location.href ),
					this.index.filters,
					this.config.defaultSort,
				);
				this.apply( next, 'none', entry?.drawn ?? 0 );
				window.scrollTo( { top: entry?.scroll ?? 0, behavior: 'instant' } );
			},
			true,
		);

		window.addEventListener(
			'scroll',
			() => {
				window.clearTimeout( this.scrollTimer );
				this.scrollTimer = window.setTimeout( () => this.noteEntry(), SCROLL_NOTE_MS );
			},
			{ passive: true },
		);
	}
}

async function start(): Promise< void > {
	const pointer = readJson< IndexPointer >( POINTER_ID );
	const config = readJson< Config >( CONFIG_ID );
	const grid = document.querySelector< HTMLElement >( GRID );
	const panel = document.querySelector( PANEL );

	// Without a card the server drew, there is none to draw the others from.
	if ( ! pointer || ! config || ! grid?.querySelector( CARD ) || ! panel ) {
		return;
	}

	const from = window.location.href;
	const index = await loadIndex( pointer );

	// The shopper changed the archive on the server meanwhile: the page is no longer the one it renders.
	if ( ! index || window.location.href !== from || ! document.contains( grid ) ) {
		return;
	}

	document.documentElement.setAttribute( CLIENT_ARCHIVE_ATTRIBUTE, '' );
	new ClientArchive( index, config, grid, panel );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', () => void start() );
} else {
	void start();
}
