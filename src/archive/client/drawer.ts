/**
 * The filters' drawer, its chips and the toolbar of the archive filtered in
 * the browser, kept in step with the choice: each value ticked or not, with
 * its count, hidden while it would show nothing; each dependent attribute's
 * values under the value they apply under (as
 * `inc/Archive/NestedAttributeFilters.php` places them); the price slider;
 * a chip for each chosen filter; the number of chosen filters on the
 * toolbar's button, the drawer's "Show N products" and the result count.
 *
 * WooCommerce rendered all of it on the server; its script draws it again
 * only when its own state changes, which the archive keeps it from doing, so
 * the elements are changed in place and new ones copied from its own.
 */

import type { Choice } from '../index/engine.ts';
import { formatAmount } from '../index/price-markup.ts';
import type { ArchiveIndex, Filter } from '../index/types.ts';

export const PANEL = '.pixonaut-shop-archive-filters';
export const CHOSEN = '.pixonaut-shop-archive-chosen';
const ATTRIBUTE_BLOCK = '.wp-block-woocommerce-product-filter-attribute';
const ITEMS = '.wc-block-product-filter-checkbox-list__items';
const ITEM = '.wc-block-product-filter-checkbox-list__item';
const INPUT = 'input.wc-block-product-filter-checkbox-list__input';
const ITEM_TEXT = '.wc-block-product-filter-checkbox-list__text';
const ITEM_COUNT = '.wc-block-product-filter-checkbox-list__count';
const SHOW_MORE = '.wc-block-product-filter-checkbox-list__show-more';
const NESTED_CLASS = 'pixonaut-shop-filter-nested';
const NESTED_ATTRIBUTE = 'data-pixonaut-shop-attribute';
const PRICE_RANGE = '.wc-block-product-filter-price-slider__range';
const ACTIVE_BLOCK = '.wp-block-woocommerce-product-filter-active';
const CHIPS = '.wc-block-product-filter-removable-chips__items';
const CHIP_LABEL = '.wc-block-product-filter-removable-chips__label';
const CHIP_REMOVE = '.wc-block-product-filter-removable-chips__remove';
const OPEN_BUTTON = '.wc-block-product-filters__open-overlay';
const BADGE_CLASS = 'pixonaut-shop-filters-count';
const DRAWER_CLEAR = '.pixonaut-shop-filters-clear';
const APPLY_TEXT = '.wc-block-product-filters__apply span';
const RESULT_COUNT = '.woocommerce-result-count';
/** On each value's checkbox the archive draws: its filter's key. */
export const FILTER_KEY = 'data-pixonaut-shop-filter';
/** On a chip the archive draws: the filter it removes, and its value. */
export const CHIP_FILTER = 'data-pixonaut-shop-chip';
export const CHIP_VALUE = 'data-pixonaut-shop-chip-value';
/** The chosen filters' block the archive draws, beside WooCommerce's own, which it hides. */
export const CLIENT_CHOSEN_CLASS = 'pixonaut-shop-archive-chosen-client';
const DIRECTIVE = /^data-wp-/;
/** The key of the price filter's chip. */
export const PRICE_CHIP = 'price';
/** The price slider's ends as CSS custom properties, from 0% to 100%. */
const FULL_RANGE_PERCENT = 100;

export interface Words {
	count: Plural;
	/** The count of a result with no product. */
	none: string;
	showProducts: Plural;
	chosen: Plural;
	removeFilter: string;
}

export interface Plural {
	one: string;
	other: string;
}

export function plural( words: Plural, n: number ): string {
	return ( n === 1 ? words.one : words.other ).replace(
		'%s',
		n.toLocaleString( document.documentElement.lang || undefined ),
	);
}

function withoutDirectives( element: Element ): Element {
	for ( const node of [ element, ...element.querySelectorAll( '*' ) ] ) {
		for ( const name of node.getAttributeNames() ) {
			if ( DIRECTIVE.test( name ) ) {
				node.removeAttribute( name );
			}
		}
	}

	return element;
}

function contextOf( element: Element | null ): Record< string, unknown > {
	try {
		const parsed: unknown = JSON.parse( element?.getAttribute( 'data-wp-context' ) ?? '{}' );

		return parsed !== null && typeof parsed === 'object'
			? ( parsed as Record< string, unknown > )
			: {};
	} catch {
		return {};
	}
}

