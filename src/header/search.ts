/**
 * The header's search (`inc/Header/HeaderSearch.php`): the search icon and
 * the panel it opens under the header, which the server prints whole. The
 * panel is a popover the icon opens, so the browser opens and closes it,
 * Escape and a click outside included, and focuses its field.
 *
 * This script adds the rest. From two letters on, the panel lists the
 * first five products the results page lists for the query, asked of that
 * page's own search (`inc/Header/SearchSuggestions.php`), with their picture
 * and price, the page's count and a link to it. A product the page lists
 * once per card is suggested and counted once per card, each with the
 * card's own picture, name, price and link. The field is a combobox: the arrow keys move among the
 * suggestions, Enter opens the active one, or the results page when none is
 * active. An empty search keeps the panel open. When focus leaves the panel
 * it closes, and when it closes, focus goes back to the icon if it was in
 * the panel. Counts and "nothing found" are announced.
 *
 * Nothing in the panel moves what was already shown: the count, the link and
 * the "nothing found" line take the hint's place in one row above the list.
 *
 * A browser without the Popover API opens and closes the panel through
 * `mega-menu-panel.ts`, as the mega menu's does.
 */

import { __, _n, sprintf } from '@wordpress/i18n';

import { controlPanel, supportsPopover } from './mega-menu-panel.ts';
import { type Found, foundFrom, optionStep, type Suggestion } from './search-suggestions.ts';

const ROOT = '.pixonaut-shop-header-search';
const CLASS = 'pixonaut-shop-header-search';

/**
 * The header the panel opens under, and the custom property that tells the
 * stylesheet where the header ends, for the panel and the scrim behind it.
 */
const HEADER = '.pixonaut-shop-header';
const TOP_PROPERTY = '--pixonaut-shop-header-search-top';

/** From how many letters the panel suggests. */
const MIN_LETTERS = 2;

/** How long typing pauses before the suggestions are fetched, in milliseconds. */
const TYPING_PAUSE_MS = 200;

/** The value of the query variable that asks the results page for its suggestions. */
const ASKS_FOR_SUGGESTIONS = '1';

/** The active suggestion's class, and no suggestion's index. */
const ACTIVE_CLASS = 'is-active';
const NO_OPTION = -1;

const ESCAPE = 'Escape';
const ENTER = 'Enter';

/**
 * Puts a price's markup in an element, parsed where no script runs. The
 * server keeps it to the inline markup a price holds
 * (`SearchSuggestions::PRICE_MARKUP`).
 */
function appendPrice( element: HTMLElement, html: string ): void {
	const parsed = new DOMParser().parseFromString( html, 'text/html' ).body;
	while ( parsed.firstChild ) {
		element.append( parsed.firstChild );
	}
}

function part< T extends Element >( root: ParentNode, name: string ): T | null {
	return root.querySelector< T >( `.${ CLASS }__${ name }` );
}

/**
 * A suggestion's row: its picture, its name and its price. An option holds
 * no link, which a screen reader would not reach inside a listbox; a click
 * on it opens the product, as Enter does.
 */
function optionFor( suggestion: Suggestion, id: string ): HTMLLIElement {
	const option = document.createElement( 'li' );
	option.id = id;
	option.className = `${ CLASS }__option`;
	option.setAttribute( 'role', 'option' );
	option.setAttribute( 'aria-selected', 'false' );

	const picture = document.createElement( 'span' );
	picture.className = `${ CLASS }__picture`;
	if ( suggestion.image ) {
		const image = document.createElement( 'img' );
		image.src = suggestion.image;
		image.alt = '';
		image.loading = 'lazy';
		image.decoding = 'async';
		picture.append( image );
	}

	const name = document.createElement( 'span' );
	name.className = `${ CLASS }__name`;
	name.textContent = suggestion.name;

	const price = document.createElement( 'span' );
	price.className = `${ CLASS }__price`;
	appendPrice( price, suggestion.price );

	option.append( picture, name, price );
	option.addEventListener( 'click', () => window.location.assign( suggestion.url ) );

	return option;
}

