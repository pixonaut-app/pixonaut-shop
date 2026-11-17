import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import {
	type Choice,
	counts,
	linkOf,
	pictureOf,
	priceRange,
	shownCards,
} from '../../src/archive/index/engine.ts';
import type { ArchiveIndex, Card, Variation } from '../../src/archive/index/types.ts';
import { generatedIndex } from './archive-index-fixture.ts';

/** Prints sold bare, on a hanger or framed; a frame's colour applies under a hanger or a frame only. */
const PICTURE = { src: 'p.jpg', srcset: '', width: 600, height: 600 };

function variation( values: Variation[ 'values' ], price: number, image?: number ): Variation {
	return { values, price, regularPrice: price, ...( image === undefined ? {} : { image } ) };
}

function card( id: string, variations: Variation[], extra: Partial< Card > = {} ): Card {
	return {
		id,
		productId: Number( id.replace( /\D/g, '' ) || 1 ),
		title: id,
		url: `https://shop.test/product/${ id }/`,
		summary: '',
		image: { ...PICTURE, src: `${ id }.jpg`, alt: id },
		sort: {},
		holds: {},
		listedBy: [],
		onlyFiltered: [],
		variations,
		...extra,
	};
}

const index: ArchiveIndex = {
	version: '1',
	currency: {
		code: 'SEK',
		symbol: 'kr',
		position: 'right_space',
		decimals: 2,
		decimalSeparator: ',',
		thousandSeparator: ' ',
	},
	filters: [
		{
			key: 'framing',
			label: 'Framing',
			type: 'attribute',
			values: [
				{ id: 'none', label: 'No frame', order: 0 },
				{ id: 'hanger', label: 'Hanger', order: 1 },
				{ id: 'frame', label: 'Frame', order: 2 },
			],
		},
		{
			key: 'colour',
			label: 'Frame colour',
			type: 'attribute',
			values: [
				{ id: 'oak', label: 'Oak', order: 0 },
				{ id: 'black', label: 'Black', order: 1 },
			],
			parent: { key: 'framing', values: [ 'hanger', 'frame' ] },
		},
		{
			key: 'size',
			label: 'Size',
			type: 'attribute',
			values: [
				{ id: 's', label: 'Small', order: 0 },
				{ id: 'l', label: 'Large', order: 1 },
			],
		},
		{ key: 'price', label: 'Price', type: 'price' },
	],
	sorts: [
		{ key: 'menu_order', label: 'Default' },
		{ key: 'price', label: 'Price' },
	],
	cards: [
		card(
			'p1',
			[
				variation( { framing: [ 'none' ], colour: [], size: [ 's' ] }, 100 ),
				variation( { framing: [ 'frame' ], colour: [ 'oak' ], size: [ 's' ] }, 300, 0 ),
				variation( { framing: [ 'frame' ], colour: [ 'black' ], size: [ 'l' ] }, 400 ),
			],
			{ sort: { menu_order: 0, price: 1 } },
		),
		card( 'p2', [ variation( { framing: [ 'none' ], size: [ 's', 'l' ] }, 150 ) ], {
			sort: { menu_order: 1, price: 0 },
		} ),
		// A product split into a card per framing; its framed card is listed only by the framing.
		card( 'p3-none', [ variation( { framing: [ 'none' ], colour: [], size: [ 'l' ] }, 200 ) ], {
			productId: 3,
			holds: { framing: [ 'none' ] },
			listedBy: [ 'framing' ],
			sort: { menu_order: 2, price: 2 },
		} ),
		card(
			'p3-frame',
			[ variation( { framing: [ 'frame' ], colour: [ 'oak' ], size: [ 'l' ] }, 500 ) ],
			{
				productId: 3,
				holds: { framing: [ 'frame' ] },
				listedBy: [ 'framing' ],
				onlyFiltered: [ 'framing' ],
				sort: { menu_order: 3, price: 3 },
			},
		),
	],
	images: [ { src: 'p1-oak.jpg', srcset: '', width: 600, height: 600 } ],
};

function choice( values: Record< string, string[] > = {}, extra: Partial< Choice > = {} ): Choice {
	return {
		values: new Map( Object.entries( values ).map( ( [ key, ids ] ) => [ key, new Set( ids ) ] ) ),
		min: null,
		max: null,
		sort: 'menu_order',
		...extra,
	};
}

function ids( c: Choice ): string[] {
	return shownCards( index, c ).map( ( entry ) => entry.card.id );
}

