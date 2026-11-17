/**
 * Filters, sorts and counts an archive's cards in the browser by the rules
 * the shop's server-rendered archive follows (Pixonaut for WooCommerce's
 * archive index), so an address means the same results on either side.
 *
 * - A variation matches when, for each chosen attribute, it holds a chosen
 *   value, or holds none of that attribute and the attribute's filter is
 *   waived; and its price is within the chosen range. Values of one
 *   attribute combine with "or", attributes with "and".
 * - A dependent attribute's filter is waived while a value of its parent is
 *   chosen that it does not apply under: "Frame colour: oak" beside "No
 *   frame" keeps the unframed prints.
 * - With nothing chosen every card shows but those listed only while a
 *   filter is on one of their attributes. With a choice, a card shows when
 *   every attribute it is listed only by is chosen, it holds a chosen value
 *   of every chosen attribute its product is split by (or the filter is
 *   waived and it holds none), and one of its variations matches.
 * - A value's count is the cards shown with that value added to the choice.
 */

import type { ArchiveIndex, Card, Filter, Picture, Variation } from './types.ts';

export interface Choice {
	/** The chosen values of each attribute, by the filter's key. */
	values: Map< string, Set< string > >;
	min: number | null;
	max: number | null;
	/** The sort's key; one the index does not have keeps the index's order. */
	sort: string;
}

export interface Shown {
	card: Card;
	/** The card's variations the choice matches: every one while nothing is chosen. */
	variations: Variation[];
}

type Values = Map< string, Set< string > >;

/** The chosen parent values each chosen dependent attribute's filter is waived under. */
export function waivers( filters: Filter[], values: Values ): Values {
	const waived: Values = new Map();

	for ( const filter of filters ) {
		const parent = filter.parent;
		const chosenParent = parent ? values.get( parent.key ) : undefined;

		if ( ! parent || ! chosenParent || ! values.has( filter.key ) ) {
			continue;
		}

		const under = [ ...chosenParent ].filter( ( id ) => ! parent.values.includes( id ) );

		if ( under.length > 0 ) {
			waived.set( filter.key, new Set( under ) );
		}
	}

	return waived;
}

function anyChosen( held: string[] | undefined, ids: Set< string > ): boolean {
	return ( held ?? [] ).some( ( id ) => ids.has( id ) );
}

export function variationMatches(
	variation: Variation,
	values: Values,
	min: number | null,
	max: number | null,
	waived: Values,
): boolean {
	for ( const [ key, ids ] of values ) {
		const held = variation.values[ key ];

		// A product without the attribute fails its filter.
		if ( held === undefined ) {
			return false;
		}

		if ( held.length === 0 ? ! waived.has( key ) : ! anyChosen( held, ids ) ) {
			return false;
		}
	}

	return withinPrice( variation, min, max );
}

function withinPrice( variation: Variation, min: number | null, max: number | null ): boolean {
	const highest = variation.priceMax ?? variation.price;

	return ( min === null || highest >= min ) && ( max === null || variation.price <= max );
}

function isFiltered( values: Values, min: number | null, max: number | null ): boolean {
	return values.size > 0 || min !== null || max !== null;
}

/** The variations a card shows for a choice, or null when the card does not show. */
function shownVariations(
	card: Card,
	values: Values,
	min: number | null,
	max: number | null,
	waived: Values,
): Variation[] | null {
	if ( ! isFiltered( values, min, max ) ) {
		return card.onlyFiltered.length === 0 ? card.variations : null;
	}

	if ( ! card.onlyFiltered.every( ( key ) => values.has( key ) ) ) {
		return null;
	}

	for ( const key of card.listedBy ) {
		const ids = values.get( key );
		const held = card.holds[ key ] ?? [];

		if ( ids && ! anyChosen( held, ids ) && ! ( waived.has( key ) && held.length === 0 ) ) {
			return null;
		}
	}

	const matched = card.variations.filter( ( variation ) =>
		variationMatches( variation, values, min, max, waived ),
	);

	return matched.length > 0 ? matched : null;
}

function shownWith(
	index: ArchiveIndex,
	values: Values,
	min: number | null,
	max: number | null,
): Shown[] {
	const waived = waivers( index.filters, values );
	const shown: Shown[] = [];

	for ( const card of index.cards ) {
		const variations = shownVariations( card, values, min, max, waived );

		if ( variations ) {
			shown.push( { card, variations } );
		}
	}

	return shown;
}

