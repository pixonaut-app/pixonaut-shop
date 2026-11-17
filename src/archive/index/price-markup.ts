/**
 * A card's price as the shop prints it on the server, from the markup the
 * theme renders with placeholders for the amounts (`inc/Archive/ClientArchive.php`):
 *
 * - a range of prices, "from" its lowest or as the range, as the store
 *   chose (`Product\VariablePrice`);
 * - one reduced price struck through after its regular one, else the price;
 * - after a reduced price, its lowest price in the 30 days before
 *   (`Product\LowestPrice`).
 *
 * Amounts are whole where they are whole ("389 kr"), else in the store's
 * decimals (`Product\WholeAmounts`).
 */

import type { Currency, Variation } from './types.ts';

export interface PriceTemplates {
	amount: string;
	sale: string;
	range: string;
	from: string;
	/** How the store shows a range of prices. */
	varied: 'from' | 'range';
	lowest: string;
}

const FIRST = '{{a}}';
const SECOND = '{{b}}';
/** Digits from the right, three at a time, for the thousand separator. */
const THOUSANDS = /\B(?=(\d{3})+(?!\d))/g;

export function formatAmount( amount: number, currency: Currency ): string {
	const decimals = Number.isInteger( amount ) ? 0 : currency.decimals;
	const [ whole, fraction ] = Math.abs( amount ).toFixed( decimals ).split( '.' );
	const grouped = whole.replace( THOUSANDS, currency.thousandSeparator );
	const sign = amount < 0 ? '-' : '';

	return (
		sign + ( fraction === undefined ? grouped : grouped + currency.decimalSeparator + fraction )
	);
}

function fill( template: string, first: number, currency: Currency, second?: number ): string {
	const filled = template.split( FIRST ).join( formatAmount( first, currency ) );

	return second === undefined
		? filled
		: filled.split( SECOND ).join( formatAmount( second, currency ) );
}

/** The price markup of a card showing some variations. */
export function priceMarkup(
	variations: Variation[],
	templates: PriceTemplates,
	currency: Currency,
): string {
	if ( variations.length === 0 ) {
		return '';
	}

	const prices = variations.map( ( variation ) => variation.price );
	const lowest = Math.min( ...prices );
	const highest = Math.max(
		...variations.map( ( variation ) => variation.priceMax ?? variation.price ),
	);
	const reduced = variations.filter( ( variation ) => variation.price < variation.regularPrice );
	const references = reduced
		.map( ( variation ) => variation.lowestPrice )
		.filter( ( reference ): reference is number => reference !== undefined );
	const note =
		references.length > 0 ? fill( templates.lowest, Math.min( ...references ), currency ) : '';

	if ( lowest !== highest ) {
		const varied =
			templates.varied === 'range'
				? fill( templates.range, lowest, currency, highest )
				: fill( templates.from, lowest, currency );

		return varied + note;
	}

	const regulars = new Set( variations.map( ( variation ) => variation.regularPrice ) );

	if ( reduced.length === variations.length && regulars.size === 1 ) {
		return fill( templates.sale, variations[ 0 ].regularPrice, currency, lowest ) + note;
	}

	return fill( templates.amount, lowest, currency ) + note;
}

/**
 * An amount a shopper typed, in the store's format ("1 249,50 kr",
 * "$1,249.50"): its grouping dropped, its decimal separator read as a
 * point. Null for one with no digits.
 */
export function parseAmount( text: string, currency: Currency ): number | null {
	const grouped =
		currency.thousandSeparator === '' ? text : text.split( currency.thousandSeparator ).join( '' );
	const pointed =
		currency.decimalSeparator === ''
			? grouped
			: grouped.split( currency.decimalSeparator ).join( '.' );
	const digits = pointed.replace( /[^\d.]/g, '' );
	const amount = digits === '' ? Number.NaN : Number( digits );

	return Number.isFinite( amount ) ? amount : null;
}