describe( 'shownCards', () => {
	it( 'shows every card but one listed only while filtered, with nothing chosen', () => {
		assert.deepEqual( ids( choice() ), [ 'p1', 'p2', 'p3-none' ] );
	} );

	it( 'combines values of one attribute with or', () => {
		assert.deepEqual( ids( choice( { framing: [ 'none', 'frame' ] } ) ), [
			'p1',
			'p2',
			'p3-none',
			'p3-frame',
		] );
		assert.deepEqual( ids( choice( { framing: [ 'frame' ] } ) ), [ 'p1', 'p3-frame' ] );
	} );

	it( 'combines attributes with and, on one variation', () => {
		assert.deepEqual( ids( choice( { framing: [ 'frame' ], size: [ 's' ] } ) ), [ 'p1' ] );
		assert.deepEqual( ids( choice( { colour: [ 'black' ], size: [ 's' ] } ) ), [] );
	} );

	it( 'fails a product without the attribute, and a variation it does not apply to', () => {
		assert.deepEqual( ids( choice( { colour: [ 'oak' ] } ) ), [ 'p1' ] );
	} );

	it( 'waives a dependent attribute under a chosen parent value it does not apply under', () => {
		// Oak beside "No frame" keeps what is sold bare; p2 has no colour at all and fails.
		assert.deepEqual( ids( choice( { framing: [ 'none' ], colour: [ 'oak' ] } ) ), [
			'p1',
			'p3-none',
		] );
	} );

	it( 'filters on the displayed price', () => {
		assert.deepEqual( ids( choice( {}, { min: 160, max: 350 } ) ), [ 'p1', 'p3-none' ] );
	} );

	it( 'keeps the sort order, ties in the index order', () => {
		assert.deepEqual( ids( choice( {}, { sort: 'price' } ) ), [ 'p2', 'p1', 'p3-none' ] );
		assert.deepEqual( ids( choice( {}, { sort: 'unknown' } ) ), [ 'p1', 'p2', 'p3-none' ] );
	} );
} );

describe( 'counts', () => {
	it( 'counts each value as the cards shown with it added', () => {
		const result = counts( index, choice( { size: [ 'l' ] } ) );

		assert.deepEqual( Object.fromEntries( result.get( 'framing' ) ?? [] ), {
			none: 2,
			frame: 2,
		} );
		assert.deepEqual( Object.fromEntries( result.get( 'size' ) ?? [] ), { s: 3, l: 3 } );
		assert.equal( result.has( 'price' ), false );
	} );
} );

describe( 'counts, against the cards shown with each value added', () => {
	const choices: Record< string, string[] >[] = [
		{},
		{ size: [ 's' ] },
		{ framing: [ 'frame' ] },
		{ framing: [ 'none' ], colour: [ 'oak' ] },
		{ colour: [ 'black' ], size: [ 'l' ] },
		{ framing: [ 'none', 'frame' ], size: [ 's', 'l' ] },
	];

	for ( const values of choices ) {
		it( `agree for ${ JSON.stringify( values ) }`, () => {
			for ( const price of [ {}, { min: 160, max: 450 } ] ) {
				const current = choice( values, price );
				const result = counts( index, current );

				for ( const filter of index.filters.filter( ( f ) => f.type === 'attribute' ) ) {
					for ( const value of filter.values ?? [] ) {
						const added = new Map( current.values );
						added.set(
							filter.key,
							new Set( [ ...( current.values.get( filter.key ) ?? [] ), value.id ] ),
						);
						const expected = shownCards( index, { ...current, values: added } ).length;

						assert.equal(
							result.get( filter.key )?.get( value.id ) ?? 0,
							expected,
							`${ filter.key }=${ value.id }`,
						);
					}
				}
			}
		} );
	}
} );

describe( 'a shown card', () => {
	it( 'shows the price range of the variations the choice keeps', () => {
		const [ p1 ] = shownCards( index, choice( { framing: [ 'frame' ] } ) );
		assert.deepEqual( priceRange( p1.variations ), { min: 300, max: 400 } );
	} );

	it( 'links to its first matching variation’s single values', () => {
		const [ p1 ] = shownCards( index, choice( { colour: [ 'black' ] } ) );
		assert.equal(
			linkOf( p1, 'https://shop.test/' ),
			'https://shop.test/product/p1/?framing=frame&colour=black&size=l',
		);
		const [ , p2 ] = shownCards( index, choice() );
		assert.equal(
			linkOf( p2, 'https://shop.test/' ),
			'https://shop.test/product/p2/?framing=none',
		);
	} );

	it( 'shows the combination’s picture while a filter chooses what the card does not hold', () => {
		const [ p1 ] = shownCards( index, choice( { colour: [ 'oak' ] } ) );
		assert.equal( pictureOf( index, p1, choice( { colour: [ 'oak' ] } ) ).src, 'p1-oak.jpg' );
		assert.equal( pictureOf( index, p1, choice() ).src, 'p1.jpg' );
	} );
} );

describe( 'a large archive', () => {
	it( 'filters, sorts and counts 500 cards of 36 variations in well under a frame', () => {
		const large = generatedIndex( 500, 36 );
		const busy = choice( { size: [ 's1', 's2' ], framing: [ 'frame' ] }, { sort: 'price' } );
		// A first run warms the engine up, as a page that has filtered once would be.
		shownCards( large, busy );
		counts( large, busy );

		// The best of a few runs, so a machine busy with other work does not fail it.
		const RUNS = 5;
		const took = Math.min(
			...Array.from( { length: RUNS }, () => {
				const start = performance.now();
				shownCards( large, busy );
				counts( large, busy );

				return performance.now() - start;
			} ),
		);

		/** A frame's budget on a mid-range phone, with room for drawing: the work here must take a fraction of it. */
		const BUDGET_MS = 50;
		/** A desktop runs these about four times faster than a mid-range phone. */
		const PHONE_SLOWDOWN = 4;
		assert.ok( took * PHONE_SLOWDOWN < BUDGET_MS, `took ${ took.toFixed( 1 ) } ms` );
	} );
} );