/** The cards a choice shows, in its sort's order. */
export function shownCards( index: ArchiveIndex, choice: Choice ): Shown[] {
	const shown = shownWith( index, choice.values, choice.min, choice.max );

	if ( ! index.cards.some( ( card ) => choice.sort in card.sort ) ) {
		return shown;
	}

	// A stable sort keeps the index's order among cards that tie.
	return shown.sort(
		( a, b ) =>
			( a.card.sort[ choice.sort ] ?? Number.POSITIVE_INFINITY ) -
			( b.card.sort[ choice.sort ] ?? Number.POSITIVE_INFINITY ),
	);
}

function withValue( values: Values, key: string, id: string ): Values {
	const next = new Map( values );
	next.set( key, new Set( [ ...( values.get( key ) ?? [] ), id ] ) );

	return next;
}

/**
 * Whether adding a value of a filter to the choice can change how another
 * chosen filter is waived: then its counts are worked out one value at a
 * time.
 */
function changesWaivers( filters: Filter[], filter: Filter, values: Values ): boolean {
	return filters.some( ( other ) => other.parent?.key === filter.key && values.has( other.key ) );
}

/**
 * For each card's variations, the chosen filters (and the price) each fails,
 * one bit each, so a count can ask whether a variation passes every filter
 * but its own at once.
 */
interface Fails {
	bits: Map< string, number >;
	masks: number[][];
}

const PRICE_BIT_KEY = '';

function failsOf( index: ArchiveIndex, choice: Choice ): Fails {
	const bits = new Map< string, number >();
	const keys = [ ...choice.values.keys() ];
	keys.forEach( ( key, place ) => {
		bits.set( key, 1 << place );
	} );
	const priceBit = 1 << keys.length;
	bits.set( PRICE_BIT_KEY, priceBit );
	const waived = waivers( index.filters, choice.values );

	const masks = index.cards.map( ( card ) =>
		card.variations.map( ( variation ) => {
			let mask = 0;

			for ( const [ key, ids ] of choice.values ) {
				const held = variation.values[ key ];
				const passes =
					held !== undefined && ( held.length === 0 ? waived.has( key ) : anyChosen( held, ids ) );

				if ( ! passes ) {
					mask |= bits.get( key ) ?? 0;
				}
			}

			if ( ! withinPrice( variation, choice.min, choice.max ) ) {
				mask |= priceBit;
			}

			return mask;
		} ),
	);

	return { bits, masks };
}

/** One value's count: the cards shown with it added. */
function countOne( index: ArchiveIndex, choice: Choice, key: string, id: string ): number {
	return shownWith( index, withValue( choice.values, key, id ), choice.min, choice.max ).length;
}

/**
 * Every value's count of a filter whose values waive nothing, in one pass:
 * the other filters are the same for each of its values, so a card counts
 * for a value when it passes them and one of the variations that pass them
 * holds the value or one already chosen.
 */
function countAll(
	index: ArchiveIndex,
	choice: Choice,
	filter: Filter,
	fails: Fails,
): Map< string, number > {
	const key = filter.key;
	const chosen = choice.values.get( key ) ?? new Set< string >();
	const others = new Map( choice.values );
	others.delete( key );
	// The filter itself is chosen with any of its values, so it is waived (or not) alike for each.
	const withFilter = new Map( others );
	withFilter.set( key, new Set( [ ...chosen, '' ] ) );
	const waived = waivers( index.filters, withFilter );
	const filterWaived = waived.has( key );
	const perValue = new Map< string, number >();
	const ids = ( filter.values ?? [] ).map( ( value ) => value.id );

	const own = fails.bits.get( key ) ?? 0;

	index.cards.forEach( ( card, place ) => {
		if ( ! card.onlyFiltered.every( ( only ) => only === key || others.has( only ) ) ) {
			return;
		}

		const passesOthers = card.listedBy.every( ( listed ) => {
			const wanted = others.get( listed );
			const held = card.holds[ listed ] ?? [];

			return ! wanted || anyChosen( held, wanted ) || ( waived.has( listed ) && held.length === 0 );
		} );

		if ( ! passesOthers ) {
			return;
		}

		// The values of the filter the card's passing variations hold, and whether one passes it waived.
		const held = new Set< string >();
		let passesWaived = false;

		card.variations.forEach( ( variation, at ) => {
			const values = variation.values[ key ];

			// Passes every other filter: it fails none but, perhaps, this one.
			if (
				! values ||
				( values.length === 0 && ! filterWaived ) ||
				( fails.masks[ place ][ at ] & ~own ) !== 0
			) {
				return;
			}

			passesWaived ||= values.length === 0;

			for ( const id of values ) {
				held.add( id );
			}
		} );

		const cardHolds = card.listedBy.includes( key ) ? ( card.holds[ key ] ?? [] ) : null;
		const holdsChosen = passesWaived || [ ...chosen ].some( ( id ) => held.has( id ) );

		for ( const id of ids ) {
			const listed =
				cardHolds === null ||
				cardHolds.includes( id ) ||
				anyChosen( cardHolds, chosen ) ||
				( filterWaived && cardHolds.length === 0 );

			if ( listed && ( holdsChosen || held.has( id ) ) ) {
				perValue.set( id, ( perValue.get( id ) ?? 0 ) + 1 );
			}
		}
	} );

	return perValue;
}

