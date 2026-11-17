/**
 * An archive's choice in its address, as the server reads it: each chosen
 * attribute under its short name (`?storlek=30-x-40-cm,50-x-70-cm`), or
 * WooCommerce's own `filter_<name>`; the price as `min_price` and
 * `max_price`; the sorting as `orderby`. Writing a choice keeps every other
 * parameter, drops the page the archive was on, and names attributes the
 * short way.
 */

import type { Choice } from './engine.ts';
import type { Filter } from './types.ts';

const FILTER_PREFIX = 'filter_';
const QUERY_TYPE_PREFIX = 'query_type_';
const MIN_PRICE = 'min_price';
const MAX_PRICE = 'max_price';
const ORDER_BY = 'orderby';
/** WordPress's later pages: `/page/2/` in the path, or `paged` and WooCommerce's `product-page`. */
const LATER_PAGE = /\/page\/\d+\/?$/;
const PAGE_PARAMETERS = [ 'paged', 'product-page' ];
const VALUE_SEPARATOR = ',';
const ENCODED_SEPARATOR = /%2C/gi;

function price( params: URLSearchParams, name: string ): number | null {
	const raw = params.get( name );
	const amount = raw === null || raw.trim() === '' ? Number.NaN : Number( raw );

	return Number.isFinite( amount ) ? amount : null;
}

/** The choice an address names, with `defaultSort` where it names no sorting. */
export function choiceFrom( url: URL, filters: Filter[], defaultSort: string ): Choice {
	const values = new Map< string, Set< string > >();

	for ( const filter of filters ) {
		if ( filter.type !== 'attribute' ) {
			continue;
		}

		const known = new Set( ( filter.values ?? [] ).map( ( value ) => value.id ) );
		const named = [
			...( url.searchParams.get( filter.key ) ?? '' ).split( VALUE_SEPARATOR ),
			...( url.searchParams.get( FILTER_PREFIX + filter.key ) ?? '' ).split( VALUE_SEPARATOR ),
		].filter( ( id ) => known.has( id ) );

		if ( named.length > 0 ) {
			values.set( filter.key, new Set( named ) );
		}
	}

	return {
		values,
		min: price( url.searchParams, MIN_PRICE ),
		max: price( url.searchParams, MAX_PRICE ),
		sort: url.searchParams.get( ORDER_BY ) ?? defaultSort,
	};
}

/** The address of a choice, from the address it was made on. */
export function addressOf(
	from: URL,
	choice: Choice,
	filters: Filter[],
	defaultSort: string,
): URL {
	const url = new URL( from.href );
	url.pathname = url.pathname.replace( LATER_PAGE, '/' );

	for ( const name of [ ...PAGE_PARAMETERS, MIN_PRICE, MAX_PRICE, ORDER_BY ] ) {
		url.searchParams.delete( name );
	}

	for ( const filter of filters ) {
		url.searchParams.delete( filter.key );
		url.searchParams.delete( FILTER_PREFIX + filter.key );
		url.searchParams.delete( QUERY_TYPE_PREFIX + filter.key );
	}

	// In the filters' own order, each attribute's values in theirs, so one choice has one address.
	for ( const filter of filters ) {
		const chosen = choice.values.get( filter.key );

		if ( chosen && chosen.size > 0 ) {
			const ordered = ( filter.values ?? [] )
				.filter( ( value ) => chosen.has( value.id ) )
				.map( ( value ) => value.id );
			url.searchParams.set( filter.key, ordered.join( VALUE_SEPARATOR ) );
		}
	}

	if ( choice.min !== null ) {
		url.searchParams.set( MIN_PRICE, String( choice.min ) );
	}

	if ( choice.max !== null ) {
		url.searchParams.set( MAX_PRICE, String( choice.max ) );
	}

	if ( choice.sort !== defaultSort ) {
		url.searchParams.set( ORDER_BY, choice.sort );
	}

	// Values listed as the server writes them, with a bare comma.
	url.search = url.search.replace( ENCODED_SEPARATOR, VALUE_SEPARATOR );

	return url;
}
