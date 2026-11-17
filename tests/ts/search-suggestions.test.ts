/**
 * The header search's suggestions (`src/header/search-suggestions.ts`): the
 * results page's answer for the panel as the suggestions it lists, and
 * where the arrow keys move among them. Run with Node's own test runner:
 * `pnpm test:ts`.
 */

import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import { foundFrom, optionStep } from '../../src/header/search-suggestions.ts';

/** A card of a product the results page lists once per card, as the search answers it. */
const CARD = {
	id: 7,
	name: 'Mugg ”Världens bästa” – Röd',
	url: 'https://shop.test/produkt/mugg/?attribute_pa_farg=rod',
	image: 'https://shop.test/mugg-rod-300x300.jpg',
	price: '<span class="woocommerce-Price-amount amount"><bdi>169&nbsp;kr</bdi></span>',
};

describe( 'foundFrom', () => {
	it( 'takes the count and each suggestion’s name, link, picture and price', () => {
		assert.deepEqual( foundFrom( { total: 12, suggestions: [ CARD ] } ), {
			total: 12,
			suggestions: [ CARD ],
		} );
	} );

	it( 'gives a suggestion without a picture none', () => {
		const found = foundFrom( { total: 1, suggestions: [ { ...CARD, image: null } ] } );

		assert.equal( found?.suggestions[ 0 ]?.image, null );
	} );

	it( 'leaves out a suggestion it cannot read, and never counts fewer than it shows', () => {
		const found = foundFrom( { suggestions: [ CARD, { id: 'eight' } ] } );

		assert.deepEqual( found, { total: 1, suggestions: [ CARD ] } );
	} );

	it( 'reads nothing from an answer of another shape', () => {
		assert.equal( foundFrom( [ CARD ] ), null );
		assert.equal( foundFrom( null ), null );
		assert.equal( foundFrom( { total: 3 } ), null );
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