/** Each attribute value's count, by the filter's key: values that would show no card are left out. */
export function counts(
	index: ArchiveIndex,
	choice: Choice,
): Map< string, Map< string, number > > {
	const result = new Map< string, Map< string, number > >();
	const fails = failsOf( index, choice );

	for ( const filter of index.filters ) {
		if ( filter.type !== 'attribute' ) {
			continue;
		}

		if ( ! changesWaivers( index.filters, filter, choice.values ) ) {
			result.set( filter.key, countAll( index, choice, filter, fails ) );
			continue;
		}

		const perValue = new Map< string, number >();

		for ( const value of filter.values ?? [] ) {
			const count = countOne( index, choice, filter.key, value.id );

			if ( count > 0 ) {
				perValue.set( value.id, count );
			}
		}

		result.set( filter.key, perValue );
	}

	return result;
}

/** The lowest and highest price among some variations. */
export function priceRange( variations: Variation[] ): { min: number; max: number } {
	return {
		min: Math.min( ...variations.map( ( variation ) => variation.price ) ),
		max: Math.max( ...variations.map( ( variation ) => variation.priceMax ?? variation.price ) ),
	};
}

/** Whether any filter is on: a card then opens on its first matching variation. */
export function isChoosing( choice: Choice ): boolean {
	return isFiltered( choice.values, choice.min, choice.max );
}

/**
 * The link a shown card opens: its own, and while a filter is on, its first
 * matching variation's value of each attribute the product's page lets the
 * shopper choose, where the variation holds just one and the link names none.
 */
export function linkOf( entry: Shown, base: string, choice: Choice ): string {
	const url = new URL( entry.card.url, base );
	const opened = isChoosing( choice ) ? entry.variations[ 0 ] : undefined;

	for ( const [ key, held ] of Object.entries( opened?.values ?? {} ) ) {
		if (
			entry.card.choices.includes( key ) &&
			held.length === 1 &&
			! url.searchParams.has( key )
		) {
			url.searchParams.set( key, held[ 0 ] );
		}
	}

	return url.toString();
}

/**
 * The picture a shown card shows. With no filter on, its own. Else, its
 * first matching variation's picture of the filtered combination while a
 * filter on an attribute the shopper chooses picks values the card holds
 * none of (and, while that filter is waived, the card holds none of the
 * parent's values that waive it); else that variation's opening picture;
 * else its own.
 */
export function pictureOf(
	index: ArchiveIndex,
	entry: Shown,
	choice: Choice,
): Picture & { alt: string } {
	const { card } = entry;
	const opened = isChoosing( choice ) ? entry.variations[ 0 ] : undefined;

	if ( ! opened ) {
		return card.image;
	}

	const openPicture = opened.openImage === undefined ? undefined : index.images[ opened.openImage ];
	const open = openPicture ? { ...openPicture, alt: card.image.alt } : card.image;
	const combination = opened.image === undefined ? undefined : index.images[ opened.image ];

	if ( ! combination ) {
		return open;
	}

	const waived = waivers( index.filters, choice.values );
	const picksUnheld = [ ...choice.values ].some( ( [ key, ids ] ) => {
		if ( ! card.choices.includes( key ) || anyChosen( card.holds[ key ], ids ) ) {
			return false;
		}

		const parent = waived.has( key )
			? index.filters.find( ( filter ) => filter.key === key )?.parent
			: undefined;

		return ! ( parent && anyChosen( card.holds[ parent.key ], waived.get( key ) ?? new Set() ) );
	} );

	return picksUnheld ? { ...combination, alt: card.image.alt } : open;
}