function setUp( root: HTMLElement ): void {
	const toggle = part< HTMLButtonElement >( root, 'toggle' );
	const panel = part< HTMLElement >( root, 'panel' );
	const form = part< HTMLFormElement >( root, 'form' );
	const field = part< HTMLInputElement >( root, 'input' );
	const hint = part< HTMLElement >( root, 'hint' );
	const count = part< HTMLElement >( root, 'count' );
	const all = part< HTMLAnchorElement >( root, 'all' );
	const none = part< HTMLElement >( root, 'none' );
	const list = part< HTMLUListElement >( root, 'list' );
	const help = part< HTMLElement >( root, 'help' );
	const announce = part< HTMLElement >( root, 'announce' );
	const suggestionsVar = panel?.dataset.suggestions;

	if (
		! toggle ||
		! panel ||
		! form ||
		! field ||
		! hint ||
		! count ||
		! all ||
		! none ||
		! list ||
		! help ||
		! announce ||
		! suggestionsVar
	) {
		return;
	}

	const found = new Map< string, Found >();
	let shown: Suggestion[] = [];
	let active = NO_OPTION;
	let pending: AbortController | null = null;
	let timer: ReturnType< typeof setTimeout > | undefined;

	form.noValidate = true;

	const resultsUrl = ( query: string ): string => {
		const url = new URL( form.action );
		url.searchParams.set( 's', query );
		for ( const input of form.querySelectorAll< HTMLInputElement >( 'input[type="hidden"]' ) ) {
			url.searchParams.set( input.name, input.value );
		}
		return url.toString();
	};

	const setActive = ( index: number ): void => {
		active = index;
		const options = [ ...list.children ] as HTMLElement[];
		options.forEach( ( option, position ) => {
			const isActive = position === index;
			option.classList.toggle( ACTIVE_CLASS, isActive );
			option.setAttribute( 'aria-selected', String( isActive ) );
			if ( isActive ) {
				option.scrollIntoView( { block: 'nearest' } );
			}
		} );
		const option = options[ index ];
		if ( option ) {
			field.setAttribute( 'aria-activedescendant', option.id );
		} else {
			field.removeAttribute( 'aria-activedescendant' );
		}
	};

	/** Shows one of the status row's lines (or the hint), and the list or the help under it. */
	const showState = ( state: 'hint' | 'found' | 'none' ): void => {
		hint.hidden = state !== 'hint';
		count.hidden = state !== 'found';
		all.hidden = state !== 'found';
		none.hidden = state !== 'none';
		list.hidden = state !== 'found';
		help.hidden = state !== 'none';
		field.setAttribute( 'aria-expanded', String( state === 'found' ) );
		if ( state !== 'found' ) {
			list.replaceChildren();
			shown = [];
			setActive( NO_OPTION );
		}
	};

	const show = ( query: string, { suggestions, total }: Found ): void => {
		if ( suggestions.length === 0 ) {
			none.textContent = sprintf(
				/* translators: %s: what the shopper searched for. */
				__( 'No products match “%s”', 'pixonaut-shop' ),
				query,
			);
			showState( 'none' );
			announce.textContent = none.textContent;
			return;
		}

		count.textContent = sprintf(
			/* translators: %s: how many products match the search. */
			_n( '%s product', '%s products', total, 'pixonaut-shop' ),
			total.toLocaleString(),
		);
		all.textContent = sprintf(
			/* translators: %s: how many products match the search. */
			_n( 'Show the %s result', 'Show all %s results', total, 'pixonaut-shop' ),
			total.toLocaleString(),
		);
		all.href = resultsUrl( query );

		shown = suggestions;
		list.replaceChildren(
			...suggestions.map( ( suggestion, index ) =>
				optionFor( suggestion, `${ list.id }-${ index }` ),
			),
		);
		showState( 'found' );
		setActive( NO_OPTION );
		announce.textContent = sprintf(
			/* translators: %s: how many suggestions the search shows. */
			_n(
				'%s suggestion. Use the up and down arrows to pick it.',
				'%s suggestions. Use the up and down arrows to pick one.',
				suggestions.length,
				'pixonaut-shop',
			),
			suggestions.length.toLocaleString(),
		);
	};

	const search = async ( query: string ): Promise< void > => {
		const known = found.get( query );
		if ( known ) {
			show( query, known );
			return;
		}

		pending?.abort();
		const controller = new AbortController();
		pending = controller;

		// The results page's own search, so the panel lists and counts what that page does.
		const url = new URL( resultsUrl( query ) );
		url.searchParams.set( suggestionsVar, ASKS_FOR_SUGGESTIONS );

		try {
			const response = await fetch( url, {
				signal: controller.signal,
				credentials: 'same-origin',
			} );
			if ( ! response.ok ) {
				throw new Error( `The search answered ${ response.status }.` );
			}
			const result = foundFrom( await response.json() );
			if ( ! result ) {
				throw new Error( 'The search answered something other than suggestions.' );
			}
			found.set( query, result );
			// Only the latest query's answer is shown, and only while it is still in the field.
			if ( pending === controller && field.value.trim() === query ) {
				show( query, result );
			}
		} catch {
			// A newer query aborted this one, or the request failed: without suggestions for
			// what is in the field, the panel shows the hint again, and Enter still searches.
			if ( pending === controller && ! controller.signal.aborted ) {
				showState( 'hint' );
			}
		}
	};

	field.addEventListener( 'input', () => {
		clearTimeout( timer );
		const query = field.value.trim();
		// What is listed belongs to the query before this one: it stays in view until the new
		// suggestions replace it, but none can be picked, and "show all" searches the new query.
		shown = [];
		setActive( NO_OPTION );
		all.href = resultsUrl( query );
		if ( query.length < MIN_LETTERS ) {
			pending?.abort();
			showState( 'hint' );
			announce.textContent = '';
			return;
		}
		timer = setTimeout( () => void search( query ), TYPING_PAUSE_MS );
	} );

	field.addEventListener( 'keydown', ( event ) => {
		if ( event.key === ENTER && active !== NO_OPTION ) {
			const suggestion = shown[ active ];
			if ( suggestion ) {
				event.preventDefault();
				window.location.assign( suggestion.url );
			}
			return;
		}

		// Escape closes the panel at once, rather than first emptying the field.
		if ( event.key === ESCAPE ) {
			event.preventDefault();
			control.close();
			return;
		}

		const target = optionStep( event.key, active, shown.length );
		if ( target !== null ) {
			event.preventDefault();
			setActive( target );
		}
	} );

	// An empty search keeps the panel open, without the browser's validation message.
	form.addEventListener( 'submit', ( event ) => {
		if ( field.value.trim() === '' ) {
			event.preventDefault();
			field.focus();
		}
	} );

	/** Hangs the panel, and the scrim, from the header's bottom edge, or the window's top once it has scrolled away. */
	const place = (): void => {
		const bottom = Math.max( 0, root.closest( HEADER )?.getBoundingClientRect().bottom ?? 0 );
		panel.style.setProperty( TOP_PROPERTY, `${ bottom }px` );
	};

	const changed = ( opened: boolean ): void => {
		toggle.setAttribute( 'aria-expanded', String( opened ) );

		if ( opened ) {
			place();
			window.addEventListener( 'scroll', place, { passive: true } );
			window.addEventListener( 'resize', place );
			field.focus();
			return;
		}

		window.removeEventListener( 'scroll', place );
		window.removeEventListener( 'resize', place );

		setActive( NO_OPTION );
		const current = document.activeElement;
		if ( ! current || current === document.body || panel.contains( current ) ) {
			toggle.focus();
		}
	};

	const popover = supportsPopover( HTMLElement.prototype );
	const control = controlPanel(
		{
			panel,
			toggle,
			closers: [ ...panel.querySelectorAll< HTMLElement >( `.${ CLASS }__close` ) ],
		},
		popover,
		changed,
		document,
	);

	toggle.setAttribute( 'aria-expanded', 'false' );

	// Placed before the browser shows it, so it never shows at the window's top first.
	panel.addEventListener( 'beforetoggle', ( event ) => {
		if ( ( event as ToggleEvent ).newState === 'open' ) {
			place();
		}
	} );

	// Focus that leaves the panel, other than for the icon, closes it.
	panel.addEventListener( 'focusout', ( event ) => {
		const next = event.relatedTarget;
		if ( next instanceof Node && ! panel.contains( next ) && next !== toggle && control.isOpen() ) {
			control.close();
		}
	} );
}

for ( const root of document.querySelectorAll< HTMLElement >( ROOT ) ) {
	setUp( root );
}
