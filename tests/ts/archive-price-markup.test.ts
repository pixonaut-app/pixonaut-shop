import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import { formatAmount, parseAmount, priceMarkup } from '../../src/archive/index/price-markup.ts';
import type { Currency } from '../../src/archive/index/types.ts';

const SEK: Currency = {
	code: 'SEK',
	symbol: 'kr',
	position: 'right_space',
	decimals: 2,
	decimalSeparator: ',',
	thousandSeparator: ' ',
};

const templates = {
	amount: '<span class="amount">{{a}} kr</span>',
	sale: '<del>{{a}} kr</del> <ins>{{b}} kr</ins>',
	range: '{{a}} kr – {{b}} kr',
	from: '<span class="from">Från </span>{{a}} kr',
	varied: 'from' as const,
	lowest: '<span class="note">Lägsta pris: {{a}} kr</span>',
};

describe( 'formatAmount', () => {
	it( 'prints a whole amount without decimals, and groups thousands', () => {
		assert.equal( formatAmount( 1249, SEK ), '1 249' );
		assert.equal( formatAmount( 389, SEK ), '389' );
	} );

	it( 'keeps the store’s decimals for an amount with a fraction', () => {
		assert.equal( formatAmount( 389.5, SEK ), '389,50' );
	} );
} );

describe( 'priceMarkup', () => {
	it( 'is one price when every variation costs the same', () => {
		assert.equal(
			priceMarkup( [ { values: {}, price: 299, regularPrice: 299 } ], templates, SEK ),
			'<span class="amount">299 kr</span>',
		);
	} );

	it( 'is "from" the lowest price, or the range, as the store shows a range', () => {
		const variations = [
			{ values: {}, price: 299, regularPrice: 299 },
			{ values: {}, price: 549, regularPrice: 549 },
		];

		assert.equal(
			priceMarkup( variations, templates, SEK ),
			'<span class="from">Från </span>299 kr',
		);
		assert.equal(
			priceMarkup( variations, { ...templates, varied: 'range' }, SEK ),
			'299 kr – 549 kr',
		);
	} );

	it( 'strikes a reduced price’s regular one, with its lowest price before the reduction', () => {
		assert.equal(
			priceMarkup(
				[ { values: {}, price: 249, regularPrice: 299, lowestPrice: 279 } ],
				templates,
				SEK,
			),
			'<del>299 kr</del> <ins>249 kr</ins><span class="note">Lägsta pris: 279 kr</span>',
		);
	} );

	it( 'notes the lowest reference of the reduced variations after a range', () => {
		assert.equal(
			priceMarkup(
				[
					{ values: {}, price: 249, regularPrice: 299, lowestPrice: 279 },
					{ values: {}, price: 449, regularPrice: 499, lowestPrice: 469 },
				],
				templates,
				SEK,
			),
			'<span class="from">Från </span>249 kr<span class="note">Lägsta pris: 279 kr</span>',
		);
	} );
} );

describe( 'parseAmount', () => {
	it( 'reads an amount typed in the store’s format', () => {
		assert.equal( parseAmount( '1 249,50 kr', SEK ), 1249.5 );
		assert.equal( parseAmount( '389', SEK ), 389 );
		assert.equal(
			parseAmount( '$1,249.50', { ...SEK, decimalSeparator: '.', thousandSeparator: ',' } ),
			1249.5,
		);
	} );

	it( 'is nothing for a field without digits', () => {
		assert.equal( parseAmount( 'kr', SEK ), null );
		assert.equal( parseAmount( '', SEK ), null );
	} );
} );