/** The filter a WooCommerce attribute block is for: by its attribute's slug, else its heading. */
function filterOf( block: Element, filters: Filter[] ): Filter | undefined {
	const type = contextOf( block ).filterType;
	const slug = typeof type === 'string' ? type.replace( /^attribute\//, '' ) : '';
	const heading = block.closest( 'details' )?.querySelector( 'summary' )?.textContent?.trim();

	return (
		filters.find( ( filter ) => filter.key === slug ) ??
		filters.find( ( filter ) => filter.type === 'attribute' && filter.label === heading )
	);
}

/** The attribute blocks of the drawer that have a section of their own, by their filter. */
function topLevelLists( panel: Element, filters: Filter[] ): Map< string, Element > {
	const lists = new Map< string, Element >();

	for ( const block of panel.querySelectorAll( ATTRIBUTE_BLOCK ) ) {
		const filter = block.closest( `.${ NESTED_CLASS }` ) ? undefined : filterOf( block, filters );

		if ( filter && ! lists.has( filter.key ) ) {
			lists.set( filter.key, block );
		}
	}

	return lists;
}

export interface DrawerState {
	panel: Element;
	/** The checkbox item every new value is copied from. */
	itemModel: Element | null;
	/** The attribute blocks with a section of their own, by their filter's key. */
	lists: Map< string, Element >;
	/** Each filter's chip label, `{{label}}` for the value. */
	labels: Map< string, string >;
}

export function drawerOf( panel: Element, index: ArchiveIndex ): DrawerState {
	const template = panel.querySelector< HTMLTemplateElement >( `${ ITEMS } template` );
	const model = template?.content.querySelector( ITEM ) ?? panel.querySelector( ITEM );
	const lists = topLevelLists( panel, index.filters );
	const labels = new Map< string, string >();

	for ( const block of panel.querySelectorAll( ATTRIBUTE_BLOCK ) ) {
		const filter = filterOf( block, index.filters );
		const label = contextOf( block ).activeLabelTemplate;

		if ( filter && typeof label === 'string' && ! labels.has( filter.key ) ) {
			labels.set( filter.key, label );
		}
	}

	// The server's nested groups go: the archive places its own as the choice changes.
	for ( const nested of panel.querySelectorAll( `.${ NESTED_CLASS }` ) ) {
		nested.remove();
	}

	// Every value of a long list shows: the archive draws the lists, so WooCommerce's fold has nothing to fold.
	for ( const more of panel.querySelectorAll( SHOW_MORE ) ) {
		more.remove();
	}

	return {
		panel,
		itemModel: model ? withoutDirectives( model.cloneNode( true ) as Element ) : null,
		lists,
		labels,
	};
}

function drawItem(
	drawer: DrawerState,
	filter: Filter,
	id: string,
	label: string,
	place: string,
): Element | null {
	if ( ! drawer.itemModel ) {
		return null;
	}

	const item = drawer.itemModel.cloneNode( true ) as Element;
	const input = item.querySelector< HTMLInputElement >( INPUT );
	const domId = `${ place }attribute/${ filter.key }-${ id }`;

	item.querySelector( 'label' )?.setAttribute( 'for', domId );

	if ( input ) {
		input.id = domId;
		input.value = id;
		input.checked = false;
		input.removeAttribute( 'checked' );
		input.setAttribute( 'aria-label', label );
	}

	const text = item.querySelector( ITEM_TEXT );

	if ( text ) {
		text.textContent = label;
	}

	return item;
}

/** Ticks, counts and shows or hides each value of a list, drawing the values it lacks. */
function syncList(
	drawer: DrawerState,
	block: Element,
	filter: Filter,
	choice: Choice,
	counts: Map< string, number >,
	place: string,
): void {
	const container = block.querySelector( ITEMS );

	if ( ! container ) {
		return;
	}

	const chosen = choice.values.get( filter.key );
	const byValue = new Map< string, Element >();

	for ( const item of container.querySelectorAll( `:scope > ${ ITEM }` ) ) {
		const value = item.querySelector< HTMLInputElement >( INPUT )?.value;

		if ( value !== undefined ) {
			byValue.set( value, item );
		}
	}

	const values = [ ...( filter.values ?? [] ) ].sort( ( a, b ) => a.order - b.order );
	let previous: Element | null = null;

	for ( const value of values ) {
		let item = byValue.get( value.id ) ?? null;

		if ( ! item ) {
			item = drawItem( drawer, filter, value.id, value.label, place );

			if ( ! item ) {
				continue;
			}

			if ( previous ) {
				previous.after( item );
			} else {
				container.prepend( item );
			}
		}

		const input = item.querySelector< HTMLInputElement >( INPUT );
		const count = counts.get( value.id ) ?? 0;
		const isChosen = chosen?.has( value.id ) ?? false;

		input?.setAttribute( FILTER_KEY, filter.key );

		if ( input ) {
			input.checked = isChosen;
		}

		const number = item.querySelector( `${ ITEM_COUNT } span` ) ?? item.querySelector( ITEM_COUNT );

		if ( number ) {
			number.textContent = count.toLocaleString( document.documentElement.lang || undefined );
		}

		( item as HTMLElement ).hidden = count === 0 && ! isChosen;
		previous = item;
	}

	for ( const [ value, item ] of byValue ) {
		if ( ! values.some( ( known ) => known.id === value ) ) {
			( item as HTMLElement ).hidden = true;
		}
	}
}

/** A dependent attribute's values under its name, for under a value of its parent. */
function drawNested( filter: Filter, parentKey: string ): Element {
	const nested = document.createElement( 'div' );
	nested.className = NESTED_CLASS;
	nested.setAttribute( NESTED_ATTRIBUTE, filter.key );

	const name = document.createElement( 'p' );
	name.className = `${ NESTED_CLASS }__label`;
	name.setAttribute( 'aria-hidden', 'true' );
	name.textContent = filter.label;

	const block = document.createElement( 'div' );
	block.className = ATTRIBUTE_BLOCK.slice( 1 );
	const list = document.createElement( 'div' );
	list.className =
		'wp-block-woocommerce-product-filter-checkbox-list wc-block-product-filter-checkbox-list';
	const fieldset = document.createElement( 'fieldset' );
	const legend = document.createElement( 'legend' );
	legend.className = 'screen-reader-text';
	legend.textContent = filter.label;
	const items = document.createElement( 'div' );
	items.className = ITEMS.slice( 1 );

	fieldset.append( legend, items );
	list.append( fieldset );
	block.append( list );
	nested.append( name, block );
	nested.dataset.pixonautShopPlace = `${ parentKey }-`;

	return nested;
}

/**
 * Where a dependent attribute shows: after the last chosen value of its
 * parent it applies under; with none chosen, after the last value it
 * applies under while a value of its own is chosen, so it can be cleared;
 * else nowhere.
 */
export function anchorOf( filter: Filter, parent: Filter, choice: Choice ): string | null {
	const under = ( parent.values ?? [] )
		.filter( ( value ) => filter.parent?.values.includes( value.id ) )
		.sort( ( a, b ) => a.order - b.order )
		.map( ( value ) => value.id );
	const chosenParents = under.filter( ( id ) => choice.values.get( parent.key )?.has( id ) );

	if ( chosenParents.length > 0 ) {
		return chosenParents[ chosenParents.length - 1 ];
	}

	return ( choice.values.get( filter.key )?.size ?? 0 ) > 0
		? ( under[ under.length - 1 ] ?? null )
		: null;
}

function syncNested(
	drawer: DrawerState,
	index: ArchiveIndex,
	choice: Choice,
	counts: Map< string, Map< string, number > >,
): void {
	for ( const filter of index.filters ) {
		const parent = index.filters.find( ( other ) => other.key === filter.parent?.key );
		const parentList = parent ? drawer.lists.get( parent.key ) : undefined;

		// A dependent with a section of its own is a list like any other.
		if ( ! parent || ! parentList || drawer.lists.has( filter.key ) ) {
			continue;
		}

		const anchor = anchorOf( filter, parent, choice );
		let nested = drawer.panel.querySelector(
			`.${ NESTED_CLASS }[${ NESTED_ATTRIBUTE }="${ CSS.escape( filter.key ) }"]`,
		);
		const anchorItem =
			anchor === null
				? null
				: Array.from( parentList.querySelectorAll( `${ ITEMS } > ${ ITEM }` ) ).find(
						( item ) => item.querySelector< HTMLInputElement >( INPUT )?.value === anchor,
					);

		if ( ! anchorItem ) {
			nested?.remove();
			continue;
		}

		nested ??= drawNested( filter, parent.key );
		const label = anchorItem.querySelector( 'label' );

		if ( label && label.nextElementSibling !== nested ) {
			label.after( nested );
		}

		const block = nested.querySelector( ATTRIBUTE_BLOCK );

		if ( block ) {
			syncList(
				drawer,
				block,
				filter,
				choice,
				counts.get( filter.key ) ?? new Map(),
				`${ parent.key }-`,
			);
		}
	}
}

function syncPrice( panel: Element, index: ArchiveIndex, choice: Choice ): void {
	const range = panel.querySelector< HTMLElement >( PRICE_RANGE );
	const inputs = Array.from(
		panel.querySelectorAll< HTMLInputElement >( `${ PRICE_RANGE } input[type="range"]` ),
	);
	const [ low, high ] = [
		inputs.find( ( input ) => input.classList.contains( 'min' ) ),
		inputs.find( ( input ) => input.classList.contains( 'max' ) ),
	];

	if ( ! range || ! low || ! high ) {
		return;
	}

	const bottom = Number( low.min );
	const top = Number( low.max );
	const min = choice.min ?? bottom;
	const max = choice.max ?? top;
	const span = top - bottom || 1;

	low.value = String( min );
	high.value = String( max );
	range.style.setProperty( '--low', `${ ( ( min - bottom ) / span ) * FULL_RANGE_PERCENT }%` );
	range.style.setProperty( '--high', `${ ( ( max - bottom ) / span ) * FULL_RANGE_PERCENT }%` );

	for ( const text of panel.querySelectorAll< HTMLInputElement >(
		'.wc-block-product-filter-price-slider input[type="text"]',
	) ) {
		const amount = text.classList.contains( 'min' ) ? min : max;

		if ( document.activeElement !== text ) {
			text.value = text.value.replace( /[\d\s.,]+/, formatAmount( amount, index.currency ) );
		}
	}
}

/** Draws the drawer for a choice. */
export function syncDrawer(
	drawer: DrawerState,
	index: ArchiveIndex,
	choice: Choice,
	counts: Map< string, Map< string, number > >,
): void {
	for ( const [ key, block ] of drawer.lists ) {
		const filter = index.filters.find( ( known ) => known.key === key );

		if ( filter ) {
			syncList( drawer, block, filter, choice, counts.get( key ) ?? new Map(), '' );
		}
	}

	syncNested( drawer, index, choice, counts );
	syncPrice( drawer.panel, index, choice );
}

export interface ChipsState {
	/** The chosen filters' block the archive draws, beside WooCommerce's own. */
	block: Element;
}

/** WooCommerce's cross on a chip's remove button. */
const CROSS_PATH =
	'M12 13.06l3.712 3.713 1.061-1.06L13.061 12l3.712-3.712-1.06-1.06L12 10.938 8.288 7.227l-1.061 1.06L10.939 12l-3.712 3.712 1.06 1.061L12 13.061z';
const SVG = 'http://www.w3.org/2000/svg';
/** The size WooCommerce draws the cross at. */
const CROSS_SIZE = '25';

/** A chip as WooCommerce draws one, for a chosen filter. */
function drawChip( chip: Chip, words: Words ): Element {
	const label = words.removeFilter.replace( '%s', chip.label );
	const item = document.createElement( 'li' );
	item.className = 'wc-block-product-filter-removable-chips__item';
	const text = document.createElement( 'span' );
	text.className = CHIP_LABEL.slice( 1 );
	text.textContent = chip.label;
	const remove = document.createElement( 'button' );
	remove.type = 'button';
	remove.className = CHIP_REMOVE.slice( 1 );
	remove.setAttribute( 'aria-label', label );
	remove.setAttribute( CHIP_FILTER, chip.filter );
	remove.setAttribute( CHIP_VALUE, chip.value );
	const cross = document.createElementNS( SVG, 'svg' );
	cross.setAttribute( 'viewBox', '0 0 24 24' );
	cross.setAttribute( 'width', CROSS_SIZE );
	cross.setAttribute( 'height', CROSS_SIZE );
	cross.setAttribute( 'class', 'wc-block-product-filter-removable-chips__remove-icon' );
	cross.setAttribute( 'aria-hidden', 'true' );
	cross.setAttribute( 'focusable', 'false' );
	const path = document.createElementNS( SVG, 'path' );
	path.setAttribute( 'd', CROSS_PATH );
	cross.append( path );
	const spoken = document.createElement( 'span' );
	spoken.className = 'screen-reader-text';
	spoken.textContent = label;
	remove.append( cross, spoken );
	item.append( text, remove );

	return item;
}

/** The chosen filters' block the archive draws in place of WooCommerce's, which stays for the page it rendered. */
export function chipsOf( chosen: Element ): ChipsState | null {
	const active = chosen.querySelector( ACTIVE_BLOCK );

	if ( ! active ) {
		return null;
	}

	const block = withoutDirectives( active.cloneNode( true ) as Element );
	block.classList.add( CLIENT_CHOSEN_CLASS );
	block.querySelector( CHIPS )?.replaceChildren();
	active.after( block );
	( active as HTMLElement ).hidden = true;
	( active as HTMLElement ).style.display = 'none';

	return { block };
}

export interface Chip {
	filter: string;
	value: string;
	label: string;
}

/** Each chosen filter's chip, in the filters' order. */
export function chipsFor(
	drawer: DrawerState,
	index: ArchiveIndex,
	choice: Choice,
	priceLabel: ( min: number | null, max: number | null ) => string,
): Chip[] {
	const chips: Chip[] = [];

	for ( const filter of index.filters ) {
		if ( filter.type === 'price' ) {
			if ( choice.min !== null || choice.max !== null ) {
				chips.push( {
					filter: PRICE_CHIP,
					value: '',
					label: priceLabel( choice.min, choice.max ),
				} );
			}

			continue;
		}

		const chosen = choice.values.get( filter.key );

		for ( const value of filter.values ?? [] ) {
			if ( chosen?.has( value.id ) ) {
				const template = drawer.labels.get( filter.key ) ?? `${ filter.label }: {{label}}`;
				chips.push( {
					filter: filter.key,
					value: value.id,
					label: template.replace( '{{label}}', value.label ),
				} );
			}
		}
	}

	return chips;
}

export function syncChips( chips: ChipsState, list: Chip[], words: Words ): void {
	( chips.block as HTMLElement ).hidden = list.length === 0;
	chips.block
		.querySelector( CHIPS )
		?.replaceChildren( ...list.map( ( chip ) => drawChip( chip, words ) ) );
}

/** The toolbar's button, the drawer's footer and the result count, for the cards shown and the filters chosen. */
export function syncCounts( shown: number, chosen: number, words: Words ): void {
	const button = document.querySelector( `${ PANEL } ${ OPEN_BUTTON }` );
	let badge = button?.querySelector( `.${ BADGE_CLASS }` );

	if ( chosen === 0 ) {
		badge?.remove();
	} else if ( button ) {
		if ( ! badge ) {
			badge = document.createElement( 'span' );
			badge.className = BADGE_CLASS;
			button.append( badge );
		}

		const number = document.createElement( 'span' );
		number.setAttribute( 'aria-hidden', 'true' );
		number.textContent = chosen.toLocaleString( document.documentElement.lang || undefined );
		const spoken = document.createElement( 'span' );
		spoken.className = 'screen-reader-text';
		spoken.textContent = plural( words.chosen, chosen );
		badge.replaceChildren( number, spoken );
	}

	for ( const clear of document.querySelectorAll< HTMLButtonElement >( DRAWER_CLEAR ) ) {
		clear.disabled = chosen === 0;
	}

	const apply = document.querySelector( `${ PANEL } ${ APPLY_TEXT }` );

	if ( apply ) {
		apply.textContent = plural( words.showProducts, shown );
	}

	const count = document.querySelector( `main ${ RESULT_COUNT }` );

	if ( count ) {
		count.textContent = shown === 0 ? words.none : plural( words.count, shown );
	}
}
