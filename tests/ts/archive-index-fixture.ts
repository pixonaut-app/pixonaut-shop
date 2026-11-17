import type { ArchiveIndex, Card } from '../../src/archive/index/types.ts';

/**
 * A generated archive index of `cards` products with `variations` each:
 * sizes, a framing and a frame colour that applies under a frame only.
 */
export function generatedIndex( cards: number, variations: number ): ArchiveIndex {
	const sizes = Array.from( { length: 6 }, ( _, i ) => `s${ i }` );
	const colours = [ 'oak', 'black', 'white' ];
	const framings = [ 'none', 'frame' ];
	const picture = { src: 'p.jpg', srcset: '', width: 600, height: 600 };
	/** Base and step of the generated prices, in whole kronor. */
	const BASE_PRICE = 199;
	const PRICE_STEP = 50;
	const PRICE_SPREAD = 7;

	const list: Card[] = Array.from( { length: cards }, ( _, n ) => ( {
		id: `c${ n }`,
		productId: n,
		title: `Card ${ n }`,
		url: `https://shop.test/product/c${ n }/`,
		summary: '',
		image: { ...picture, alt: '' },
		sort: { menu_order: n, price: ( n * PRICE_SPREAD ) % cards },
		holds: {},
		listedBy: [],
		onlyFiltered: [],
		choices: [ 'size', 'framing', 'colour' ],
		variations: Array.from( { length: variations }, ( __, v ) => {
			const framing = framings[ v % framings.length ];

			return {
				values: {
					size: [ sizes[ v % sizes.length ] ],
					framing: [ framing ],
					colour: framing === 'frame' ? [ colours[ v % colours.length ] ] : [],
				},
				price: BASE_PRICE + PRICE_STEP * ( v % PRICE_SPREAD ),
				regularPrice: BASE_PRICE + PRICE_STEP * ( v % PRICE_SPREAD ),
			};
		} ),
	} ) );

	return {
		version: 'generated',
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
				key: 'size',
				label: 'Size',
				type: 'attribute',
				values: sizes.map( ( id, order ) => ( { id, label: id, order } ) ),
			},
			{
				key: 'framing',
				label: 'Framing',
				type: 'attribute',
				values: framings.map( ( id, order ) => ( { id, label: id, order } ) ),
			},
			{
				key: 'colour',
				label: 'Colour',
				type: 'attribute',
				values: colours.map( ( id, order ) => ( { id, label: id, order } ) ),
				parent: { key: 'framing', values: [ 'frame' ] },
			},
			{ key: 'price', label: 'Price', type: 'price' },
		],
		sorts: [
			{ key: 'menu_order', label: 'Default' },
			{ key: 'price', label: 'Price' },
		],
		cards: list,
		images: [],
	};
}
