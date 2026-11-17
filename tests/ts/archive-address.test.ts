import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import { addressOf, choiceFrom } from '../../src/archive/index/address.ts';
import type { Filter } from '../../src/archive/index/types.ts';

const filters: Filter[] = [
	{
		key: 'storlek',
		label: 'Storlek',
		type: 'attribute',
		values: [
			{ id: '30-x-40-cm', label: '30 × 40 cm', order: 0 },
			{ id: '50-x-70-cm', label: '50 × 70 cm', order: 1 },
		],
	},
	{
		key: 'inramning',
		label: 'Inramning',
		type: 'attribute',
		values: [
			{ id: 'ingen-ram', label: 'Ingen ram', order: 0 },
			{ id: 'ram', label: 'Ram', order: 1 },
		],
	},
	{ key: 'price', label: 'Pris', type: 'price' },
];

const DEFAULT_SORT = 'menu_order';

describe( 'choiceFrom', () => {
	it( 'reads short names, WooCommerce’s names, the price and the sorting', () => {
		const choice = choiceFrom(
			new URL(
				'https://shop.test/c/?storlek=50-x-70-cm,30-x-40-cm&filter_inramning=ram&query_type_inramning=or&min_price=100&max_price=400&orderby=price',
			),
			filters,
			DEFAULT_SORT,
		);

		assert.deepEqual(
			[ ...( choice.values.get( 'storlek' ) ?? [] ) ],
			[ '50-x-70-cm', '30-x-40-cm' ],
		);
		assert.deepEqual( [ ...( choice.values.get( 'inramning' ) ?? [] ) ], [ 'ram' ] );
		assert.equal( choice.min, 100 );
		assert.equal( choice.max, 400 );
		assert.equal( choice.sort, 'price' );
	} );

	it( 'ignores a value the archive does not have, and a price that is not a number', () => {
		const choice = choiceFrom(
			new URL( 'https://shop.test/c/?storlek=nope&min_price=abc' ),
			filters,
			DEFAULT_SORT,
		);

		assert.equal( choice.values.size, 0 );
		assert.equal( choice.min, null );
		assert.equal( choice.sort, DEFAULT_SORT );
	} );
} );

describe( 'addressOf', () => {
	it( 'writes the choice the short way, in the filters’ order, keeping other parameters and dropping the page', () => {
		const url = addressOf(
			new URL(
				'https://shop.test/c/page/3/?utm_source=x&filter_storlek=30-x-40-cm&query_type_storlek=or&paged=3',
			),
			{
				values: new Map( [
					[ 'inramning', new Set( [ 'ram' ] ) ],
					[ 'storlek', new Set( [ '50-x-70-cm', '30-x-40-cm' ] ) ],
				] ),
				min: null,
				max: 500,
				sort: 'price-desc',
			},
			filters,
			DEFAULT_SORT,
		);

		assert.equal(
			url.href,
			'https://shop.test/c/?utm_source=x&storlek=30-x-40-cm,50-x-70-cm&inramning=ram&max_price=500&orderby=price-desc',
		);
	} );

	it( 'leaves the default sorting out, and reads back what it wrote', () => {
		const choice = {
			values: new Map( [ [ 'inramning', new Set( [ 'ingen-ram' ] ) ] ] ),
			min: 100,
			max: null,
			sort: DEFAULT_SORT,
		};
		const url = addressOf(
			new URL( 'https://shop.test/c/?orderby=price' ),
			choice,
			filters,
			DEFAULT_SORT,
		);

		assert.equal( url.searchParams.has( 'orderby' ), false );
		assert.deepEqual( choiceFrom( url, filters, DEFAULT_SORT ), choice );
	} );
} );
