/**
 * The header search's suggestions (`src/header/search-suggestions.ts`): a
 * product from WooCommerce's Store API as a suggestion, its price as the
 * store writes prices, and where the arrow keys move among them. Run with
 * Node's own test runner: `pnpm test:ts`.
 */

import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import {
	formatAmount,
	optionStep,
	type StoreApiProduct,
	suggestionFrom,
} from '../../src/header/search-suggestions.ts';

/** Swedish kronor as a Swedish store sets them: whole kronor, a space between thousands, " kr" after. */
const KRONOR = {
	currency_minor_unit: 0,
	currency_decimal_separator: ',',
	currency_thousand_separator: ' ',
	currency_prefix: '',
	currency_suffix: ' kr',
};

/** Euros with cents, written before the amount. */
const EUROS = {
	currency_minor_unit: 2,
	currency_decimal_separator: '.',
	currency_thousand_separator: ',',
	currency_prefix: '€',
	currency_suffix: '',
};

/** Decodes the two entities the samples hold, as the page's own decoder would. */
const decode = ( text: string ): string =>
	text.replaceAll( '&#8221;', '”' ).replaceAll( '&amp;', '&' );

function product( overrides: Partial< StoreApiProduct > = {} ): StoreApiProduct {
	return {
		id: 7,
		name: 'Mugg &#8221;Världens bästa&#8221;',
		permalink: 'https://shop.test/produkt/mugg/',
		images: [ { thumbnail: 'https://shop.test/mugg-300x300.jpg', alt: 'En vit mugg' } ],
		prices: { ...KRONOR, price: '169', regular_price: '169', sale_price: '169', price_range: null },
		on_sale: false,
		...overrides,
	};
}

describe( 'formatAmount', () => {
	it( 'writes whole kronor with the store’s thousands separator and suffix', () => {
		assert.equal( formatAmount( '1250', KRONOR ), '1 250 kr' );
		assert.equal( formatAmount( '49', KRONOR ), '49 kr' );
	} );

	it( 'writes the minor units with the store’s decimal separator and prefix', () => {
		assert.equal( formatAmount( '123456', EUROS ), '€1,234.56' );
		assert.equal( formatAmount( '5', EUROS ), '€0.05' );
	} );
} );

describe( 'suggestionFrom', () => {
	it( 'takes the name, the link, the thumbnail and the price', () => {
		assert.deepEqual( suggestionFrom( product(), decode ), {
			id: 7,
			name: 'Mugg ”Världens bästa”',
			url: 'https://shop.test/produkt/mugg/',
			image: 'https://shop.test/mugg-300x300.jpg',
			price: '169 kr',
			regularPrice: null,
			fromPrice: false,
		} );
	} );

	it( 'keeps the regular price of a product on sale', () => {
		const reduced = product( {
			on_sale: true,
			prices: {
				...KRONOR,
				price: '349',
				regular_price: '399',
				sale_price: '349',
				price_range: null,
			},
		} );

		assert.equal( suggestionFrom( reduced, decode ).regularPrice, '399 kr' );
		assert.equal( suggestionFrom( reduced, decode ).price, '349 kr' );
	} );

	it( 'gives a product with a range of prices its lowest', () => {
		const variable = product( {
			prices: {
				...KRONOR,
				price: '299',
				regular_price: '299',
				sale_price: '299',
				price_range: { min_amount: '299', max_amount: '549' },
			},
		} );

		assert.equal( suggestionFrom( variable, decode ).price, '299 kr' );
		assert.equal( suggestionFrom( variable, decode ).fromPrice, true );
	} );

	it( 'gives a product without a picture none', () => {
		assert.equal( suggestionFrom( product( { images: [] } ), decode ).image, null );
	} );
} );

describe( 'optionStep', () => {
	const OPTIONS = 3;
	const NONE = -1;

	it( 'moves down and up the suggestions, from the field to the first or the last', () => {
		assert.equal( optionStep( 'ArrowDown', NONE, OPTIONS ), 0 );
		assert.equal( optionStep( 'ArrowDown', 0, OPTIONS ), 1 );
		assert.equal( optionStep( 'ArrowUp', NONE, OPTIONS ), OPTIONS - 1 );
		assert.equal( optionStep( 'ArrowUp', 1, OPTIONS ), 0 );
	} );

	it( 'goes back to the field past either end', () => {
		assert.equal( optionStep( 'ArrowDown', OPTIONS - 1, OPTIONS ), NONE );
		assert.equal( optionStep( 'ArrowUp', 0, OPTIONS ), NONE );
	} );

	it( 'leaves every other key, and an empty list, to the field', () => {
		assert.equal( optionStep( 'ArrowLeft', 0, OPTIONS ), null );
		assert.equal( optionStep( 'Home', 1, OPTIONS ), null );
		assert.equal( optionStep( 'ArrowDown', NONE, 0 ), null );
	} );
} );
