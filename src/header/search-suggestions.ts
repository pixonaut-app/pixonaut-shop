/**
 * The header search's suggestions (`src/header/search.ts`), worked out from
 * data alone so they are tested without a page: a product from
 * WooCommerce's Store API as the suggestion the panel lists, its price as
 * the store writes prices, and where the arrow keys move among them.
 */

/** How the Store API describes a price's currency, from the store's own settings. */
export interface StoreApiCurrency {
	currency_minor_unit: number;
	currency_decimal_separator: string;
	currency_thousand_separator: string;
	currency_prefix: string;
	currency_suffix: string;
}

/** The parts of a Store API product (`/wc/store/v1/products`) a suggestion reads. */
export interface StoreApiProduct {
	id: number;
	/** The product's name, with HTML entities, as WordPress stores titles. */
	name: string;
	permalink: string;
	images: { thumbnail: string; alt: string }[];
	prices: StoreApiCurrency & {
		/** Amounts in the currency's minor unit, as strings. */
		price: string;
		regular_price: string;
		sale_price: string;
		price_range: { min_amount: string; max_amount: string } | null;
	};
	on_sale: boolean;
}

/** One suggestion, as the panel shows it. */
export interface Suggestion {
	id: number;
	name: string;
	url: string;
	image: string | null;
	price: string;
	/** The price before a sale, struck through beside the price. */
	regularPrice: string | null;
	/** Whether the price is the lowest of several, which the panel says. */
	fromPrice: boolean;
}

/** Every three digits of the whole part, from the end. */
const THOUSANDS = /\B(?=(\d{3})+(?!\d))/g;

const DECIMAL_BASE = 10;

/** An amount in the currency's minor unit, written as the store writes prices. */
export function formatAmount( amount: string, currency: StoreApiCurrency ): string {
	const minor = currency.currency_minor_unit;
	const digits = String( Math.abs( Number.parseInt( amount, DECIMAL_BASE ) || 0 ) ).padStart(
		minor + 1,
		'0',
	);
	const whole = digits
		.slice( 0, digits.length - minor )
		.replace( THOUSANDS, currency.currency_thousand_separator );
	const fraction = minor > 0 ? currency.currency_decimal_separator + digits.slice( -minor ) : '';

	return currency.currency_prefix + whole + fraction + currency.currency_suffix;
}

/**
 * A product as a suggestion. `decode` turns the HTML entities in its name
 * into text, which the page does with the browser's own parser.
 */
export function suggestionFrom(
	product: StoreApiProduct,
	decode: ( text: string ) => string,
): Suggestion {
	const { prices } = product;
	const range = prices.price_range;
	const fromPrice = range !== null && range.min_amount !== range.max_amount;

	return {
		id: product.id,
		name: decode( product.name ),
		url: product.permalink,
		image: product.images[ 0 ]?.thumbnail || null,
		price: formatAmount( fromPrice && range ? range.min_amount : prices.price, prices ),
		regularPrice:
			product.on_sale && prices.regular_price !== prices.price
				? formatAmount( prices.regular_price, prices )
				: null,
		fromPrice,
	};
}

/** The keys that move among the suggestions. */
const NEXT = 'ArrowDown';
const PREVIOUS = 'ArrowUp';

/** The field itself, where no suggestion is active. */
const FIELD = -1;

/**
 * The suggestion a key makes active from the one at `current` (-1: none,
 * the field), of `count`: past either end, back to the field. `null` for a
 * key the field keeps, such as the arrows that move its caret.
 */
export function optionStep( key: string, current: number, count: number ): number | null {
	if ( count === 0 ) {
		return null;
	}

	switch ( key ) {
		case NEXT:
			return current === count - 1 ? FIELD : current + 1;
		case PREVIOUS:
			if ( current === FIELD ) {
				return count - 1;
			}
			return current - 1;
		default:
			return null;
	}
}
